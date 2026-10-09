<?php
/**
 * Plugin bootstrap.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'init' ), 5 );
		add_action( 'solo_estate_daily', array( Leads::class, 'cleanup' ) );
		add_action( 'solo_estate_daily', array( Trash::class, 'cleanup' ) );
		add_action( Leads::RETRY_HOOK, array( Leads::class, 'retry_webhook' ), 10, 2 );
		Rates::register();
		Cache::register();
		Labels::register();

		Frontend\Shortcode::register();
		Frontend\Lead_Endpoint::register();
		Frontend\Block::register();
		Frontend\Language_Links::register();
		Frontend\Seo::register();
		Attribution::register();
		Frontend\Sitemap::register();
		Frontend\Admin_Bar::register();

		if ( is_admin() ) {
			Admin\Admin::register();
		}
	}

	/**
	 * Version string for a CSS/JS file: plugin version plus the file's modification time, so
	 * browsers, caching plugins and CDNs fetch the new file after every update.
	 *
	 * @param string $path Path inside the plugin, e.g. assets/css/frontend.css.
	 * @return string
	 */
	public static function asset_version( $path ) {
		$file = SOLO_ESTATE_DIR . ltrim( $path, '/' );
		$time = is_readable( $file ) ? filemtime( $file ) : 0;
		return $time ? SOLO_ESTATE_VERSION . '.' . $time : SOLO_ESTATE_VERSION;
	}

	/**
	 * Loads translations and upgrades the schema when needed.
	 */
	public function init() {
		load_plugin_textdomain( 'solo-estate', false, dirname( plugin_basename( SOLO_ESTATE_FILE ) ) . '/languages' );
		Install::maybe_upgrade();
		if ( ! wp_next_scheduled( 'solo_estate_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'solo_estate_daily' );
		}
	}
}
