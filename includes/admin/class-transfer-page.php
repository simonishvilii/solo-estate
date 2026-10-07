<?php
/**
 * Import / Export of apartments as CSV (opens in Excel, Google Sheets, Numbers).
 *
 * @package SoloEstate\Admin
 */

namespace SoloEstate\Admin;

use SoloEstate\Csv;
use SoloEstate\Nodes;

defined( 'ABSPATH' ) || exit;

class Transfer_Page {

	const TRANSIENT = 'solo_estate_csv_report_';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_post_solo_estate_csv_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_solo_estate_csv_import', array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * Screen.
	 */
	public static function render() {
		$projects = Nodes::projects();

		echo '<div class="wrap solo-estate-wrap"><h1>' . esc_html__( 'Import / Export', 'solo-estate' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['csv_error'] ) ? sanitize_key( wp_unslash( $_GET['csv_error'] ) ) : '';
		if ( 'file' === $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Please choose a .csv file up to 10 MB.', 'solo-estate' ) . '</p></div>';
		} elseif ( 'none' === $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Select at least one project to export.', 'solo-estate' ) . '</p></div>';
		}
		self::render_report();

		// Export (needs at least one project).
		if ( $projects ) {
			echo '<div class="solo-estate-card"><h2>' . esc_html__( 'Export apartments', 'solo-estate' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'Downloads every apartment of the selected projects in one file, with the project name, status, rooms, area, prices and specification. Edit it in Excel or Google Sheets and import it back to update everything at once.', 'solo-estate' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'solo_estate_csv_export' );
			echo '<input type="hidden" name="action" value="solo_estate_csv_export">';
			echo '<p><label><input type="checkbox" data-solo-estate-check-group="export-projects" checked> <strong>' . esc_html__( 'All projects', 'solo-estate' ) . '</strong></label></p>';
			echo '<div class="solo-estate-apply-layout__floors">';
			foreach ( $projects as $project ) {
				printf(
					'<label><input type="checkbox" name="projects[]" value="%1$d" data-group="export-projects" checked> %2$s</label>',
					(int) $project->id,
					esc_html( Nodes::admin_title( $project ) )
				);
			}
			echo '</div>';
			submit_button( __( 'Download CSV', 'solo-estate' ), 'secondary', 'submit', false );
			echo '</form></div>';
		}

		// Import.
		echo '<div class="solo-estate-card"><h2>' . esc_html__( 'Import apartments', 'solo-estate' ) . '</h2>';
		echo '<ul class="ul-disc description">';
		echo '<li>' . esc_html__( 'Use the exported file as a template. Units are matched by id, or by project + phase + building + floor + unit number (phase only when the project uses phases). The entrance column is a detail of the apartment.', 'solo-estate' ) . '</li>';
		echo '<li>' . esc_html__( 'The "type" column is flat, commercial, villa or spot (parking space). Villas leave building and floor empty. Parking spaces can only be updated, by id.', 'solo-estate' ) . '</li>';
		echo '<li>' . esc_html__( 'The "project" column says which project each row belongs to. A project that does not exist on the site yet is created during the import.', 'solo-estate' ) . '</li>';
		echo '<li>' . esc_html__( 'Empty cells leave the value unchanged. Type a single "-" to clear a value.', 'solo-estate' ) . '</li>';
		echo '<li>' . esc_html__( 'Status can be written in any site language (e.g. Sold, გაყიდულია).', 'solo-estate' ) . '</li>';
		echo '</ul>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'solo_estate_csv_import' );
		echo '<input type="hidden" name="action" value="solo_estate_csv_import">';
		self::project_select( $projects, __( 'From the file ("project" column)', 'solo-estate' ) );
		echo '<p><input type="file" name="csv" accept=".csv,text/csv" required></p>';
		echo '<p><label><input type="checkbox" name="create" value="1" checked> ' . esc_html__( 'Create projects, buildings, floors and apartments that do not exist yet', 'solo-estate' ) . '</label><br>';
		echo '<label><input type="checkbox" name="preview" value="1" checked> <strong>' . esc_html__( 'Preview only — show what would change, without saving', 'solo-estate' ) . '</strong></label></p>';
		submit_button( __( 'Upload', 'solo-estate' ), 'primary', 'submit', false );
		echo '</form></div></div>';
	}

	private static function project_select( array $projects, $from_file = '' ) {
		echo '<p><label>' . esc_html__( 'Project', 'solo-estate' ) . ' <select name="project">';
		if ( '' !== $from_file ) {
			printf( '<option value="0">%s</option>', esc_html( $from_file ) );
		}
		foreach ( $projects as $project ) {
			printf( '<option value="%1$d">%2$s</option>', (int) $project->id, esc_html( Nodes::admin_title( $project ) ) );
		}
		echo '</select></label></p>';
	}

