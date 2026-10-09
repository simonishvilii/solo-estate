<?php
/**
 * Statuses editor: names per language, colour, clickability, "available" flag.
 *
 * @package SoloEstate\Admin
 */

namespace SoloEstate\Admin;

use SoloEstate\I18n;
use SoloEstate\Statuses;

defined( 'ABSPATH' ) || exit;

class Statuses_Page {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_post_solo_estate_save_statuses', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_solo_estate_delete_status', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Screen.
	 */
	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only shows a confirmation form.
		$delete = isset( $_GET['delete'] ) ? Statuses::get( absint( $_GET['delete'] ) ) : null;
		if ( $delete && Statuses::usage( $delete->id ) ) {
			self::render_delete( $delete );
			return;
		}
		$languages = I18n::languages();

		echo '<div class="wrap solo-estate-wrap"><h1>' . esc_html__( 'Statuses', 'solo-estate' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Colours are used for polygons and labels on the site. "Clickable" lets visitors open the item (turn it off for Sold, Reserved and Rented). "Counts as available" is used for the free-unit counters, "Counts as sold" for the sold percentages of projects, phases and buildings.', 'solo-estate' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Projects: when every apartment of a project is sold, the projects page shows it under the status marked "Sold-out projects move here" (e.g. Completed), whatever status is set on it.', 'solo-estate' ) . '</p>';
		$headings = array(
			'flat'     => __( 'Apartments, commercial spaces, villas and parking spaces', 'solo-estate' ),
			'floor'    => __( 'Floors and parkings', 'solo-estate' ),
			'building' => __( 'Phases and buildings', 'solo-estate' ),
			'project'  => __( 'Projects', 'solo-estate' ),
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'solo_estate_save_statuses' );
		echo '<input type="hidden" name="action" value="solo_estate_save_statuses">';

