<?php
/**
 * Item pages in the XML sitemap.
 *
 * The selector's pages (?solo_estate_node=ID on the page holding it) are reached only through
 * the image and its links, so search engines found few of them. They are listed in WordPress's
 * own sitemap (wp-sitemap.xml) and, with Yoast SEO, in its sitemap index.
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

use SoloEstate\Install;
use SoloEstate\Nodes;

defined( 'ABSPATH' ) || exit;

class Sitemap {

	/** Transient holding the list. */
	const CACHE = 'solo_estate_sitemap';

	/** Transient holding the pages that show the selector. */
	const HOSTS = 'solo_estate_sitemap_hosts';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'providers' ), 20 );
		add_action( 'solo_estate_data_changed', array( __CLASS__, 'flush' ) );
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Registers the sitemap with WordPress and Yoast SEO.
	 */
	public static function providers() {
		/**
		 * Whether Solo Estate adds its item pages to the XML sitemaps.
		 *
		 * @param bool $add Add.
		 */
		if ( ! apply_filters( 'solo_estate_sitemap', true ) ) {
			return;
		}
		if ( function_exists( 'wp_register_sitemap_provider' ) ) {
			wp_register_sitemap_provider( 'soloestate', new Sitemap_Provider() );
		}
		global $wpseo_sitemaps;
		if ( is_object( $wpseo_sitemaps ) && method_exists( $wpseo_sitemaps, 'register_sitemap' ) ) {
			$wpseo_sitemaps->register_sitemap( 'solo-estate', array( __CLASS__, 'yoast' ) );
			add_filter( 'wpseo_sitemap_index', array( __CLASS__, 'yoast_index' ) );
		}
	}

	/**
	 * Forgets the list after data or page changes.
	 */
	public static function flush() {
		delete_transient( self::CACHE );
		delete_transient( self::HOSTS );
	}

	/**
	 * Addresses of all item pages that visitors can open, on every page holding the selector.
	 * With Polylang or WPML (one sitemap per language) only the current language's pages.
	 *
	 * @return string[]
	 */
	public static function urls() {
		$all = get_transient( self::CACHE );
		if ( ! is_array( $all ) ) {
			$all   = array();
			$hosts = self::hosts();
			if ( $hosts ) {
				$pages = self::pages();
				foreach ( $hosts as $host ) {
					$base = get_permalink( $host[0] );
					if ( ! $base ) {
						continue;
					}
					$lang = $host[2];
					foreach ( $pages as $node ) {
						if ( $host[1] && ( (int) $node->project_id !== $host[1] || (int) $node->id === $host[1] ) ) {
							continue;
						}
						// A project with a page of its own is listed there, not in the catalog.
						if ( ! $host[1] && self::home_of( (int) ( $node->project_id ? $node->project_id : $node->id ), $lang ) ) {
							continue;
						}
						$all[ add_query_arg( Renderer::QUERY_ARG, (int) $node->id, $base ) ] = $lang;
					}
				}
			}
			set_transient( self::CACHE, $all, 12 * HOUR_IN_SECONDS );
		}
		$current = self::current_language();
		$urls    = array();
		foreach ( $all as $url => $lang ) {
			if ( '' === $current || '' === $lang || $lang === $current ) {
				$urls[] = (string) $url;
			}
		}
		/**
		 * Item page addresses listed in the sitemap.
		 *
		 * @param string[] $urls URLs.
		 */
		return array_values( (array) apply_filters( 'solo_estate_sitemap_urls', $urls ) );
	}

	/**
	 * Language of a page (Polylang, WPML); '' without a multilingual plugin.
	 *
	 * @param int $post_id Page id.
	 * @return string
	 */
	public static function language( $post_id ) {
		if ( function_exists( 'pll_get_post_language' ) ) {
			return (string) pll_get_post_language( $post_id );
		}
		$details = apply_filters( 'wpml_post_language_details', null, $post_id );
		return is_array( $details ) && ! empty( $details['language_code'] ) ? (string) $details['language_code'] : '';
	}

	/**
	 * Language of the sitemap being served; '' for all.
	 *
	 * @return string
	 */
	private static function current_language() {
		if ( function_exists( 'pll_current_language' ) ) {
			return (string) pll_current_language();
		}
		return defined( 'ICL_SITEPRESS_VERSION' ) ? (string) apply_filters( 'wpml_current_language', null ) : '';
	}

	/**
	 * The page showing a single project (in a language), if there is one: item pages of that
	 * project found through the catalog name it as their canonical address.
	 *
	 * @param int    $project_id Project id.
	 * @param string $lang       Language ('' : any).
	 * @return int Page id (0: none).
	 */
	public static function home_of( $project_id, $lang = '' ) {
		foreach ( self::hosts() as $host ) {
			if ( $host[1] === (int) $project_id && ( '' === $lang || '' === $host[2] || $host[2] === $lang ) ) {
				return $host[0];
			}
		}
		return 0;
	}

	/**
	 * Published pages holding the selector: [post id, project id (0: catalog), language].
	 *
	 * @return array<int,array{0:int,1:int,2:string}>
	 */
	public static function hosts() {
		static $hosts = null;
		if ( null === $hosts ) {
			$hosts = get_transient( self::HOSTS );
			if ( ! is_array( $hosts ) ) {
				$hosts = self::find_hosts();
				set_transient( self::HOSTS, $hosts, 12 * HOUR_IN_SECONDS );
			}
		}
		return $hosts;
	}

	/**
	 * Looks up the pages holding the selector.
	 *
	 * @return array<int,array{0:int,1:int,2:string}>
	 */
	private static function find_hosts() {
		global $wpdb;
		$types = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) );
		if ( ! $types ) {
			return array();
		}
		$in = implode( ',', array_map( static function ( $type ) use ( $wpdb ) {
			return $wpdb->prepare( '%s', $type );
		}, $types ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- types prepared above.
		$ids = $wpdb->get_col( "SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data' WHERE p.post_status = 'publish' AND p.post_password = '' AND p.post_type IN ($in) AND ( p.post_content LIKE '%[solo\\_estate%' OR p.post_content LIKE '%wp:solo-estate/project%' OR m.meta_value LIKE '%[solo\\_estate%' ) ORDER BY p.ID" );
		$hosts = array();
		foreach ( $ids as $id ) {
			$post    = get_post( (int) $id );
			$project = $post ? Seo::host_project( $post ) : -1;
			if ( $project >= 0 ) {
				$hosts[] = array( (int) $id, $project, self::language( (int) $id ) );
			}
		}
		return $hosts;
	}

	/**
	 * Items with a page of their own: open (with every parent), not an info window, not a
	 * level the selector skips.
	 *
	 * @return object[]
	 */
	private static function pages() {
		global $wpdb;
		$table = Install::table( 'nodes' );
		$ids   = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM $table ORDER BY path, id" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		Nodes::prime( $ids );
		Nodes::prime_stats();
		$open  = array();
		$pages = array();
		// Parents come before their children (materialized path order).
		foreach ( $ids as $id ) {
			$node = Nodes::get( $id );
			if ( ! $node ) {
				continue;
			}
			$parent      = (int) $node->parent_id;
			$open[ $id ] = ( ! $parent || ! empty( $open[ $parent ] ) ) && Renderer::is_open( $node ) && ! Renderer::is_info( $node );
			if ( $open[ $id ] && ! Renderer::skips( $node ) ) {
				$pages[] = $node;
			}
		}
		return $pages;
	}

	/**
	 * Yoast SEO: the sitemap itself.
	 */
	public static function yoast() {
		global $wpseo_sitemaps;
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( self::urls() as $url ) {
			$xml .= "\t<url><loc>" . esc_url( $url ) . "</loc></url>\n";
		}
		$xml .= '</urlset>';
		$wpseo_sitemaps->set_sitemap( $xml );
	}

	/**
	 * Yoast SEO: the entry in its sitemap index.
	 *
	 * @param string $xml Index entries.
	 * @return string
	 */
	public static function yoast_index( $xml ) {
		if ( ! self::urls() ) {
			return $xml;
		}
		return $xml . '<sitemap><loc>' . esc_url( home_url( 'solo-estate-sitemap.xml' ) ) . '</loc></sitemap>' . "\n";
	}
}
