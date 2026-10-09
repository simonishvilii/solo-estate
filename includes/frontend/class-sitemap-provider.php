<?php
/**
 * WordPress core sitemap provider for Solo Estate item pages (wp-sitemap-soloestate-1.xml).
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

defined( 'ABSPATH' ) || exit;

class Sitemap_Provider extends \WP_Sitemaps_Provider {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name        = 'soloestate';
		$this->object_type = 'soloestate';
	}

	/**
	 * Addresses on one sitemap page.
	 *
	 * @param int    $page_num       Page, from 1.
	 * @param string $object_subtype Unused.
	 * @return array[]
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$max   = wp_sitemaps_get_max_urls( $this->object_type );
		$slice = array_slice( Sitemap::urls(), max( 0, ( (int) $page_num - 1 ) * $max ), $max );
		return array_map(
			static function ( $url ) {
				return array( 'loc' => $url );
			},
			$slice
		);
	}

	/**
	 * Number of sitemap pages.
	 *
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		return (int) ceil( count( Sitemap::urls() ) / wp_sitemaps_get_max_urls( $this->object_type ) );
	}
}
