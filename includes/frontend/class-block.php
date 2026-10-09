<?php
/**
 * "Solo Estate project" Gutenberg block (server-rendered, no build step).
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

use SoloEstate\Nodes;

defined( 'ABSPATH' ) || exit;

class Block {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'add' ) );
	}

	/**
	 * Registers the block and its editor script.
	 */
	public static function add() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		Assets::register();
		wp_register_script(
			'solo-estate-block',
			SOLO_ESTATE_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			\SoloEstate\Plugin::asset_version( 'assets/js/block.js' ),
			true
		);
		wp_set_script_translations( 'solo-estate-block', 'solo-estate', SOLO_ESTATE_DIR . 'languages' );
		register_block_type(
			'solo-estate/project',
			array(
				'api_version'     => 2,
				'editor_script'   => 'solo-estate-block',
				'editor_style'    => 'solo-estate',
				'attributes'      => array(
					'projectId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/**
	 * Project list for the block's dropdown.
	 */
	public static function editor_data() {
		if ( ! wp_style_is( 'solo-estate', 'registered' ) ) {
			Assets::register();
		}
		$projects = array();
		foreach ( Nodes::projects() as $project ) {
			$projects[] = array(
				'value' => $project->id,
				'label' => Nodes::admin_title( $project ),
			);
		}
		wp_localize_script( 'solo-estate-block', 'soloEstateBlock', array( 'projects' => $projects ) );
	}

	/**
	 * Server render.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		Assets::enqueue();
		return Assets::late_styles() . Renderer::project( isset( $attributes['projectId'] ) ? absint( $attributes['projectId'] ) : 0 );
	}
}