	/**
	 * Result of the last import for this user (kept for one minute).
	 */
	private static function render_report() {
		$key    = self::TRANSIENT . get_current_user_id();
		$report = get_transient( $key );
		if ( ! is_array( $report ) ) {
			return;
		}
		delete_transient( $key );

		$class = $report['applied'] ? 'notice-success' : 'notice-info';
		echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>' . esc_html( $report['applied'] ? __( 'Import finished.', 'solo-estate' ) : __( 'Preview — nothing was saved yet.', 'solo-estate' ) ) . '</strong></p><ul class="ul-disc">';
		/* translators: %d: number */
		echo '<li>' . esc_html( sprintf( __( 'Rows read: %d', 'solo-estate' ), $report['rows'] ) ) . '</li>';
		/* translators: %d: number */
		echo '<li>' . esc_html( sprintf( __( 'Apartments updated: %d', 'solo-estate' ), $report['updated'] ) ) . '</li>';
		/* translators: %d: number */
		echo '<li>' . esc_html( sprintf( __( 'Unchanged: %d', 'solo-estate' ), $report['unchanged'] ) ) . '</li>';
		/* translators: 1: projects, 2: buildings, 3: floors, 4: apartments */
		echo '<li>' . esc_html( sprintf( __( 'New: %1$d projects, %2$d buildings, %3$d floors, %4$d apartments', 'solo-estate' ), $report['created']['project'], $report['created']['building'], $report['created']['floor'], $report['created']['flat'] ) ) . '</li>';
		if ( ! empty( $report['created']['phase'] ) ) {
			/* translators: %d: number of phases */
			echo '<li>' . esc_html( sprintf( __( 'New phases: %d', 'solo-estate' ), $report['created']['phase'] ) ) . '</li>';
		}
		echo '</ul>';
		if ( $report['errors'] ) {
			/* translators: %d: number of problems */
			echo '<p><strong>' . esc_html( sprintf( _n( '%d problem:', '%d problems:', count( $report['errors'] ), 'solo-estate' ), count( $report['errors'] ) ) ) . '</strong></p><ul class="ul-disc">';
			foreach ( array_slice( $report['errors'], 0, 50 ) as $error ) {
				/* translators: 1: line number, 2: message */
				echo '<li>' . esc_html( sprintf( __( 'Line %1$d: %2$s', 'solo-estate' ), $error[0], $error[1] ) ) . '</li>';
			}
			echo '</ul>';
		}
		if ( ! $report['applied'] && ( $report['updated'] || array_sum( $report['created'] ) ) ) {
			echo '<p>' . esc_html__( 'If this looks right, upload the same file again with "Preview only" unticked.', 'solo-estate' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Download.
	 */
	public static function handle_export() {
		Admin::check( 'solo_estate_csv_export' );
		$ids      = isset( $_POST['projects'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['projects'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$projects = array();
		foreach ( Nodes::projects() as $project ) {
			if ( in_array( $project->id, $ids, true ) ) {
				$projects[] = $project;
			}
		}
		if ( ! $projects ) {
			wp_safe_redirect( add_query_arg( 'csv_error', 'none', Admin::url( 'solo-estate-transfer' ) ) );
			exit;
		}
		Csv::download( $projects );
	}

	/**
	 * Upload, preview or apply.
	 */
	public static function handle_import() {
		Admin::check( 'solo_estate_csv_import' );
		$back = Admin::url( 'solo-estate-transfer' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$pid     = isset( $_POST['project'] ) ? absint( $_POST['project'] ) : 0;
		$project = $pid ? Nodes::get( $pid ) : null;
		$tmp     = isset( $_FILES['csv']['tmp_name'] ) ? (string) $_FILES['csv']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$name    = isset( $_FILES['csv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['csv']['name'] ) ) : '';
		$size    = isset( $_FILES['csv']['size'] ) ? (int) $_FILES['csv']['size'] : 0;
		$apply   = empty( $_POST['preview'] );
		$create  = ! empty( $_POST['create'] );
		// phpcs:enable

		if ( $pid && ( ! $project || 'project' !== $project->level ) ) {
			Admin::redirect( $back, 'error' );
		}
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || ! in_array( $ext, array( 'csv', 'txt' ), true ) || $size <= 0 || $size > 10 * MB_IN_BYTES ) {
			wp_safe_redirect( add_query_arg( 'csv_error', 'file', $back ) );
			exit;
		}

		$report            = Csv::import( $tmp, $project, $apply, $create );
		$report['applied'] = $apply;
		set_transient( self::TRANSIENT . get_current_user_id(), $report, MINUTE_IN_SECONDS );
		wp_safe_redirect( $back );
		exit;
	}
}
