<?php
/**
 * Where a lead came from: ad campaign tags (utm_*), ad click ids, the referring site and the
 * first page of the visit.
 *
 * Pages are served from caches, so the server cannot see each visitor's arrival. A small script
 * on every page remembers it in the visitor's browser (localStorage, 90 days; a later visit
 * from a campaign or another site replaces it, direct visits keep it) and the lead form sends
 * it along. It is stored with the lead and shown in the admin, the e-mail and the webhook.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Attribution {

	/** Campaign tags and ad click ids read from the landing page address. */
	const PARAMS = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid', 'yclid', 'li_fat_id' );

	/** Click id parameters, by ad network. */
	const CLICK_IDS = array(
		'gclid'     => 'Google Ads',
		'gbraid'    => 'Google Ads',
		'wbraid'    => 'Google Ads',
		'fbclid'    => 'Meta',
		'msclkid'   => 'Microsoft Ads',
		'ttclid'    => 'TikTok',
		'yclid'     => 'Yandex',
		'li_fat_id' => 'LinkedIn',
	);

	/** localStorage key. */
	const STORAGE_KEY = 'solo_estate_attribution';

	/**
	 * Hooks.
	 */
	public static function register() {
		if ( ! is_admin() ) {
			add_action( 'wp_footer', array( __CLASS__, 'script' ), 5 );
		}
	}

	/**
	 * Whether sources are recorded (Settings → Leads).
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) Settings::get( 'lead_attribution' ) && (bool) Settings::get( 'leads_enabled' );
	}

	/**
	 * The script that remembers the visit's source, on every page of the site (visitors
	 * usually arrive on another page than the one with the form).
	 */
	public static function script() {
		if ( ! self::enabled() ) {
			return;
		}
		$js = '(function(){try{var K=' . wp_json_encode( self::STORAGE_KEY ) . ',P=' . wp_json_encode( self::PARAMS ) . ','
			. 'q=new URLSearchParams(location.search),d={},hit=false,s=null,r=document.referrer,ext=false;'
			. 'P.forEach(function(k){var v=q.get(k);if(v){d[k]=v.slice(0,200);hit=true;}});'
			. 'try{ext=!!r&&new URL(r).host!==location.host;}catch(e){}'
			. 'try{s=JSON.parse(localStorage.getItem(K));}catch(e){}'
			. 'if(s&&(!s.t||Date.now()-s.t>7776e6)){s=null;}'
			// A campaign or another site starts a new source; direct visits and pages of the site keep it.
			. 'if(hit||ext||!s){if(ext){d.referrer=r.slice(0,500);}d.landing_page=(location.origin+location.pathname+location.search).slice(0,500);d.t=Date.now();localStorage.setItem(K,JSON.stringify(d));}'
			. '}catch(e){}})();';
		wp_print_inline_script_tag( $js, array( 'id' => 'solo-estate-attribution' ) );
	}

	/**
	 * Sanitized source from the form's JSON.
	 *
	 * @param mixed $raw JSON string.
	 * @return array<string,string>
	 */
	public static function sanitize( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > 4000 ) {
			return array();
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		foreach ( self::PARAMS as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				$value = mb_substr( sanitize_text_field( (string) $data[ $key ] ), 0, 200 );
				if ( '' !== $value ) {
					$out[ $key ] = $value;
				}
			}
		}
		foreach ( array( 'referrer', 'landing_page' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				$url = esc_url_raw( mb_substr( $data[ $key ], 0, 500 ), array( 'http', 'https' ) );
				if ( '' !== $url ) {
					$out[ $key ] = $url;
				}
			}
		}
		if ( $out && isset( $data['t'] ) && is_numeric( $data['t'] ) ) {
			$time = (int) ( $data['t'] / 1000 );
			// Only a plausible arrival time (within the 90 days the browser keeps it).
			if ( $time > time() - 91 * DAY_IN_SECONDS && $time <= time() + HOUR_IN_SECONDS ) {
				$out['first_seen'] = gmdate( 'Y-m-d H:i:s', $time );
			}
		}
		return $out;
	}

	/**
	 * Stored source of a lead.
	 *
	 * @param object|array $lead Lead row.
	 * @return array<string,string>
	 */
	public static function of( $lead ) {
		$json = is_array( $lead ) ? ( isset( $lead['attribution'] ) ? $lead['attribution'] : '' ) : ( isset( $lead->attribution ) ? $lead->attribution : '' );
		$data = $json ? json_decode( (string) $json, true ) : null;
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Short description: "google / cpc / spring_sale", "Google Ads (click id)",
	 * "facebook.com" or "Direct".
	 *
	 * @param array $data Source.
	 * @return string
	 */
	public static function summary( array $data ) {
		if ( ! $data ) {
			return '';
		}
		$utm = array_filter( array( isset( $data['utm_source'] ) ? $data['utm_source'] : '', isset( $data['utm_medium'] ) ? $data['utm_medium'] : '', isset( $data['utm_campaign'] ) ? $data['utm_campaign'] : '' ), 'strlen' );
		if ( $utm ) {
			return implode( ' / ', $utm );
		}
		foreach ( self::CLICK_IDS as $key => $network ) {
			if ( ! empty( $data[ $key ] ) ) {
				/* translators: %s: ad network */
				return sprintf( __( '%s (ad click)', 'solo-estate' ), $network );
			}
		}
		if ( ! empty( $data['referrer'] ) ) {
			$host = wp_parse_url( $data['referrer'], PHP_URL_HOST );
			return $host ? preg_replace( '/^www\./', '', $host ) : $data['referrer'];
		}
		return __( 'Direct', 'solo-estate' );
	}

	/**
	 * Labelled lines for the e-mail and the admin.
	 *
	 * @param array $data Source.
	 * @return array<string,string> Label => value.
	 */
	public static function lines( array $data ) {
		if ( ! $data ) {
			return array();
		}
		$lines = array( __( 'Source', 'solo-estate' ) => self::summary( $data ) );
		foreach ( array( 'utm_term' => __( 'Keyword', 'solo-estate' ), 'utm_content' => __( 'Ad content', 'solo-estate' ) ) as $key => $label ) {
			if ( ! empty( $data[ $key ] ) ) {
				$lines[ $label ] = $data[ $key ];
			}
		}
		foreach ( self::CLICK_IDS as $key => $network ) {
			if ( ! empty( $data[ $key ] ) ) {
				$lines[ $network . ' (' . $key . ')' ] = $data[ $key ];
			}
		}
		if ( ! empty( $data['referrer'] ) ) {
			$lines[ __( 'Referring page', 'solo-estate' ) ] = $data['referrer'];
		}
		if ( ! empty( $data['landing_page'] ) ) {
			$lines[ __( 'First page of the visit', 'solo-estate' ) ] = $data['landing_page'];
		}
		if ( ! empty( $data['first_seen'] ) ) {
			$lines[ __( 'Arrived', 'solo-estate' ) ] = get_date_from_gmt( $data['first_seen'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		}
		return $lines;
	}
}
