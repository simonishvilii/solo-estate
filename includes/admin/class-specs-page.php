<?php
/**
 * Specification fields editor (Living room, Bedroom 1, Terrace, …).
 *
 * @package SoloEstate\Admin
 */

namespace SoloEstate\Admin;

use SoloEstate\I18n;
use SoloEstate\Specs;

defined( 'ABSPATH' ) || exit;

class Specs_Page {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_post_solo_estate_save_specs', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_solo_estate_delete_spec', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Screen.
	 */
	public static function render() {
		$languages = I18n::languages();
		$fields    = array_values( Specs::fields() );
		$fields[]  = null;

		echo '<div class="wrap solo-estate-wrap"><h1>' . esc_html__( 'Specification fields', 'solo-estate' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Rooms and areas listed on the apartment page. Values are entered on each apartment. Drag rows to reorder. "Highlight" makes the row stand out (e.g. living area).', 'solo-estate' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'solo_estate_save_specs' );
		echo '<input type="hidden" name="action" value="solo_estate_save_specs">';
		echo '<table class="widefat striped solo-estate-table solo-estate-specs-table"><thead><tr><th class="solo-estate-handle-col"></th>';
		foreach ( $languages as $lang ) {
			echo '<th>' . esc_html( $lang['name'] ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Unit', 'solo-estate' ) . '</th><th>' . esc_html__( 'Highlight', 'solo-estate' ) . '</th><th></th></tr></thead><tbody data-solo-estate-sortable>';

		foreach ( $fields as $field ) {
			$key = $field ? (string) $field->id : 'new';
			echo '<tr' . ( $field ? '' : ' class="solo-estate-new-row"' ) . '>';
			echo '<td class="solo-estate-handle"><span class="dashicons dashicons-menu"></span>';
			printf( '<input type="hidden" name="specs[%1$s][sort_order]" value="%2$d" data-solo-estate-sort></td>', esc_attr( $key ), $field ? (int) $field->sort_order : 999 );
			foreach ( $languages as $code => $lang ) {
				printf(
					'<td><input type="text" name="specs[%1$s][title][%2$s]" value="%3$s" placeholder="%4$s"></td>',
					esc_attr( $key ),
					esc_attr( $code ),
					esc_attr( $field && isset( $field->i18n['title'][ $code ] ) ? $field->i18n['title'][ $code ] : '' ),
					esc_attr( $field ? '' : __( 'New field…', 'solo-estate' ) )
				);
			}
			printf( '<td><input type="text" class="small-text" name="specs[%1$s][unit]" value="%2$s" placeholder="%3$s"></td>', esc_attr( $key ), esc_attr( $field ? $field->unit : '' ), esc_attr( \SoloEstate\Settings::get( 'area_unit' ) ) );
			printf( '<td><input type="checkbox" name="specs[%1$s][highlight]" value="1"%2$s></td>', esc_attr( $key ), checked( $field ? $field->highlight : 0, 1, false ) );
			echo '<td>';
			if ( $field ) {
				printf(
					'<a class="solo-estate-delete" data-solo-estate-confirm href="%1$s">%2$s</a>',
					esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'solo_estate_delete_spec', 'id' => $field->id ), admin_url( 'admin-post.php' ) ), 'solo_estate_delete_spec_' . $field->id ) ),
					esc_html__( 'Delete', 'solo-estate' )
				);
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Save fields', 'solo-estate' ) );
		echo '</form></div>';
	}

	/**
	 * Saves all fields.
	 */
	public static function handle_save() {
		Admin::check( 'solo_estate_save_specs' );

		$input = isset( $_POST['specs'] ) && is_array( $_POST['specs'] ) ? wp_unslash( $_POST['specs'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		foreach ( $input as $key => $row ) {
			$title = Ui::read_i18n( isset( $row['title'] ) ? wp_slash( $row['title'] ) : array() );
			$data  = array(
				'sort_order' => isset( $row['sort_order'] ) ? intval( $row['sort_order'] ) : 0,
				'highlight'  => empty( $row['highlight'] ) ? 0 : 1,
				'unit'       => isset( $row['unit'] ) ? mb_substr( sanitize_text_field( $row['unit'] ), 0, 20 ) : '',
				'i18n'       => array( 'title' => $title ),
			);
			if ( 'new' === $key ) {
				if ( $title ) {
					Specs::save_field( $data );
				}
			} elseif ( isset( Specs::fields()[ absint( $key ) ] ) ) {
				Specs::save_field( $data, absint( $key ) );
			}
		}
		Admin::redirect( Admin::url( 'solo-estate-specs' ) );
	}

	/**
	 * Deletes a field and its values.
	 */
	public static function handle_delete() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		Admin::check( 'solo_estate_delete_spec_' . $id );
		Specs::delete_field( $id );
		Admin::redirect( Admin::url( 'solo-estate-specs' ), 'deleted' );
	}
}
