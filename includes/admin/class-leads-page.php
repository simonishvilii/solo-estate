<?php
/**
 * Leads list, status, CSV export.
 *
 * @package SoloEstate\Admin
 */

namespace SoloEstate\Admin;

use SoloEstate\Install;
use SoloEstate\Leads;
use SoloEstate\Nodes;

defined( 'ABSPATH' ) || exit;

class Leads_Page {

	const PER_PAGE = 30;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_post_solo_estate_leads_bulk', array( __CLASS__, 'handle_bulk' ) );
		add_action( 'admin_post_solo_estate_leads_export', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Count bubble of new leads for the menu.
	 *
	 * @return string
	 */
	public static function menu_badge() {
		global $wpdb;
		$table = Install::table( 'leads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'new'" );
		return $count ? ' <span class="awaiting-mod">' . (int) $count . '</span>' : '';
	}

	/**
	 * Screen.
	 */
	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:enable
		$result = Leads::query( self::PER_PAGE, $page, $search );
		$pages  = (int) ceil( $result['total'] / self::PER_PAGE );

		echo '<div class="wrap solo-estate-wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Leads', 'solo-estate' ) . '</h1>';
		// Exporting every lead at once is for managers only.
		if ( Admin::can_manage() ) {
			printf(
				' <a class="page-title-action" href="%1$s">%2$s</a>',
				esc_url( wp_nonce_url( add_query_arg( 'action', 'solo_estate_leads_export', admin_url( 'admin-post.php' ) ), 'solo_estate_leads_export' ) ),
				esc_html__( 'Export CSV', 'solo-estate' )
			);
		}
		echo '<hr class="wp-header-end">';

		echo '<form method="get" class="search-form"><input type="hidden" name="page" value="solo-estate-leads">';
		printf( '<p class="search-box"><input type="search" name="s" value="%1$s"> <input type="submit" class="button" value="%2$s"></p></form>', esc_attr( $search ), esc_attr__( 'Search', 'solo-estate' ) );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'solo_estate_leads_bulk' );
		echo '<input type="hidden" name="action" value="solo_estate_leads_bulk">';
		echo '<div class="tablenav top"><div class="alignleft actions bulkactions"><select name="bulk"><option value="">' . esc_html__( 'Bulk actions', 'solo-estate' ) . '</option>';
		foreach ( Leads::STATUSES as $status ) {
			/* translators: %s: lead status */
			printf( '<option value="status:%1$s">%2$s</option>', esc_attr( $status ), esc_html( sprintf( __( 'Mark as: %s', 'solo-estate' ), Leads::status_label( $status ) ) ) );
		}
		if ( Admin::can_manage() ) {
			echo '<option value="delete">' . esc_html__( 'Delete', 'solo-estate' ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Apply', 'solo-estate' ), 'action', 'apply', false, array( 'data-solo-estate-bulk-confirm' => '1' ) );
		echo '</div>';
		/* translators: %s: number of leads */
		echo '<div class="tablenav-pages"><span class="displaying-num">' . esc_html( sprintf( _n( '%s item', '%s items', $result['total'], 'solo-estate' ), number_format_i18n( $result['total'] ) ) ) . '</span>';
		if ( $pages > 1 ) {
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $page,
						'total'   => $pages,
					)
				)
			);
		}
		echo '</div></div>';

		echo '<table class="wp-list-table widefat fixed striped solo-estate-table"><thead><tr>';
		echo '<td class="manage-column check-column"><input type="checkbox" data-solo-estate-check-all></td>';
		foreach ( array( __( 'Date', 'solo-estate' ), __( 'Name', 'solo-estate' ), __( 'Phone', 'solo-estate' ), __( 'Apartment', 'solo-estate' ), __( 'Language', 'solo-estate' ), __( 'Status', 'solo-estate' ) ) as $col ) {
			echo '<th>' . esc_html( $col ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( ! $result['rows'] ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No leads yet.', 'solo-estate' ) . '</td></tr>';
		}
		foreach ( $result['rows'] as $lead ) {
			$node = Nodes::get( $lead->node_id );
			echo '<tr' . ( 'new' === $lead->status ? ' class="solo-estate-lead--new"' : '' ) . '>';
			printf( '<th scope="row" class="check-column"><input type="checkbox" name="ids[]" value="%d"></th>', (int) $lead->id );
			echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->created_at ) ) . '</td>';
			echo '<td>' . esc_html( $lead->name );
			if ( $lead->email ) {
				echo '<br><a href="mailto:' . esc_attr( $lead->email ) . '">' . esc_html( $lead->email ) . '</a>';
			}
			if ( $lead->message ) {
				echo '<br><small>' . esc_html( $lead->message ) . '</small>';
			}
			echo '</td>';
			echo '<td><a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $lead->phone ) ) . '">' . esc_html( $lead->phone ) . '</a></td>';
			echo '<td>';
			if ( $node ) {
				$context = Leads::context( $node );
				printf( '<a href="%1$s">%2$s</a>', esc_url( Admin::url( 'solo-estate', array( 'node' => $node->id ) ) ), esc_html( implode( ' › ', array_filter( $context ) ) ) );
			} else {
				echo '—';
			}
			if ( $lead->page_url ) {
				printf( '<br><a href="%1$s" target="_blank" rel="noopener"><small>%2$s</small></a>', esc_url( $lead->page_url ), esc_html__( 'Open page', 'solo-estate' ) );
			}
			echo '</td>';
			echo '<td>' . esc_html( strtoupper( $lead->lang ) ) . '</td>';
			echo '<td>' . esc_html( Leads::status_label( $lead->status ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></form></div>';
	}

	/**
	 * Bulk status / delete.
	 */
	public static function handle_bulk() {
		Admin::check( 'solo_estate_leads_bulk', \SoloEstate\Install::CAP_SELL );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$bulk = isset( $_POST['bulk'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk'] ) ) : '';
		$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		// phpcs:enable
		$back = Admin::url( 'solo-estate-leads' );
		if ( 'delete' === $bulk ) {
			if ( ! Admin::can_manage() ) {
				Admin::deny();
			}
			Leads::delete( $ids );
			Admin::redirect( $back, 'deleted' );
		}
		if ( 0 === strpos( $bulk, 'status:' ) ) {
			Leads::set_status( $ids, substr( $bulk, 7 ) );
			Admin::redirect( $back, 'updated' );
		}
		Admin::redirect( $back, 'error' );
	}

	/**
	 * CSV download (UTF-8 with BOM so Excel shows Georgian correctly).
	 */
	public static function handle_export() {
		Admin::check( 'solo_estate_leads_export' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=solo-estate-leads-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'id', 'date', 'name', 'phone', 'email', 'message', 'project', 'building', 'floor', 'apartment', 'language', 'status', 'page' ), ',', '"', '' );
		foreach ( Leads::all() as $lead ) {
			$context = Leads::context( Nodes::get( $lead->node_id ) );
			fputcsv(
				$out,
				array_map(
					array( __CLASS__, 'csv_safe' ),
					array( $lead->id, $lead->created_at, $lead->name, $lead->phone, $lead->email, $lead->message, $context['project'], $context['building'], $context['floor'], $context['flat'], $lead->lang, $lead->status, $lead->page_url )
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Neutralises spreadsheet formula injection.
	 *
	 * @param mixed $value Cell.
	 * @return string
	 */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		return ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $value ) ) ? "'" . $value : $value;
	}
}
