<?php
/**
 * Trash: a deleted item (project, building, floor, apartment…) is kept with everything inside
 * for a while and can be restored, polygons, prices and specification included.
 *
 * The rows are stored as they were (same ids), so links, shortcodes and leads that point at
 * them work again after a restore. Entries are removed for good after RETENTION days.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Trash {

	/** Days an entry is kept. */
	const RETENTION = 30;

	/**
	 * Moves a node and everything inside to the trash.
	 *
	 * @param int $id Node id.
	 * @return int Number of items removed.
	 */
	public static function move( $id ) {
		global $wpdb;

		$node = Nodes::get( $id );
		if ( ! $node ) {
			return 0;
		}
		$ids   = Nodes::descendant_ids( $node->id );
		$ids[] = $node->id;
		$in    = implode( ',', array_map( 'intval', $ids ) );
		$nodes = Install::table( 'nodes' );
		$specs = Install::table( 'spec_values' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data = array(
			'nodes' => (array) $wpdb->get_results( "SELECT * FROM $nodes WHERE id IN ($in)", ARRAY_A ),
			'specs' => (array) $wpdb->get_results( "SELECT node_id, field_id, value FROM $specs WHERE node_id IN ($in)", ARRAY_A ),
		);
		// phpcs:enable
		$json = wp_json_encode( $data );
		if ( ! $json || ! $data['nodes'] ) {
			return 0;
		}
		$units = 0;
		foreach ( $data['nodes'] as $row ) {
			if ( Nodes::is_unit( $row['level'] ) ) {
				$units++;
			}
		}
		$stored = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Install::table( 'trash' ),
			array(
				'deleted_at' => current_time( 'mysql' ),
				'user_id'    => get_current_user_id(),
				'node_id'    => $node->id,
				'parent_id'  => $node->parent_id,
				'level'      => $node->level,
				'title'      => mb_substr( Nodes::admin_title( $node ), 0, 191 ),
				'items'      => count( $data['nodes'] ),
				'units'      => $units,
				'data'       => $json,
			)
		);
		// Never delete what could not be kept.
		if ( ! $stored ) {
			return 0;
		}
		return Nodes::delete( $node->id );
	}

	/**
	 * Entries, newest first.
	 *
	 * @return object[]
	 */
	public static function all() {
		global $wpdb;
		$table = Install::table( 'trash' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT id, deleted_at, user_id, node_id, parent_id, level, title, items, units FROM $table ORDER BY deleted_at DESC, id DESC" );
	}

	/**
	 * Number of entries.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		$table = Install::table( 'trash' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	}

	/**
	 * Puts an entry back.
	 *
	 * @param int $trash_id Entry id.
	 * @return string '' on success, otherwise the reason: 'missing', 'parent' (its parent no
	 *                longer exists) or 'error'.
	 */
	public static function restore( $trash_id ) {
		global $wpdb;

		$table = Install::table( 'trash' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $trash_id ) );
		if ( ! $entry ) {
			return 'missing';
		}
		$data = json_decode( (string) $entry->data, true );
		if ( ! is_array( $data ) || empty( $data['nodes'] ) ) {
			return 'error';
		}
		$parent = (int) $entry->parent_id ? Nodes::get( (int) $entry->parent_id ) : null;
		if ( (int) $entry->parent_id && ! $parent ) {
			return 'parent';
		}

		$nodes   = Install::table( 'nodes' );
		$columns = array_flip( (array) $wpdb->get_col( "DESC $nodes", 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids     = array_map( 'intval', wp_list_pluck( $data['nodes'], 'id' ) );
		$in      = implode( ',', $ids );
		// Ids are normally free (they are never reused); if one was taken meanwhile, the whole
		// entry gets new ids.
		$taken = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $nodes WHERE id IN ($in)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$map   = array();

		// Parents before children.
		$rows = $data['nodes'];
		usort(
			$rows,
			static function ( $a, $b ) {
				return substr_count( (string) $a['path'], '/' ) - substr_count( (string) $b['path'], '/' );
			}
		);
		foreach ( $rows as $row ) {
			$row    = array_intersect_key( $row, $columns );
			$old_id = (int) $row['id'];
			if ( isset( $map[ (int) $row['parent_id'] ] ) ) {
				$row['parent_id'] = $map[ (int) $row['parent_id'] ];
			}
			if ( isset( $map[ (int) $row['project_id'] ] ) ) {
				$row['project_id'] = $map[ (int) $row['project_id'] ];
			}
			if ( $taken ) {
				unset( $row['id'] );
			}
			if ( ! $wpdb->insert( $nodes, $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return 'error';
			}
			$map[ $old_id ] = $taken ? (int) $wpdb->insert_id : $old_id;
			if ( $taken && 'project' === $row['level'] && (int) $row['project_id'] === $old_id ) {
				$wpdb->update( $nodes, array( 'project_id' => $map[ $old_id ] ), array( 'id' => $map[ $old_id ] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		foreach ( (array) ( isset( $data['specs'] ) ? $data['specs'] : array() ) as $spec ) {
			if ( isset( $map[ (int) $spec['node_id'] ] ) ) {
				$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					Install::table( 'spec_values' ),
					array(
						'node_id'  => $map[ (int) $spec['node_id'] ],
						'field_id' => (int) $spec['field_id'],
						'value'    => (string) $spec['value'],
					)
				);
			}
		}
		// Paths follow the parent as it is now (it may have moved meanwhile).
		Nodes::rebuild_paths();
		$wpdb->delete( $table, array( 'id' => (int) $entry->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		Nodes::flush();
		Nodes::changed( 'restored', array_values( $map ) );
		return '';
	}

	/**
	 * Removes an entry for good.
	 *
	 * @param int $trash_id Entry id.
	 */
	public static function purge( $trash_id ) {
		global $wpdb;
		$wpdb->delete( Install::table( 'trash' ), array( 'id' => (int) $trash_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Daily: removes entries older than RETENTION days.
	 */
	public static function cleanup() {
		global $wpdb;
		$table  = Install::table( 'trash' );
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::RETENTION * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with local deleted_at.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE deleted_at < %s", $cutoff ) );
	}
}
