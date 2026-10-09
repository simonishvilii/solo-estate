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

	/** @var bool The last attempt to read the statuses failed (database error). */
	public static $failed = false;

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
			$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY scope ASC, sort_order ASC, id ASC" );
			if ( null === $rows || '' !== $wpdb->last_error ) {
				self::$failed = true;
				return array(); // Query failed: not remembered; Statuses::of() treats items as closed.
			}
			self::$failed = false;
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
	 * Status of an item for the site: null when it has none (open), the status, or, when its
	 * status cannot be found (database error, removed), a closed stand-in. Sold apartments must
	 * never turn into open ones because a lookup failed.
	 *
	 * @param int $id Status id of the item.
	 * @return object|null
	 */
	public static function of( $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$status = self::get( $id );
		if ( $status ) {
			return $status;
		}
		return (object) array(
			'id'         => $id,
			'scope'      => '',
			'color'      => '#9ca3af',
			'clickable'  => 0,
			'available'  => 0,
			'sold'       => 0,
			'sort_order' => 0,
			'i18n'       => array(),
			'missing'    => true,
		);
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
			$stored      = $id ? self::get( $id ) : null;
			$row['i18n'] = I18n::encode( $stored ? I18n::keep_inactive( $row['i18n'], (array) $stored->i18n ) : $row['i18n'] );
		}
		self::$all = null;
		Nodes::changed( 'statuses' );

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $id;
		}
		$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->insert_id;
	}

	/**
	 * Deletes a status. A status in use is deleted only together with a replacement of the
	 * same list, which its items get.
	 *
	 * @param int $id          Id.
	 * @param int $replacement Status the items move to.
	 * @return bool Whether it was deleted.
	 */
	public static function delete( $id, $replacement = 0 ) {
		global $wpdb;

		$id     = (int) $id;
		$status = self::get( $id );
		if ( ! $status ) {
			return false;
		}
		// Items with this status move to another status of the same list first: without one,
		// sold and reserved apartments would become open, clickable units with a call form.
		if ( self::usage( $id ) ) {
			$target = self::get( (int) $replacement );
			if ( ! $target || $target->id === $id || $target->scope !== $status->scope ) {
				return false;
			}
			$wpdb->update( Install::table( 'nodes' ), array( 'status_id' => $target->id ), array( 'status_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Nodes::flush();
		}
		$wpdb->delete( Install::table( 'statuses' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::$all   = null;
		self::$usage = null;
		Nodes::changed( 'statuses' );
		return true;
	}

	/** @var array<int,int>|null */
	private static $usage = null;

	/**
	 * Number of items that have a status (all statuses when $id is omitted).
	 *
	 * @param int|null $id Status id.
	 * @return int|array<int,int>
	 */
	public static function usage( $id = null ) {
		global $wpdb;

		if ( null === self::$usage ) {
			self::$usage = array();
			$table       = Install::table( 'nodes' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $wpdb->get_results( "SELECT status_id, COUNT(*) AS c FROM $table WHERE status_id > 0 GROUP BY status_id" ) as $row ) {
				self::$usage[ (int) $row->status_id ] = (int) $row->c;
			}
		}
		if ( null === $id ) {
			return self::$usage;
		}
		return isset( self::$usage[ (int) $id ] ) ? self::$usage[ (int) $id ] : 0;
	}

	/**
	 * Clears the in-request cache.
	 */
	public static function flush() {
		self::$all = null;
	}
}
