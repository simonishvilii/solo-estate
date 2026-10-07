<?php
/**
 * AJAX endpoint for the lead form.
 *
 * Public form for anonymous visitors: a nonce would break on cached pages and gives
 * no real protection for logged-out users, so spam is filtered with a honeypot,
 * a minimum fill time and a per-IP rate limit instead.
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

use SoloEstate\I18n;
use SoloEstate\Leads;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

class Lead_Endpoint {

	const RATE_LIMIT  = 5;   // Requests…
	const RATE_WINDOW = 600; // …per 10 minutes per IP.
	const MIN_SECONDS = 2;   // Faster submissions are treated as bots.
	const GLOBAL_LIMIT = 60; // Site-wide cap per hour, against floods from many IPs.

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'wp_ajax_solo_estate_lead', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_solo_estate_lead', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Validates and stores a lead.
	 */
	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see class docblock.
		$lang = I18n::sanitize_code( isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lang'] ) ) : '' );

		if ( ! Settings::get( 'leads_enabled' ) ) {
			wp_send_json_error( array( 'message' => Texts::get( 'lead_error', $lang ) ), 403 );
		}

		$honeypot = isset( $_POST['website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['website'] ) ) ) : '';
		$ts       = isset( $_POST['ts'] ) ? absint( $_POST['ts'] ) : 0;
		// The form always sends its render time; missing, too fast or future values mean a bot.
		if ( '' !== $honeypot || ! $ts || time() - $ts < self::MIN_SECONDS || $ts > time() + 60 ) {
			// Pretend success so bots don't retry.
			wp_send_json_success( array( 'message' => Texts::get( 'lead_success', $lang ) ) );
		}

		$ip     = self::ip();
		$key    = 'solo_estate_rl_' . md5( $ip );
		$hit    = (int) get_transient( $key );
		$global = (int) get_transient( 'solo_estate_rl_global' );
		if ( $hit >= self::RATE_LIMIT || $global >= self::GLOBAL_LIMIT ) {
			wp_send_json_error( array( 'message' => Texts::get( 'lead_error', $lang ) ), 429 );
		}

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$msg   = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$node  = Nodes::get( isset( $_POST['node_id'] ) ? absint( $_POST['node_id'] ) : 0 );
		$page  = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		// phpcs:enable

		$errors = array();
		if ( '' === $name || mb_strlen( $name ) > 100 ) {
			$errors[] = 'name';
		}
		$digits = preg_replace( '/\D/', '', $phone );
		if ( strlen( $digits ) < 6 || strlen( $digits ) > 20 || ! preg_match( '/^[\d\s()+\-.]+$/', $phone ) ) {
			$errors[] = 'phone';
		}
		if ( $errors ) {
			wp_send_json_error(
				array(
					'message' => Texts::get( 'lead_error', $lang ),
					'fields'  => $errors,
				),
				422
			);
		}

		// Only keep page URLs from this site.
		if ( $page && wp_parse_url( $page, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			$page = '';
		}

		set_transient( $key, $hit + 1, self::RATE_WINDOW );
		set_transient( 'solo_estate_rl_global', $global + 1, HOUR_IN_SECONDS );

		$id = Leads::create(
			array(
				'name'     => $name,
				'phone'    => $phone,
				'email'    => $email,
				'message'  => $msg,
				'node_id'  => $node ? $node->id : 0,
				'lang'     => $lang,
				'page_url' => $page,
				'ip'       => $ip,
			)
		);

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => Texts::get( 'lead_error', $lang ) ), 500 );
		}
		wp_send_json_success( array( 'message' => Texts::get( 'lead_success', $lang ) ) );
	}

	/**
	 * Visitor IP. REMOTE_ADDR by default; proxy headers only when enabled in settings,
	 * because they can be forged when the site is not actually behind that proxy.
	 *
	 * @return string
	 */
	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$header = Settings::get( 'ip_header' );
		if ( 'cf' === $header && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		} elseif ( 'xff' === $header && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = trim( $parts[0] );
		}
		/**
		 * Filters the visitor IP, e.g. to trust a CDN header.
		 *
		 * @param string $ip IP.
		 */
		$ip = (string) apply_filters( 'solo_estate_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