		foreach ( array_reverse( Statuses::SCOPES ) as $scope ) {
			echo '<h2>' . esc_html( $headings[ $scope ] ) . '</h2>';
			echo '<table class="widefat striped solo-estate-table solo-estate-status-table"><thead><tr>';
			foreach ( $languages as $lang ) {
				echo '<th>' . esc_html( $lang['name'] ) . '</th>';
			}
			echo '<th>' . esc_html__( 'Colour', 'solo-estate' ) . '</th><th>' . esc_html__( 'Clickable', 'solo-estate' ) . '</th><th>' . esc_html__( 'Counts as available', 'solo-estate' ) . '</th>' . ( 'flat' === $scope ? '<th>' . esc_html__( 'Counts as sold', 'solo-estate' ) . '</th>' : '' ) . ( 'project' === $scope ? '<th>' . esc_html__( 'Sold-out projects move here', 'solo-estate' ) . '</th>' : '' ) . '<th>' . esc_html__( 'Order', 'solo-estate' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'solo-estate' ) . '</span></th></tr></thead><tbody>';

			$rows   = Statuses::for_scope( $scope );
			$rows[] = null; // Empty row to add a new status.
			foreach ( $rows as $i => $status ) {
				$key = $status ? (string) $status->id : 'new_' . $scope;
				echo '<tr' . ( $status ? '' : ' class="solo-estate-new-row"' ) . '>';
				// Each field is named after its row and column for screen readers.
				$name  = $status ? Statuses::title( $status ) : __( 'New status', 'solo-estate' );
				$label = static function ( $column ) use ( $name ) {
					/* translators: 1: status name, 2: column */
					return esc_attr( sprintf( __( '%1$s: %2$s', 'solo-estate' ), $name, $column ) );
				};
				foreach ( $languages as $code => $lang ) {
					printf(
						'<td><input type="text" name="statuses[%1$s][title][%2$s]" value="%3$s" placeholder="%4$s" aria-label="%5$s"></td>',
						esc_attr( $key ),
						esc_attr( $code ),
						esc_attr( $status && isset( $status->i18n['title'][ $code ] ) ? $status->i18n['title'][ $code ] : '' ),
						esc_attr( $status ? '' : __( 'New status…', 'solo-estate' ) ),
						$label( $lang['name'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $label.
					);
				}
				printf( '<td><input type="text" class="solo-estate-color" name="statuses[%1$s][color]" value="%2$s" aria-label="%3$s"></td>', esc_attr( $key ), esc_attr( $status ? $status->color : '#707d76' ), $label( __( 'Colour', 'solo-estate' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				printf( '<td><input type="checkbox" name="statuses[%1$s][clickable]" value="1"%2$s aria-label="%3$s"></td>', esc_attr( $key ), checked( $status ? $status->clickable : 1, 1, false ), $label( __( 'Clickable', 'solo-estate' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				printf( '<td><input type="checkbox" name="statuses[%1$s][available]" value="1"%2$s aria-label="%3$s"></td>', esc_attr( $key ), checked( $status ? $status->available : 0, 1, false ), $label( __( 'Counts as available', 'solo-estate' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( 'flat' === $scope || 'project' === $scope ) {
					printf( '<td><input type="checkbox" name="statuses[%1$s][sold]" value="1"%2$s aria-label="%3$s"></td>', esc_attr( $key ), checked( $status ? $status->sold : 0, 1, false ), $label( 'flat' === $scope ? __( 'Counts as sold', 'solo-estate' ) : __( 'Sold-out projects move here', 'solo-estate' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				printf( '<td><input type="number" class="small-text" name="statuses[%1$s][sort_order]" value="%2$d" aria-label="%3$s"></td>', esc_attr( $key ), $status ? (int) $status->sort_order : count( $rows ), $label( __( 'Order', 'solo-estate' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				printf( '<input type="hidden" name="statuses[%1$s][scope]" value="%2$s">', esc_attr( $key ), esc_attr( $scope ) );
				echo '<td>';
				if ( $status && Statuses::usage( $status->id ) ) {
					// In use: a confirmation screen asks where its items go.
					printf(
						'<a class="solo-estate-delete" href="%1$s">%2$s</a> <span class="description">%3$s</span>',
						esc_url( Admin::url( 'solo-estate-statuses', array( 'delete' => $status->id ) ) ),
						esc_html__( 'Delete', 'solo-estate' ),
						/* translators: %d: number of items */
						esc_html( sprintf( _n( '%d item', '%d items', Statuses::usage( $status->id ), 'solo-estate' ), Statuses::usage( $status->id ) ) )
					);
				} elseif ( $status ) {
					printf(
						'<a class="solo-estate-delete" data-solo-estate-confirm href="%1$s">%2$s</a>',
						esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'solo_estate_delete_status', 'id' => $status->id ), admin_url( 'admin-post.php' ) ), 'solo_estate_delete_status_' . $status->id ) ),
						esc_html__( 'Delete', 'solo-estate' )
					);
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		submit_button( __( 'Save statuses', 'solo-estate' ) );
		echo '</form></div>';
	}

	/**
	 * Saves all statuses.
	 */
	public static function handle_save() {
		Admin::check( 'solo_estate_save_statuses' );

		$input = isset( $_POST['statuses'] ) && is_array( $_POST['statuses'] ) ? wp_unslash( $_POST['statuses'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.

		foreach ( $input as $key => $row ) {
			$scope = isset( $row['scope'] ) && in_array( $row['scope'], Statuses::SCOPES, true ) ? $row['scope'] : '';
			$title = Ui::read_i18n( isset( $row['title'] ) ? wp_slash( $row['title'] ) : array() );
			$is_new = 0 === strpos( (string) $key, 'new_' );
			if ( '' === $scope || ( $is_new && ! $title ) ) {
				continue;
			}
			$color = sanitize_hex_color( isset( $row['color'] ) ? $row['color'] : '' );
			$data  = array(
				'scope'      => $scope,
				'color'      => $color ? $color : '#707d76',
				'clickable'  => empty( $row['clickable'] ) ? 0 : 1,
				'available'  => empty( $row['available'] ) ? 0 : 1,
				'sold'       => ( in_array( $scope, array( 'flat', 'project' ), true ) && ! empty( $row['sold'] ) ) ? 1 : 0,
				'sort_order' => isset( $row['sort_order'] ) ? intval( $row['sort_order'] ) : 0,
				'i18n'       => array( 'title' => $title ),
			);
			if ( $is_new ) {
				Statuses::save( $data );
			} elseif ( Statuses::get( absint( $key ) ) ) {
				Statuses::save( $data, absint( $key ) );
			}
		}
		Admin::redirect( Admin::url( 'solo-estate-statuses' ) );
	}

	/**
	 * Deletes a status.
	 */
	public static function handle_delete() {
		// phpcs:disable WordPress.Security.NonceVerification -- checked by Admin::check().
		$id          = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		$replacement = isset( $_REQUEST['replacement'] ) ? absint( $_REQUEST['replacement'] ) : 0;
		// phpcs:enable
		Admin::check( 'solo_estate_delete_status_' . $id );
		if ( ! Statuses::delete( $id, $replacement ) ) {
			Admin::redirect( Admin::url( 'solo-estate-statuses', array( 'delete' => $id ) ), 'status_in_use' );
		}
		Admin::redirect( Admin::url( 'solo-estate-statuses' ), 'deleted' );
	}

	/**
	 * Deleting a status that items have: choose the status they get instead.
	 *
	 * @param object $status Status.
	 */
	private static function render_delete( $status ) {
		$count   = Statuses::usage( $status->id );
		$options = array_filter(
			Statuses::for_scope( $status->scope ),
			static function ( $other ) use ( $status ) {
				return $other->id !== $status->id;
			}
		);
		echo '<div class="wrap solo-estate-wrap"><h1>' . esc_html( sprintf( /* translators: %s: status name */ __( 'Delete status "%s"', 'solo-estate' ), Statuses::title( $status ) ) ) . '</h1>';
		/* translators: %d: number of items */
		echo '<p>' . esc_html( sprintf( _n( '%d item has this status. Choose the status it gets instead; then this status is deleted.', '%d items have this status. Choose the status they get instead; then this status is deleted.', $count, 'solo-estate' ), $count ) ) . '</p>';
		if ( ! $options ) {
			echo '<p class="solo-estate-warn">' . esc_html__( 'There is no other status in this list. Add one first.', 'solo-estate' ) . '</p>';
			printf( '<p><a class="button" href="%1$s">%2$s</a></p></div>', esc_url( Admin::url( 'solo-estate-statuses' ) ), esc_html__( 'Back', 'solo-estate' ) );
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'solo_estate_delete_status_' . $status->id );
		printf( '<input type="hidden" name="action" value="solo_estate_delete_status"><input type="hidden" name="id" value="%d">', (int) $status->id );
		echo '<p><label>' . esc_html__( 'Move them to', 'solo-estate' ) . ' <select name="replacement" required><option value="">—</option>';
		foreach ( $options as $other ) {
			printf( '<option value="%1$d">%2$s</option>', (int) $other->id, esc_html( Statuses::title( $other ) ) );
		}
		echo '</select></label></p>';
		submit_button( __( 'Move items and delete status', 'solo-estate' ), 'delete' );
		printf( '<p><a href="%1$s">%2$s</a></p></form></div>', esc_url( Admin::url( 'solo-estate-statuses' ) ), esc_html__( 'Cancel', 'solo-estate' ) );
	}
}
