<?php
/**
 * Search engines: every building, floor and apartment page gets its own title, description,
 * canonical URL, social preview and structured data.
 *
 * The selector's pages are one WordPress page with ?solo_estate_node=ID, so without this they
 * all shared the host page's title, description and canonical URL (the page without the
 * argument), which told search engines they were duplicates of it. Unknown ids answer 404.
 *
 * Works on its own (core title and canonical, meta description and Open Graph tags) and
 * through Yoast SEO, Rank Math, All in One SEO and SEOPress when one of them is active.
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

use SoloEstate\Nodes;
use SoloEstate\Statuses;

defined( 'ABSPATH' ) || exit;

class Seo {

	/** @var bool Context worked out for this request. */
	private static $ready = false;

	/** @var \WP_Post|null Page holding the selector, when a node page was requested. */
	private static $host = null;

	/** @var object|null Item the page shows (null: the host page itself). */
	private static $current = null;

	/** @var object|null Project of the shortcode (null: the catalog of all projects). */
	private static $project = null;

	/** @var bool The requested item does not exist (or belongs to another project). */
	private static $missing = false;

	/**
	 * Hooks (front end only).
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'setup' ), 5 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );

		// Core.
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ), 20 );
		add_filter( 'get_canonical_url', array( __CLASS__, 'canonical_core' ), 20, 2 );
		// Yoast SEO.
		add_filter( 'wpseo_title', array( __CLASS__, 'full_title' ), 20 );
		add_filter( 'wpseo_opengraph_title', array( __CLASS__, 'title' ), 20 );
		add_filter( 'wpseo_twitter_title', array( __CLASS__, 'title' ), 20 );
		add_filter( 'wpseo_metadesc', array( __CLASS__, 'description' ), 20 );
		add_filter( 'wpseo_opengraph_desc', array( __CLASS__, 'description' ), 20 );
		add_filter( 'wpseo_twitter_description', array( __CLASS__, 'description' ), 20 );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'canonical' ), 20 );
		add_filter( 'wpseo_opengraph_url', array( __CLASS__, 'canonical' ), 20 );
		add_filter( 'wpseo_robots', array( __CLASS__, 'robots_string' ), 20 );
		add_action( 'wpseo_add_opengraph_images', array( __CLASS__, 'yoast_image' ) );
		// Rank Math.
		add_filter( 'rank_math/frontend/title', array( __CLASS__, 'full_title' ), 20 );
		add_filter( 'rank_math/frontend/description', array( __CLASS__, 'description' ), 20 );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'canonical' ), 20 );
		add_filter( 'rank_math/opengraph/facebook/og_title', array( __CLASS__, 'title' ), 20 );
		add_filter( 'rank_math/opengraph/facebook/og_description', array( __CLASS__, 'description' ), 20 );
		add_filter( 'rank_math/opengraph/twitter/twitter_title', array( __CLASS__, 'title' ), 20 );
		add_filter( 'rank_math/opengraph/twitter/twitter_description', array( __CLASS__, 'description' ), 20 );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'robots_array' ), 20 );
		// All in One SEO.
		add_filter( 'aioseo_title', array( __CLASS__, 'full_title' ), 20 );
		add_filter( 'aioseo_description', array( __CLASS__, 'description' ), 20 );
		add_filter( 'aioseo_canonical_url', array( __CLASS__, 'canonical' ), 20 );
		// SEOPress.
		add_filter( 'seopress_titles_title', array( __CLASS__, 'full_title' ), 20 );
		add_filter( 'seopress_titles_desc', array( __CLASS__, 'description' ), 20 );
		add_filter( 'seopress_titles_canonical', array( __CLASS__, 'canonical_tag' ), 20 );
	}

	/**
	 * Works out which item the request shows; unknown items answer 404.
	 */
	public static function setup() {
		self::$ready = true;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only navigation.
		$requested = isset( $_GET[ Renderer::QUERY_ARG ] ) ? absint( $_GET[ Renderer::QUERY_ARG ] ) : 0;
		if ( ! $requested || ! is_singular() || Search::is_active() ) {
			return;
		}
		$post       = get_queried_object();
		$project_id = $post instanceof \WP_Post ? self::host_project( $post ) : -1;
		if ( $project_id < 0 ) {
			return;
		}
		$project = $project_id ? Nodes::get( $project_id ) : null;
		if ( $project_id && ( ! $project || 'project' !== $project->level ) ) {
			return;
		}
		self::$host    = $post;
		self::$project = $project;

		$node = Nodes::get( $requested );
		if ( ! $node || ( $project && (int) $node->project_id !== (int) $project->id ) ) {
			// The page still shows the project (or the catalog) for visitors; search engines
			// learn that the address is gone.
			self::$missing = true;
			status_header( 404 );
			nocache_headers();
			return;
		}
		$current = Renderer::resolve( $project, $requested );
		// The project of a single-project page is the page itself.
		self::$current = ( $current && ! ( $project && (int) $current->id === (int) $project->id ) ) ? $current : null;
	}

	/**
	 * Project id of the selector on a page: 0 for the catalog of all projects, -1 when the page
	 * has no selector.
	 *
	 * @param \WP_Post $post Page.
	 * @return int
	 */
	public static function host_project( $post ) {
		$content = (string) $post->post_content;
		if ( has_shortcode( $content, 'solo_estate' ) && preg_match_all( '/' . get_shortcode_regex( array( 'solo_estate' ) ) . '/', $content, $found, PREG_SET_ORDER ) ) {
			$atts = shortcode_parse_atts( $found[0][3] );
			return is_array( $atts ) && isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
		}
		if ( has_block( 'solo-estate/project', $post ) ) {
			$block = self::find_block( parse_blocks( $content ) );
			return $block && isset( $block['attrs']['projectId'] ) ? absint( $block['attrs']['projectId'] ) : 0;
		}
		// Elementor keeps its content in post meta.
		$builder = (string) get_post_meta( $post->ID, '_elementor_data', true );
		if ( preg_match( '/\[solo_estate(?=[\s\]\\\\])([^\]]*)\]/', $builder, $match ) ) {
			return preg_match( '/id=\\\\?["\']?(\d+)/', $match[1], $id ) ? absint( $id[1] ) : 0;
		}
		/** This filter is documented in includes/frontend/class-shortcode.php */
		return apply_filters( 'solo_estate_enqueue_assets', false, $post ) ? 0 : -1;
	}

	/**
	 * First Solo Estate block in a block tree.
	 *
	 * @param array $blocks Blocks.
	 * @return array|null
	 */
	private static function find_block( array $blocks ) {
		foreach ( $blocks as $block ) {
			if ( 'solo-estate/project' === $block['blockName'] ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner = self::find_block( $block['innerBlocks'] );
				if ( $inner ) {
					return $inner;
				}
			}
		}
		return null;
	}

	/**
	 * Whether the requested item does not exist (the page answers 404).
	 *
	 * @return bool
	 */
	public static function is_missing() {
		return self::$missing;
	}

	/**
	 * Item shown on this request (null when the page shows itself).
	 *
	 * @return object|null
	 */
	public static function current() {
		if ( ! self::$ready ) {
			self::setup();
		}
		return self::$current;
	}

	/**
	 * Address of an item on the host page: the page's own permalink plus the item argument.
	 *
	 * @param object|null $node Node (null: the page).
	 * @return string
	 */
	public static function url( $node ) {
		$host    = self::$host;
		$project = self::$project ? (int) self::$project->id : 0;
		// On the catalog, items of a project that has a page of its own point there.
		if ( $node && ! $project ) {
			$home = Sitemap::home_of( (int) ( $node->project_id ? $node->project_id : $node->id ), Sitemap::language( self::$host->ID ) );
			if ( $home ) {
				$host    = get_post( $home );
				$project = (int) ( $node->project_id ? $node->project_id : $node->id );
			}
		}
		$base = get_permalink( $host );
		if ( ! $node || ( $project && (int) $node->id === $project ) ) {
			return $base;
		}
		return add_query_arg( Renderer::QUERY_ARG, (int) $node->id, $base );
	}

	/**
	 * Items from the project (or the catalog) down to the current one, without the levels the
	 * selector skips.
	 *
	 * @return object[]
	 */
	private static function chain() {
		$chain = Nodes::ancestors( self::$current );
		$last  = count( $chain ) - 1;
		$out   = array();
		foreach ( $chain as $i => $item ) {
			if ( $i > 0 && $i < $last && Renderer::skips( $item ) ) {
				continue;
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Title of the item: "Apartment 12 — Floor 6 — Building 6 — Project".
	 *
	 * @param mixed $title Title from WordPress or an SEO plugin (kept on other pages).
	 * @return mixed
	 */
	public static function title( $title = '' ) {
		if ( ! self::current() ) {
			return $title;
		}
		$names = array();
		foreach ( array_reverse( self::chain() ) as $item ) {
			$names[] = Nodes::display_title( $item );
		}
		/**
		 * Title of a Solo Estate item page (without the site name).
		 *
		 * @param string $title Title.
		 * @param object $node  Item.
		 */
		return (string) apply_filters( 'solo_estate_seo_title', implode( ' — ', array_filter( $names, 'strlen' ) ), self::$current );
	}

	/**
	 * Title with the site name, for SEO plugins that take the whole title.
	 *
	 * @param mixed $title Title.
	 * @return mixed
	 */
	public static function full_title( $title ) {
		if ( ! self::current() ) {
			return $title;
		}
		/** This filter is documented in wp-includes/general-template.php */
		$sep = apply_filters( 'document_title_separator', '-' );
		return self::title() . ' ' . $sep . ' ' . get_bloginfo( 'name', 'display' );
	}

	/**
	 * Core document title.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public static function title_parts( $parts ) {
		if ( self::current() && is_array( $parts ) ) {
			$parts['title'] = self::title();
		}
		return $parts;
	}

	/**
	 * Description: the item's own text, otherwise its facts (status, area, price, counts).
	 *
	 * @param mixed $description Description from an SEO plugin (kept on other pages).
	 * @return mixed
	 */
	public static function description( $description = '' ) {
		$node = self::current();
		if ( ! $node ) {
			return $description;
		}
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( Nodes::field( $node, 'description' ) ) ) ) );
		if ( '' === $text ) {
			$text  = Renderer::shape_label( $node, Statuses::of( $node->status_id ) );
			$names = array();
			foreach ( array_reverse( array_slice( self::chain(), 0, -1 ) ) as $item ) {
				$names[] = Nodes::display_title( $item );
			}
			if ( $names ) {
				$text .= '. ' . implode( ', ', $names ) . '.';
			}
		}
		/**
		 * Description of a Solo Estate item page.
		 *
		 * @param string $text Description.
		 * @param object $node Item.
		 */
		return (string) apply_filters( 'solo_estate_seo_description', wp_html_excerpt( $text, 160, '…' ), $node );
	}

	/**
	 * Canonical address for SEO plugins.
	 *
	 * @param mixed $url URL.
	 * @return mixed
	 */
	public static function canonical( $url ) {
		return self::current() ? self::url( self::$current ) : $url;
	}

	/**
	 * Canonical link tag (SEOPress filters the whole tag).
	 *
	 * @param mixed $html Tag.
	 * @return mixed
	 */
	public static function canonical_tag( $html ) {
		return self::current() ? '<link rel="canonical" href="' . esc_url( self::url( self::$current ) ) . '" />' : $html;
	}

	/**
	 * Core canonical URL.
	 *
	 * @param string   $url  URL.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function canonical_core( $url, $post = null ) {
		if ( self::current() && $post && self::$host && (int) $post->ID === (int) self::$host->ID ) {
			return self::url( self::$current );
		}
		return $url;
	}

	/**
	 * Image for social previews: the item's image, plan or first photo.
	 *
	 * @return int Attachment id (0: none).
	 */
	private static function image_id() {
		$node = self::current();
		if ( ! $node ) {
			return 0;
		}
		foreach ( array( $node->image_id, $node->image2_id ) as $id ) {
			if ( $id ) {
				return (int) $id;
			}
		}
		$gallery = Nodes::gallery( $node );
		return $gallery ? (int) $gallery[0] : 0;
	}

	/**
	 * Yoast SEO: the item's image in the social preview.
	 *
	 * @param object $images Yoast image container.
	 */
	public static function yoast_image( $images ) {
		$id = self::image_id();
		if ( $id && is_object( $images ) && method_exists( $images, 'add_image_by_id' ) ) {
			$images->add_image_by_id( $id );
		}
	}

	/**
	 * noindex for addresses of items that do not exist.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( ! self::$ready ) {
			return $robots;
		}
		if ( self::$missing ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	/**
	 * Yoast SEO robots.
	 *
	 * @param mixed $robots Robots string.
	 * @return mixed
	 */
	public static function robots_string( $robots ) {
		return self::$missing ? 'noindex, follow' : $robots;
	}

	/**
	 * Rank Math robots.
	 *
	 * @param mixed $robots Robots directives.
	 * @return mixed
	 */
	public static function robots_array( $robots ) {
		if ( self::$missing && is_array( $robots ) ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	/**
	 * Whether an SEO plugin prints the description and social tags.
	 *
	 * @return bool
	 */
	private static function seo_plugin() {
		$active = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
		/**
		 * Whether an SEO plugin prints the meta description and Open Graph tags. When false,
		 * Solo Estate prints them on its item pages.
		 *
		 * @param bool $active Detected.
		 */
		return (bool) apply_filters( 'solo_estate_seo_plugin_active', $active );
	}

	/**
	 * Meta description and Open Graph tags (without an SEO plugin) and structured data.
	 */
	public static function head() {
		$node = self::current();
		if ( ! $node ) {
			return;
		}
		if ( ! self::seo_plugin() ) {
			$tags = array(
				'description'    => array( 'name', self::description() ),
				'og:type'        => array( 'property', 'website' ),
				'og:title'       => array( 'property', self::title() ),
				'og:description' => array( 'property', self::description() ),
				'og:url'         => array( 'property', self::url( $node ) ),
				'og:site_name'   => array( 'property', get_bloginfo( 'name', 'display' ) ),
			);
			$image = self::image_id() ? wp_get_attachment_image_src( self::image_id(), 'large' ) : false;
			if ( $image ) {
				$tags['og:image']        = array( 'property', $image[0] );
				$tags['og:image:width']  = array( 'property', (string) $image[1] );
				$tags['og:image:height'] = array( 'property', (string) $image[2] );
				$tags['twitter:card']    = array( 'name', 'summary_large_image' );
			}
			foreach ( $tags as $key => $tag ) {
				if ( '' !== $tag[1] ) {
					printf( '<meta %1$s="%2$s" content="%3$s" />' . "\n", esc_attr( $tag[0] ), esc_attr( $key ), 'og:url' === $key || 'og:image' === $key ? esc_url( $tag[1] ) : esc_attr( $tag[1] ) );
				}
			}
		}
		$data = self::structured_data();
		if ( $data ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
		}
	}

	/**
	 * Structured data: breadcrumbs, and the apartment (or villa) itself.
	 *
	 * @return array|null
	 */
	public static function structured_data() {
		$node = self::current();
		if ( ! $node ) {
			return null;
		}
		$items    = array();
		$position = 1;
		if ( ! self::$project ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => get_the_title( self::$host ),
				'item'     => get_permalink( self::$host ),
			);
		}
		foreach ( self::chain() as $item ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => Nodes::display_title( $item ),
				'item'     => self::url( $item ),
			);
		}
		$graph = array(
			array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			),
		);

		$types = array(
			'flat'       => 'Apartment',
			'villa'      => 'SingleFamilyResidence',
			'commercial' => 'Place',
		);
		if ( isset( $types[ $node->level ] ) ) {
			$unit = array(
				'@type' => $types[ $node->level ],
				'@id'   => self::url( $node ) . '#unit',
				'name'  => Nodes::display_title( $node ),
				'url'   => self::url( $node ),
			);
			if ( $node->area ) {
				$unit['floorSize'] = array(
					'@type'    => 'QuantitativeValue',
					'value'    => (float) $node->area,
					'unitCode' => 'MTK',
				);
			}
			if ( null !== $node->rooms && 'commercial' !== $node->level ) {
				$unit['numberOfRooms'] = (int) $node->rooms;
			}
			$image = self::image_id() ? wp_get_attachment_image_url( self::image_id(), 'large' ) : '';
			if ( $image ) {
				$unit['image'] = $image;
			}
			$description = self::description();
			if ( '' !== $description ) {
				$unit['description'] = $description;
			}
			$building = Nodes::ancestor( $node, 'building' );
			if ( $building ) {
				$unit['containedInPlace'] = array(
					'@type' => 'Residence',
					'name'  => Nodes::display_title( $building ),
					'url'   => self::url( $building ),
				);
			}
			$graph[] = $unit;
		}
		/**
		 * Structured data (JSON-LD @graph) of a Solo Estate item page; return an empty array to
		 * print none.
		 *
		 * @param array  $graph Items.
		 * @param object $node  Item.
		 */
		$graph = (array) apply_filters( 'solo_estate_structured_data', $graph, $node );
		return $graph ? array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		) : null;
	}
}
