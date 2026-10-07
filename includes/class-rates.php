<?php
/**
 * Exchange rate of the second currency, taken from the National Bank of Georgia.
 *
 * Prices are stored in the base currency (usually GEL). The second currency (usually USD)
 * is price × rate. With the "National Bank of Georgia" source the rate is fetched in the
 * background a few times a day; the manual rate from Settings is used until the first
 * successful fetch and whenever the bank cannot be reached.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Rates {

	const OPTION = 'solo_estate_rate';
	const HOOK   = 'solo_estate_refresh_rate';
	const API    = 'https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/en/json/';

	/** Refresh interval, and the retry delay after a failed request. */
	const TTL   = 6 * HOUR_IN_SECONDS;
	const RETRY = HOUR_IN_SECONDS;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'refresh' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 20 );
	}

	/**
	 * Whether the bank rate is used.
	 *
	 * @return bool
	 */
	public static function uses_bank() {
		return Settings::get( 'alt_enabled' ) && 'nbg' === Settings::get( 'rate_source' );
	}

	/**
	 * Schedules a background refresh when the stored rate is old. Never blocks a page view.
	 */
	public static function maybe_schedule() {
		if ( ! self::uses_bank() || wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$data  = self::stored();
		$delay = ! empty( $data['error'] ) ? self::RETRY : self::TTL;
		// Currencies changed since the last check, or the last check is old (a failed one is
		// retried after an hour, not on every page view).
		$changed = ! isset( $data['pair'] ) || self::pair() !== $data['pair'];
		if ( $changed || empty( $data['checked'] ) || time() - (int) $data['checked'] > $delay ) {
			wp_schedule_single_event( time(), self::HOOK );
		}
	}

	/**
	 * Multiplier from the base currency to the second currency.
	 *
	 * @return float
	 */
	public static function rate() {
		if ( self::uses_bank() ) {
			$data = self::stored();
			if ( self::matches( $data ) && ! empty( $data['rate'] ) && (float) $data['rate'] > 0 ) {
				return (float) $data['rate'];
			}
		}
		return max( 0.0, (float) Settings::get( 'alt_rate' ) );
	}

	/**
	 * Stored result of the last fetch.
	 *
	 * @return array{rate?:float,date?:string,checked?:int,base?:string,alt?:string,error?:string}
	 */
	public static function stored() {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Fetches the rate now. Keeps the previous rate when the bank cannot be reached.
	 *
	 * @return bool Success.
	 */
	public static function refresh() {
		$base = self::code( Settings::get( 'base_currency' ) );
		$alt  = self::code( Settings::get( 'alt_currency' ) );
		$data = self::stored();

		$data['checked'] = time();
		$data['pair']    = self::pair();
		$data['error']   = '';

		$base_gel = self::gel_per_unit( $base, $date_base );
		$alt_gel  = self::gel_per_unit( $alt, $date_alt );
		if ( $base_gel && $alt_gel ) {
			$data['rate'] = round( $base_gel / $alt_gel, 6 );
			$data['date'] = $date_alt ? $date_alt : $date_base;
			$data['base'] = $base;
			$data['alt']  = $alt;
		} else {
			$data['error'] = __( 'The National Bank of Georgia could not be reached, or it has no rate for this currency.', 'solo-estate' );
		}
		update_option( self::OPTION, $data, false );
		return '' === $data['error'];
	}

	/**
	 * Lari per one unit of a currency, as published by the bank (1.0 for GEL).
	 *
	 * @param string      $code ISO code.
	 * @param string|null $date Set to the rate date.
	 * @return float|null
	 */
	private static function gel_per_unit( $code, &$date = null ) {
		$date = '';
		if ( 'GEL' === $code ) {
			return 1.0;
		}
		if ( '' === $code ) {
			return null;
		}
		$response = wp_safe_remote_get(
			add_query_arg( 'currencies', $code, self::API ),
			array(
				'timeout'    => 10,
				'user-agent' => 'Solo Estate/' . SOLO_ESTATE_VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		return self::parse( wp_remote_retrieve_body( $response ), $code, $date );
	}

	/**
	 * Reads a rate from the bank's JSON: [{"date": …, "currencies": [{"code": "USD", "quantity": 1, "rate": 2.7, …}]}].
	 *
	 * @param string      $body JSON.
	 * @param string      $code ISO code.
	 * @param string|null $date Set to the rate date.
	 * @return float|null Lari per one unit.
	 */
	public static function parse( $body, $code, &$date = null ) {
		$json = json_decode( (string) $body, true );
		$date = '';
		if ( ! is_array( $json ) ) {
			return null;
		}
		foreach ( isset( $json[0] ) ? $json : array( $json ) as $day ) {
			if ( ! is_array( $day ) || empty( $day['currencies'] ) || ! is_array( $day['currencies'] ) ) {
				continue;
			}
			foreach ( $day['currencies'] as $currency ) {
				if ( ! is_array( $currency ) || ! isset( $currency['code'], $currency['rate'] ) || strtoupper( (string) $currency['code'] ) !== $code ) {
					continue;
				}
				$rate     = (float) $currency['rate'];
				$quantity = isset( $currency['quantity'] ) && (float) $currency['quantity'] > 0 ? (float) $currency['quantity'] : 1.0;
				if ( $rate <= 0 ) {
					return null;
				}
				$date = substr( sanitize_text_field( (string) ( isset( $currency['validFromDate'] ) ? $currency['validFromDate'] : ( isset( $day['date'] ) ? $day['date'] : '' ) ) ), 0, 10 );
				return $rate / $quantity;
			}
		}
		return null;
	}

	/**
	 * Configured currency pair, e.g. "GEL/USD".
	 *
	 * @return string
	 */
	private static function pair() {
		return self::code( Settings::get( 'base_currency' ) ) . '/' . self::code( Settings::get( 'alt_currency' ) );
	}

	/**
	 * Whether a stored rate belongs to the currencies currently configured.
	 *
	 * @param array $data Stored data.
	 * @return bool
	 */
	private static function matches( array $data ) {
		return isset( $data['base'], $data['alt'] ) && self::code( Settings::get( 'base_currency' ) ) === $data['base'] && self::code( Settings::get( 'alt_currency' ) ) === $data['alt'];
	}

	/**
	 * Normalized ISO code ("usd " → "USD"; anything else → '').
	 *
	 * @param mixed $code Code.
	 * @return string
	 */
	private static function code( $code ) {
		$code = strtoupper( trim( (string) $code ) );
		return preg_match( '/^[A-Z]{3}$/', $code ) ? $code : '';
	}
}
