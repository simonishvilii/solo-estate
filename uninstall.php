<?php
/**
 * Removes plugin data on uninstall, only when enabled in Solo Estate → Settings.
 *
 * @package SoloEstate
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$solo_estate_settings = get_option( 'solo_estate_settings', array() );
if ( empty( $solo_estate_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'solo_estate_nodes', 'solo_estate_statuses', 'solo_estate_spec_fields', 'solo_estate_spec_values', 'solo_estate_leads' ) as $solo_estate_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$solo_estate_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
wp_clear_scheduled_hook( 'solo_estate_daily' );
wp_clear_scheduled_hook( 'solo_estate_refresh_rate' );
foreach ( array( 'solo_estate_settings', 'solo_estate_texts', 'solo_estate_db_version', 'solo_estate_rate' ) as $solo_estate_option ) {
	delete_option( $solo_estate_option );
}
foreach ( array( 'solo_estate_manager', 'solo_estate_marketing', 'solo_estate_sales' ) as $solo_estate_role_slug ) {
	remove_role( $solo_estate_role_slug );
}
foreach ( wp_roles()->role_objects as $solo_estate_role ) {
	foreach ( array( 'manage_solo_estate', 'edit_solo_estate', 'sell_solo_estate' ) as $solo_estate_cap ) {
		$solo_estate_role->remove_cap( $solo_estate_cap );
	}
}
