<?php
/**
 * Page cache purge after data changes.
 *
 * The selector's content lives in custom tables, so page caching plugins never notice that an
 * apartment was sold or a price changed (they purge on post updates), and cached pages kept
 * showing old statuses until their TTL ran out. Every change fires
 * `solo_estate_data_changed`; the page caches of the common caching plugins are purged once
 * at the end of that request.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Cache {

	/** @var bool Purge requested in this request. */
	private static $pending = false;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'solo_estate_data_changed', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Notes that a purge is needed; it runs once, at shutdown (after redirects too).
	 */
	public static function schedule() {
		if ( self::$pending ) {
			return;
		}
		self::$pending = true;
		add_action( 'shutdown', array( __CLASS__, 'purge' ), 1 );
	}

	/**
	 * Purges the full-page caches that are present.
	 */
	public static function purge() {
		/**
		 * Whether Solo Estate purges page caches after its data changes.
		 *
		 * @param bool $purge Purge.
		 */
		if ( ! apply_filters( 'solo_estate_purge_page_cache', true ) ) {
			return;
		}
		// LiteSpeed Cache (also purges Cloudflare when its CDN integration is on).
		do_action( 'litespeed_purge_all' );
		// WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		// WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		// SiteGround Speed Optimizer.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		// WP Fastest Cache, Cache Enabler, Breeze, Hummingbird.
		do_action( 'wpfc_clear_all_cache' );
		do_action( 'cache_enabler_clear_complete_cache' );
		do_action( 'breeze_clear_all_cache' );
		do_action( 'wphb_clear_page_cache' );
		/**
		 * After Solo Estate purged the page caches; purge others here (a CDN, a host cache).
		 */
		do_action( 'solo_estate_purge_cache' );
	}
}
