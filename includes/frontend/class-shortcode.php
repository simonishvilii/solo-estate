<?php
/**
 * [solo_estate] and [solo_estate_lead_form] shortcodes.
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

defined( 'ABSPATH' ) || exit;

class Shortcode {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'add' ) );
		add_action( 'wp_enqueue_scripts', array( Assets::class, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
	}

	/**
	 * Registers shortcodes.
	 */
	public static function add() {
		add_shortcode( 'solo_estate', array( __CLASS__, 'project' ) );
		add_shortcode( 'solo_estate_lead_form', array( __CLASS__, 'lead_form' ) );
		// Search results are endless filter combinations: not for search engines.
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}

	/**
	 * noindex on apartment search results.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( Search::is_active() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Enqueues assets in <head> when the current post uses the shortcode or block,
	 * so styles don't flash. Rendering enqueues them anyway as a fallback (page builders, widgets).
	 */
	public static function maybe_enqueue() {
		$post = is_singular() ? get_post() : null;
		$uses = $post && ( has_shortcode( $post->post_content, 'solo_estate' ) || has_shortcode( $post->post_content, 'solo_estate_lead_form' ) || has_block( 'solo-estate/project', $post ) );
		// Elementor keeps its content in post meta, not in post_content.
		if ( $post && ! $uses ) {
			$builder = (string) get_post_meta( $post->ID, '_elementor_data', true );
			$uses    = false !== strpos( $builder, 'solo_estate' );
		}
		/**
		 * Whether this page needs Solo Estate's styles and script in <head>. Return true for pages
		 * whose selector comes from a template or widget the check above cannot see.
		 *
		 * @param bool          $uses Detected.
		 * @param \WP_Post|null $post Current post.
		 */
		if ( apply_filters( 'solo_estate_enqueue_assets', $uses, $post ) ) {
			Assets::enqueue();
		}
	}

	/**
	 * [solo_estate id="1"].
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function project( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'title' => 'yes',
			),
			$atts,
			'solo_estate'
		);
		Assets::enqueue();
		return Assets::late_styles() . Renderer::project( absint( $atts['id'] ), ! in_array( strtolower( (string) $atts['title'] ), array( 'no', '0', 'false' ), true ) );
	}

	/**
	 * [solo_estate_lead_form node="123"].
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function lead_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'node' => 0,
			),
			$atts,
			'solo_estate_lead_form'
		);
		Assets::enqueue();
		return Assets::late_styles() . '<div class="solo-estate-app">' . Renderer::template( 'lead-form', array( 'node' => \SoloEstate\Nodes::get( absint( $atts['node'] ) ) ) ) . '</div>';
	}
}
