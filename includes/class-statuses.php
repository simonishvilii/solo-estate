<?php
/**
 * Statuses (for sale, for rent, reserved, sold, …) per level with colour and behaviour:
 * clickable (visitors can open the item), available (free-unit counters) and sold
 * (sold percentages).
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Statuses {

	const SCOPES = array( 'project', 'building', 'floor', 'flat' );

	/**
	 * Which status list a level uses: projects have their own (Ongoing / Completed);
	 * phases and buildings share one; floors and parkings share one; apartments,
	 * commercial spaces, villas and parking spaces share the unit statuses.
	 */
	const LEVEL_SCOPE = array(
		'project'    => 'project',
		'phase'      => 'building',
		'building'   => 'building',
		'floor'      => 'floor',
		'parking'    => 'floor',
		'flat'       => 'flat',
		'commercial' => 'flat',
		'villa'      => 'flat',
		'spot'       => 'flat',
	);

	/**
	 * Status scope of a level, or '' when the level has no status.
	 *
	 * @param string $level Level.
	 * @return string
	 */
	public static function scope_of( $level ) {
		return isset( self::LEVEL_SCOPE[ $level ] ) ? self::LEVEL_SCOPE[ $level ] : '';
	}

	/**
	 * Statuses available for a level.
	 *
	 * @param string $level Level.
	 * @return object[]
	 */
	public static function for_level( $level ) {
		$scope = self::scope_of( $level );
		return '' === $scope ? array() : self::for_scope( $scope );
	}

	/** @var object[]|null */
	private static $all = null;

	/**
	 * All statuses, ordered.
	 *
	 * @return object[]
	 */
	public static function all() {
		global $wpdb;

		if ( null === self::$all ) {
			$table = Install::table( 'statuses' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows      = (array) $wpdb->get_results( "SELECT * FROM $table ORDER BY scope ASC, sort_order ASC, id ASC" );
			self::$all = array();
			foreach ( $rows as $row ) {
				$row->id                = (int) $row->id;
				$row->clickable         = (int) $row->clickable;
				$row->available         = (int) $row->available;
				$row->sold              = isset( $row->sold ) ? (int) $row->sold : 0;
				$row->sort_order        = (int) $row->sort_order;
				$row->i18n              = I18n::decode( $row->i18n );
				self::$all[ $row->id ] = $row;
			}
		}
		return self::$all;
	}

	/**
	 * Statuses for a scope.
	 *
	 * @param string $scope project|building|floor|flat.
	 * @return object[]
	 */
	public static function for_scope( $scope ) {
		return array_values(
			array_filter(
				self::all(),
				static function ( $s ) use ( $scope ) {
					return $s->scope === $scope;
				}
			)
		);
	}

	/**
	 * Single status.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ (int) $id ] ) ? $all[ (int) $id ] : null;
	}

	/**
	 * Ids of statuses that count as "available".
	 *
	 * @param string $scope Scope.
	 * @return int[]
	 */
	public static function available_ids( $scope ) {
		$ids = array();
		foreach ( self::for_scope( $scope ) as $status ) {
			if ( $status->available ) {
				$ids[] = $status->id;
			}
		}
		return $ids;
	}

	/**
	 * Translated title.
	 *
	 * @param object|null $status Status.
	 * @param string|null $lang   Language.
	 * @return string
	 */
	public static function title( $status, $lang = null ) {
		return ( $status && isset( $status->i18n['title'] ) ) ? I18n::pick( $status->i18n['title'], $lang ) : '';
	}

	/**
	 * Whether a node with this status can be opened. Nodes without a status are clickable.
	 *
	 * @param object|null $status Status.
	 * @return bool
	 */
	public static function is_clickable( $status ) {
		return ! $status || (bool) $status->clickable;
	}

	/**
	 * Inserts or updates a status. Values must be sanitized.
	 *
	 * @param array $data Data (`i18n` as array).
	 * @param int   $id   Existing id.
	 * @return int
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;

		$table = Install::table( 'statuses' );
		$row   = array_intersect_key( $data, array_flip( array( 'scope', 'color', 'clickable', 'available', 'sold', 'sort_order', 'i18n', 'legacy_id' ) ) );
		if ( isset( $row['i18n'] ) && is_array( $row['i18n'] ) ) {
			$row['i18n'] = I18n::encode( $row['i18n'] );
		}
		self::$all = null;

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $id;
		}
		$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->insert_id;
	}

	/**
	 * Deletes a status; nodes using it fall back to "no status".
	 *
	 * @param int $id Id.
	 */
	public static function delete( $id ) {
		global $wpdb;

		$wpdb->delete( Install::table( 'statuses' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( Install::table( 'nodes' ), array( 'status_id' => 0 ), array( 'status_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::$all = null;
	}

	/**
	 * Clears the in-request cache.
	 */
	public static function flush() {
		self::$all = null;
	}
}
