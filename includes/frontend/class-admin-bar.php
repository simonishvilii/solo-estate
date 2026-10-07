<?php
/**
 * Toolbar on the site: on a building, floor, apartment… the "Edit page" item edits that item
 * in Solo Estate instead (the page itself stays one click away, as a sub-item).
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

use SoloEstate\Install;
use SoloEstate\Nodes;

defined( 'ABSPATH' ) || exit;

/**
 * Admin bar edit link for the item on screen.
 */
class Admin_Bar {

	/**
	 * Hooks.
	 */
	public static function register() {
		// After WordPress adds its "Edit page" item (priority 80).
		add_action( 'admin_bar_menu', array( __CLASS__, 'menu' ), 90 );
	}

	/**
	 * Replaces "Edit page" with "Edit building" (etc.) for the item in the URL.
	 *
	 * @param \WP_Admin_Bar $bar Toolbar.
	 */
	public static function menu( $bar ) {
		if ( is_admin() || ! ( current_user_can( Install::CAP_EDIT ) || current_user_can( Install::CAP_SELL ) || current_user_can( Install::CAPABILITY ) ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state.
		$id   = isset( $_GET[ Renderer::QUERY_ARG ] ) ? absint( $_GET[ Renderer::QUERY_ARG ] ) : 0;
		$node = $id ? Nodes::get( $id ) : null;
		if ( ! $node ) {
			return;
		}
		$labels = array(
			'project'     => __( 'Edit project', 'solo-estate' ),
			'phase'       => __( 'Edit phase', 'solo-estate' ),
			'building'    => __( 'Edit building', 'solo-estate' ),
			'floor'       => __( 'Edit floor', 'solo-estate' ),
			'parking'     => __( 'Edit parking', 'solo-estate' ),
			'flat'        => __( 'Edit apartment', 'solo-estate' ),
			'commercial'  => __( 'Edit commercial space', 'solo-estate' ),
			'villa'       => __( 'Edit villa', 'solo-estate' ),
			'villa_floor' => __( 'Edit villa floor', 'solo-estate' ),
			'spot'        => __( 'Edit parking space', 'solo-estate' ),
		);
		$page = $bar->get_node( 'edit' );
		$bar->add_node(
			array(
				'id'    => 'edit',
				'title' => esc_html( isset( $labels[ $node->level ] ) ? $labels[ $node->level ] : __( 'Edit', 'solo-estate' ) ),
				'href'  => add_query_arg(
					array(
						'page' => 'solo-estate',
						'node' => $node->id,
					),
					admin_url( 'admin.php' )
				),
				'meta'  => array( 'title' => Nodes::admin_title( $node ) ),
			)
		);
		if ( $page && $page->href ) {
			$bar->add_node(
				array(
					'parent' => 'edit',
					'id'     => 'solo-estate-edit-page',
					'title'  => esc_html__( 'Edit page', 'solo-estate' ),
					'href'   => $page->href,
				)
			);
		}
	}
}
