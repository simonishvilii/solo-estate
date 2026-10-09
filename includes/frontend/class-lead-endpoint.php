<?php
/**
 * Endpoints of the lead form: AJAX, and a plain POST fallback for pages where JavaScript
 * did not run.
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

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'wp_ajax_solo_estate_lead', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_solo_estate_lead', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_solo_estate_lead_post', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_post_nopriv_solo_estate_lead_post', array( __CLASS__, 'handle_post' ) );
	}

	/**
	 * AJAX submission (the form with JavaScript): JSON answer.
	 */
	public static function handle() {
		$result = self::process();
		$data   = array( 'message' => $result['message'] );
		if ( $result['fields'] ) {
			$data['fields'] = $result['fields'];
		}
		if ( $result['ok'] ) {
			wp_send_json_success( $data );
		}
		wp_send_json_error( $data, $result['status'] );
	}

	/**
	 * Plain form submission, when the page's JavaScript did not run (blocked, broken by an
	 * optimization plugin): the lead is stored the same way and the visitor goes back to the
	 * page with the result. POST keeps the name and phone out of URLs and logs.
	 */
	public static function handle_post() {
		$result = self::process();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see class docblock.
		$back = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		if ( '' === $back || wp_parse_url( $back, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			$back = home_url( '/' );
		}
		$back = add_query_arg( 'solo_estate_lead', $result['ok'] ? 'sent' : 'error', remove_query_arg( 'solo_estate_lead', $back ) );
		wp_safe_redirect( $back . '#solo-estate-lead-result' );
		exit;
	}

	/**
	 * Validates and stores a lead.
	 *
	 * @return array{ok:bool,status:int,message:string,fields:string[]}
	 */
	private static function process() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see class docblock.
		$lang = I18n::sanitize_code( isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lang'] ) ) : '' );

		if ( ! Settings::get( 'leads_enabled' ) ) {
			return self::result( false, 403, $lang );
		}

		$honeypot = isset( $_POST['website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['website'] ) ) ) : '';
		$ts       = isset( $_POST['ts'] ) ? absint( $_POST['ts'] ) : 0;
		// The form always sends its render time; missing, too fast or future values mean a bot.
		if ( '' !== $honeypot || ! $ts || time() - $ts < self::MIN_SECONDS || $ts > time() + 60 ) {
			// Pretend success so bots don't retry; counted, so the admin sees that the filter works.
			Leads::count_rejected( '' !== $honeypot ? 'honeypot' : 'timing' );
			return self::result( true, 200, $lang );
		}

		$ip     = self::ip();
		$key    = 'solo_estate_rl_' . md5( $ip );
		$hit    = (int) get_transient( $key );
		if ( $hit >= self::RATE_LIMIT ) {
			Leads::count_rejected( 'rate_limit' );
			return self::result( false, 429, $lang );
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
			return self::result( false, 422, $lang, $errors );
		}

		// Only keep page URLs from this site.
		if ( $page && wp_parse_url( $page, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			$page = '';
		}

		set_transient( $key, $hit + 1, self::RATE_WINDOW );

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
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by Attribution::sanitize().
				'attribution' => \SoloEstate\Attribution::enabled() && isset( $_POST['attribution'] ) ? \SoloEstate\Attribution::sanitize( wp_unslash( $_POST['attribution'] ) ) : array(),
			)
		);

		return self::result( (bool) $id, $id ? 200 : 500, $lang );
	}

	/**
	 * Result of a submission.
	 *
	 * @param bool     $ok     Stored (or a bot answered with fake success).
	 * @param int      $status HTTP status.
	 * @param string   $lang   Language of the message.
	 * @param string[] $fields Invalid fields.
	 * @return array{ok:bool,status:int,message:string,fields:string[]}
	 */
	private static function result( $ok, $status, $lang, array $fields = array() ) {
		return array(
			'ok'      => $ok,
			'status'  => $status,
			'message' => Texts::get( $ok ? 'lead_success' : 'lead_error', $lang ),
			'fields'  => $fields,
		);
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
