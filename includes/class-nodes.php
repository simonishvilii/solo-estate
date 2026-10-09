<?php
/**
 * The tree of a project: project → [phase] → building → floor → apartment, plus villas,
 * parkings and commercial spaces. The entrance is a field of the apartment, not a level:
 * a floor plan covers the whole floor.
 *
 * Every node stores `path` ("/1/5/13/") so counts below any node are one query.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Nodes {

	const LEVELS = array( 'project', 'phase', 'building', 'floor', 'parking', 'flat', 'commercial', 'villa', 'villa_floor', 'spot' );

	/**
	 * What can be added inside what. Phases are optional: buildings can sit directly in a
	 * project.
	 */
	const CHILDREN = array(
		'project'  => array( 'phase', 'building', 'villa', 'parking' ),
		'phase'    => array( 'building', 'villa', 'parking' ),
		'building' => array( 'floor', 'parking' ),
		'floor'    => array( 'flat', 'commercial' ),
		'parking'  => array( 'spot' ),
		'villa'    => array( 'villa_floor' ),
	);

	/** Units that are sold and counted in availability / sold percentages. */
	const UNITS = array( 'flat', 'commercial', 'villa' );

	/** Units with their own page, prices, gallery and specification. */
	const PROPERTIES = array( 'flat', 'commercial', 'villa' );

	/** Visitor access to a node page: automatic, always open, always closed. */
	const ACCESS_AUTO   = 0;
	const ACCESS_OPEN   = 1;
	const ACCESS_CLOSED = 2;

	/**
	 * Levels that can show an info window (photos + description) instead of their own page:
	 * a boulevard, a sports field or a park drawn on the masterplan like a building.
	 */
	const INFO_LEVELS = array( 'phase', 'building', 'parking' );

	/** Translatable fields per level. */
	const I18N_FIELDS = array(
		'project'     => array( 'title', 'description' ),
		'phase'       => array( 'title', 'completion', 'description' ),
		'building'    => array( 'title', 'label', 'completion', 'link', 'description' ),
		'floor'       => array( 'title' ),
		'parking'     => array( 'title', 'description' ),
		'flat'        => array( 'title', 'description' ),
		'commercial'  => array( 'title', 'description' ),
		'villa'       => array( 'title', 'description' ),
		'villa_floor' => array( 'title' ),
		'spot'        => array( 'title' ),
	);

	/** Columns that Nodes::save() writes. */
	const COLUMNS = array( 'parent_id', 'level', 'number', 'entrance', 'status_id', 'access', 'info_modal', 'image_id', 'image2_id', 'coord_w', 'coord_h', 'gallery', 'tour_url', 'coords', 'area', 'area_living', 'area_summer', 'rooms', 'price_sqm', 'price_total', 'sort_order', 'i18n', 'legacy_id' );

	/** @var array<int,object> */
	private static $cache = array();

	/** @var array<int,array> */
	private static $stats = array();

	/** @var int Items a duplicate() call could not copy (the admin reports a partial copy). */
	public static $copy_failures = 0;

	/** @var array<int,int[]> Child ids per parent, in order (per request). */
	private static $child_ids = array();

	/**
	 * Levels that can be added inside a level.
	 *
	 * @param string $level Level.
	 * @return string[]
	 */
	public static function child_levels( $level ) {
		return isset( self::CHILDREN[ $level ] ) ? self::CHILDREN[ $level ] : array();
	}

	/**
	 * Whether a level can hold another.
	 *
	 * @param string $parent Parent level ('' for the root).
	 * @param string $child  Child level.
	 * @return bool
	 */
	public static function allows( $parent, $child ) {
		return '' === $parent ? 'project' === $child : in_array( $child, self::child_levels( $parent ), true );
	}

	/**
	 * Whether a level is a sellable unit (apartment, commercial space, villa).
	 *
	 * @param string $level Level.
	 * @return bool
	 */
	public static function is_unit( $level ) {
		return in_array( $level, self::UNITS, true );
	}

	/**
	 * Human label for a level (admin).
	 *
	 * @param string $level  Level.
	 * @param bool   $plural Plural form.
	 * @return string
	 */
	public static function level_label( $level, $plural = false ) {
		$labels = array(
			'project'     => array( __( 'Project', 'solo-estate' ), __( 'Projects', 'solo-estate' ) ),
			'phase'       => array( __( 'Phase', 'solo-estate' ), __( 'Phases', 'solo-estate' ) ),
			'building'    => array( __( 'Building', 'solo-estate' ), __( 'Buildings', 'solo-estate' ) ),
			'floor'       => array( __( 'Floor', 'solo-estate' ), __( 'Floors', 'solo-estate' ) ),
			'parking'     => array( __( 'Parking', 'solo-estate' ), __( 'Parkings', 'solo-estate' ) ),
			'flat'        => array( __( 'Apartment', 'solo-estate' ), __( 'Apartments', 'solo-estate' ) ),
			'commercial'  => array( __( 'Commercial space', 'solo-estate' ), __( 'Commercial spaces', 'solo-estate' ) ),
			'villa'       => array( __( 'Villa', 'solo-estate' ), __( 'Villas', 'solo-estate' ) ),
			'villa_floor' => array( __( 'Villa floor', 'solo-estate' ), __( 'Villa floors', 'solo-estate' ) ),
			'spot'        => array( __( 'Parking space', 'solo-estate' ), __( 'Parking spaces', 'solo-estate' ) ),
		);
		return isset( $labels[ $level ] ) ? $labels[ $level ][ $plural ? 1 : 0 ] : $level;
	}

	/**
	 * Loads a node.
	 *
	 * @param int $id Node id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		if ( ! isset( self::$cache[ $id ] ) ) {
			$table = Install::table( 'nodes' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
			if ( ! $row ) {
				return null;
			}
			self::$cache[ $id ] = self::hydrate( $row );
		}
		return self::$cache[ $id ];
	}

	/**
	 * Loads several nodes with one query (those not loaded yet), e.g. search results and
	 * their buildings and floors.
	 *
	 * @param int[] $ids Node ids.
	 */
	public static function prime( array $ids ) {
		global $wpdb;

		$missing = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! isset( self::$cache[ $id ] ) ) {
				$missing[ $id ] = $id;
			}
		}
		if ( ! $missing ) {
			return;
		}
		$table = Install::table( 'nodes' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $wpdb->get_results( "SELECT * FROM $table WHERE id IN (" . implode( ',', $missing ) . ')' ) as $row ) {
			$node                     = self::hydrate( $row );
			self::$cache[ $node->id ] = $node;
		}
	}

	/**
	 * Children of a node (one query per parent and request).
	 *
	 * @param int         $parent_id Parent id (0 for projects).
	 * @param string|null $level     Only this level.
	 * @return object[]
	 */
	public static function children( $parent_id, $level = null ) {
		global $wpdb;

		$parent_id = (int) $parent_id;
		if ( ! isset( self::$child_ids[ $parent_id ] ) ) {
			$table = Install::table( 'nodes' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE parent_id = %d ORDER BY sort_order ASC, id ASC", $parent_id ) );
			if ( null === $rows || '' !== $wpdb->last_error ) {
				return array(); // Query failed: not remembered, so a later call tries again.
			}
			self::$child_ids[ $parent_id ] = array();
			foreach ( $rows as $row ) {
				$node                            = self::hydrate( $row );
				self::$cache[ $node->id ]        = $node;
				self::$child_ids[ $parent_id ][] = $node->id;
			}
		}
		self::prime( self::$child_ids[ $parent_id ] );
		$out = array();
		foreach ( self::$child_ids[ $parent_id ] as $id ) {
			$node = isset( self::$cache[ $id ] ) ? self::$cache[ $id ] : null;
			if ( $node && ( null === $level || $node->level === $level ) ) {
				$out[] = $node;
			}
		}
		return $out;
	}

	/**
	 * Children in natural number order (1, 2, 10 — not 1, 10, 2), then by id.
	 *
	 * @param int         $parent_id Parent id.
	 * @param string|null $level     Only this level.
	 * @return object[]
	 */
	public static function sorted_children( $parent_id, $level = null ) {
		$children = self::children( $parent_id, $level );
		usort(
			$children,
			static function ( $a, $b ) {
				$cmp = strnatcmp( (string) $a->number, (string) $b->number );
				return $cmp ? $cmp : $a->id - $b->id;
			}
		);
		return $children;
	}

	/**
	 * All projects.
	 *
	 * @return object[]
	 */
	public static function projects() {
		return self::children( 0 );
	}

	/**
	 * Chain from the project down to (and including) the node.
	 *
	 * @param object $node Node.
	 * @return object[]
	 */
	public static function ancestors( $node ) {
		$chain = array( $node );
		$guard = 0;
		while ( $node && $node->parent_id && $guard++ < 12 ) {
			$node = self::get( $node->parent_id );
			if ( $node ) {
				array_unshift( $chain, $node );
			}
		}
		return $chain;
	}

	/**
	 * Nearest ancestor of a level.
	 *
	 * @param object $node  Node.
	 * @param string $level Level.
	 * @return object|null
	 */
	public static function ancestor( $node, $level ) {
		foreach ( array_reverse( self::ancestors( $node ) ) as $item ) {
			if ( $item->level === $level ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Translated field.
	 *
	 * @param object      $node  Node.
	 * @param string      $field Field.
	 * @param string|null $lang  Language.
	 * @return string
	 */
	public static function field( $node, $field, $lang = null ) {
		return isset( $node->i18n[ $field ] ) ? I18n::pick( $node->i18n[ $field ], $lang ) : '';
	}

	/**
	 * Display name, e.g. "Block A", "Floor 5", "Apartment 106", "Villa 3".
	 *
	 * @param object      $node Node.
	 * @param string|null $lang Language.
	 * @return string
	 */
	public static function display_title( $node, $lang = null ) {
		$title = self::field( $node, 'title', $lang );
		switch ( $node->level ) {
			case 'project':
				return '' !== $title ? $title : sprintf( '#%d', $node->id );
			case 'building':
				// A custom name is the whole name; otherwise type label + number ("Block A").
				if ( '' !== $title ) {
					return $title;
				}
				$label = self::field( $node, 'label', $lang );
				$label = '' !== $label ? $label : Texts::get( 'building', $lang );
				return trim( $label . ' ' . $node->number );
		}
		if ( '' !== $title ) {
			return $title;
		}
		$keys = array(
			'villa_floor' => 'floor',
			'spot'        => 'spot',
		);
		$key = isset( $keys[ $node->level ] ) ? $keys[ $node->level ] : $node->level;
		return trim( Texts::get( $key, $lang ) . ' ' . $node->number );
	}

	/**
	 * Admin-facing short name.
	 *
	 * @param object $node Node.
	 * @return string
	 */
	public static function admin_title( $node ) {
		$lang = I18n::default_code();
		if ( 'project' === $node->level ) {
			return self::display_title( $node, $lang );
		}
		$name = self::field( $node, 'title', $lang );
		if ( 'building' === $node->level ) {
			return '' !== $name ? $name : trim( self::field( $node, 'label', $lang ) . ' ' . $node->number );
		}
		if ( '' === $node->number && '' !== $name ) {
			return $name;
		}
		return trim( self::level_label( $node->level ) . ' ' . ( '' !== $node->number ? $node->number : $name ) );
	}

	/**
	 * Total price in base currency: the price entered, or total area × price per m².
	 *
	 * @param object $node Unit.
	 * @return float|null
	 */
	public static function total_price( $node ) {
		if ( null !== $node->price_total && (float) $node->price_total > 0 ) {
			return (float) $node->price_total;
		}
		if ( null !== $node->area && null !== $node->price_sqm && (float) $node->price_sqm > 0 ) {
			return round( (float) $node->area * (float) $node->price_sqm, 2 );
		}
		return null;
	}

	/**
	 * Unit counts below a node: total, available, sold, open (visitors can open them).
	 * Parkings count their parking spaces; everything else counts apartments,
	 * commercial spaces and villas.
	 *
	 * @param object $node Node.
	 * @return array{total:int,available:int,sold:int,open:int,percent:int}
	 */
	public static function stats( $node ) {
		global $wpdb;

		if ( isset( self::$stats[ $node->id ] ) ) {
			return self::$stats[ $node->id ];
		}
		$out = array(
			'total'     => 0,
			'available' => 0,
			'sold'      => 0,
			'open'      => 0,
			'percent'   => 0,
			'by_status' => array(),
		);
		if ( '' === (string) $node->path || in_array( $node->level, array( 'villa_floor', 'spot' ), true ) || self::is_unit( $node->level ) ) {
			return $out;
		}
		$levels = 'parking' === $node->level ? array( 'spot' ) : self::UNITS;
		$table  = Install::table( 'nodes' );
		$in     = "'" . implode( "','", $levels ) . "'";
		$units  = "'" . implode( "','", self::UNITS ) . "'";
		// A unit inside another unit (left over from an old import) is not shown anywhere, so it
		// is not counted either; see misplaced().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT n.status_id, COUNT(*) AS c FROM $table n JOIN $table p ON p.id = n.parent_id WHERE n.path LIKE %s AND n.level IN ($in) AND p.level NOT IN ($units) GROUP BY n.status_id", $wpdb->esc_like( $node->path ) . '%' ) );
		foreach ( $rows as $row ) {
			$count         = (int) $row->c;
			$status        = Statuses::of( (int) $row->status_id );
			$out['total'] += $count;
			$out['by_status'][ (int) $row->status_id ] = $count;
			if ( $status && $status->available ) {
				$out['available'] += $count;
			}
			if ( $status && $status->sold ) {
				$out['sold'] += $count;
			}
			if ( Statuses::is_clickable( $status ) ) {
				$out['open'] += $count;
			}
		}
		// Rounded down: 100% only when every unit is sold (1 of 300 unsold is 99%, not 100%).
		$out['percent']             = $out['total'] ? (int) floor( 100 * $out['sold'] / $out['total'] ) : 0;
		self::$stats[ $node->id ] = $out;
		return $out;
	}

	/**
	 * Whether any child has an outline on this item's image.
	 *
	 * @param int $id Node id.
	 * @return bool
	 */
	public static function has_outlines( $id ) {
		foreach ( self::children( $id ) as $child ) {
			if ( self::points( $child->coords ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Natural pixel size of an attachment (from its metadata, else the file).
	 *
	 * @param int $id Attachment id.
	 * @return array{0:int,1:int}
	 */
	public static function image_size( $id ) {
		$meta = wp_get_attachment_metadata( $id );
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			return array( (int) $meta['width'], (int) $meta['height'] );
		}
		$file = get_attached_file( $id );
		if ( $file && is_readable( $file ) ) {
			$size = wp_getimagesize( $file );
			if ( $size ) {
				return array( (int) $size[0], (int) $size[1] );
			}
		}
		return array( 0, 0 );
	}

	/**
	 * Coordinate frame of the outlines on a node's image: the size they were drawn in, or the
	 * image's own size for items saved before frames were stored.
	 *
	 * @param object $node Node.
	 * @return array{0:int,1:int}
	 */
	public static function coord_space( $node ) {
		if ( ! empty( $node->coord_w ) && ! empty( $node->coord_h ) ) {
			return array( (int) $node->coord_w, (int) $node->coord_h );
		}
		return $node->image_id ? self::image_size( $node->image_id ) : array( 0, 0 );
	}

	/**
	 * Whether the image's proportions differ from the frame its outlines were drawn in (an
	 * image replaced with a different crop): the outlines are then stretched.
	 *
	 * @param object $node Node.
	 * @return bool
	 */
	public static function frame_mismatch( $node ) {
		list( $cw, $ch ) = self::coord_space( $node );
		list( $iw, $ih ) = $node->image_id ? self::image_size( $node->image_id ) : array( 0, 0 );
		if ( ! $cw || ! $ch || ! $iw || ! $ih ) {
			return false;
		}
		return abs( ( $cw / $ch ) / ( $iw / $ih ) - 1 ) > 0.01;
	}

	/**
	 * Outline converted from one frame to another (apply a floor's layout to a floor whose
	 * image has another size).
	 *
	 * @param string $coords Coordinates.
	 * @param array  $from   [w, h] frame of the coordinates.
	 * @param array  $to     [w, h] target frame.
	 * @return string
	 */
	public static function scale_coords( $coords, array $from, array $to ) {
		if ( ! $from[0] || ! $from[1] || ! $to[0] || ! $to[1] || ( $from[0] === $to[0] && $from[1] === $to[1] ) ) {
			return (string) $coords;
		}
		$out = array();
		foreach ( self::points( $coords ) as $point ) {
			$out[] = round( $point[0] * $to[0] / $from[0], 1 );
			$out[] = round( $point[1] * $to[1] / $from[1], 1 );
		}
		return implode( ',', $out );
	}

	/**
	 * Computes Nodes::stats() for every item under a root (a whole project, or everything)
	 * with one query, instead of one query per building, floor and tooltip.
	 *
	 * @param object|null $root Root node, or null for all projects.
	 */
	public static function prime_stats( $root = null ) {
		global $wpdb;

		$table = Install::table( 'nodes' );
		$where = $root ? $wpdb->prepare( 'WHERE n.path LIKE %s', $wpdb->esc_like( (string) $root->path ) . '%' ) : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT n.id, n.level, n.path, n.status_id, p.level AS parent_level FROM $table n LEFT JOIN $table p ON p.id = n.parent_id $where" );
		if ( null === $rows || '' !== $wpdb->last_error ) {
			return;
		}
		$empty = array(
			'total'     => 0,
			'available' => 0,
			'sold'      => 0,
			'open'      => 0,
			'percent'   => 0,
			'by_status' => array(),
		);
		$level = array();
		$out   = array();
		foreach ( $rows as $row ) {
			$level[ (int) $row->id ] = $row->level;
			if ( ! self::is_unit( $row->level ) && ! in_array( $row->level, array( 'villa_floor', 'spot' ), true ) && '' !== (string) $row->path ) {
				$out[ (int) $row->id ] = $empty;
			}
		}
		foreach ( $rows as $row ) {
			$is_spot = 'spot' === $row->level;
			// Units count in everything above them except parkings; parking spaces only in
			// their parking. Items inside a unit (old imports) count nowhere, as in stats().
			if ( ( ! $is_spot && ! self::is_unit( $row->level ) ) || self::is_unit( (string) $row->parent_level ) ) {
				continue;
			}
			$status = Statuses::of( (int) $row->status_id );
			foreach ( explode( '/', trim( (string) $row->path, '/' ) ) as $id ) {
				$id = (int) $id;
				if ( $id === (int) $row->id || ! isset( $out[ $id ] ) || ( 'parking' === $level[ $id ] ) !== $is_spot ) {
					continue;
				}
				$out[ $id ]['total']++;
				$sid                             = (int) $row->status_id;
				$out[ $id ]['by_status'][ $sid ] = ( isset( $out[ $id ]['by_status'][ $sid ] ) ? $out[ $id ]['by_status'][ $sid ] : 0 ) + 1;
				if ( $status && $status->available ) {
					$out[ $id ]['available']++;
				}
				if ( $status && $status->sold ) {
					$out[ $id ]['sold']++;
				}
				if ( Statuses::is_clickable( $status ) ) {
					$out[ $id ]['open']++;
				}
			}
		}
		foreach ( $out as $id => $stats ) {
			$stats['percent']    = $stats['total'] ? (int) floor( 100 * $stats['sold'] / $stats['total'] ) : 0;
			self::$stats[ $id ] = $stats;
		}
	}

	/**
	 * Units placed inside another unit (an apartment under an apartment). Nothing can be added
	 * there, but old imports brought such records in; they are hidden, not counted and listed
	 * in the admin for deletion.
	 *
	 * @return object[]
	 */
	public static function misplaced() {
		global $wpdb;

		$table = Install::table( 'nodes' );
		$units = "'" . implode( "','", self::UNITS ) . "'";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = (array) $wpdb->get_col( "SELECT n.id FROM $table n JOIN $table p ON p.id = n.parent_id WHERE n.level IN ($units) AND p.level IN ($units) ORDER BY n.id" );
		return array_values( array_filter( array_map( array( __CLASS__, 'get' ), array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * Number of available units below a node.
	 *
	 * @param object $node Node.
	 * @return int
	 */
	public static function available_count( $node ) {
		return self::stats( $node )['available'];
	}

	/**
	 * Number of units below a node that visitors can still open (any clickable status or none).
	 *
	 * @param object $node Node.
	 * @return int
	 */
	public static function open_count( $node ) {
		return self::stats( $node )['open'];
	}

	/**
	 * Number of units below a node.
	 *
	 * @param object $node Node.
	 * @return int
	 */
	public static function flat_count( $node ) {
		return self::stats( $node )['total'];
	}

	/**
	 * Direct child counts keyed by parent id, for list screens.
	 *
	 * @param int[] $ids Parent ids.
	 * @return array<int,int>
	 */
	public static function child_counts( array $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! $ids ) {
			return array();
		}
		$table = Install::table( 'nodes' );
		$in    = implode( ',', $ids );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT parent_id, COUNT(*) AS c FROM $table WHERE parent_id IN ($in) GROUP BY parent_id" );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->parent_id ] = (int) $row->c;
		}
		return $out;
	}

	/**
	 * Inserts or updates a node. Values must already be sanitized.
	 *
	 * @param array $data Column values; `i18n` as array, `gallery` as int[].
	 * @param int   $id   Existing id or 0.
	 * @return int Node id, 0 on failure.
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;

		$table = Install::table( 'nodes' );
		$row   = array_intersect_key( $data, array_flip( self::COLUMNS ) );

		if ( isset( $row['i18n'] ) && is_array( $row['i18n'] ) ) {
			$stored      = $id ? self::get( $id ) : null;
			$row['i18n'] = I18n::encode( $stored ? I18n::keep_inactive( $row['i18n'], (array) $stored->i18n ) : $row['i18n'] );
		}
		if ( isset( $row['gallery'] ) && is_array( $row['gallery'] ) ) {
			$row['gallery'] = implode( ',', array_filter( array_map( 'absint', $row['gallery'] ) ) );
		}

		// Keep project_id pointing at the root project for fast per-project queries.
		$parent = null;
		if ( isset( $row['parent_id'] ) ) {
			$parent = self::get( $row['parent_id'] );
			if ( $parent ) {
				$row['project_id'] = 'project' === $parent->level ? $parent->id : $parent->project_id;
			} else {
				// A project is its own project. (Saving one used to reset this to 0, which broke
				// everything that looks the project up by it, e.g. its apartment filter.)
				$old               = $id ? self::get( $id ) : null;
				$level             = isset( $row['level'] ) ? $row['level'] : ( $old ? $old->level : '' );
				$row['project_id'] = ( 'project' === $level && $id ) ? (int) $id : 0;
			}
		}

		$row['updated_at'] = current_time( 'mysql' );
		self::$stats       = array();
		self::$child_ids   = array();

		// Coordinate frame of the outlines drawn on this item's image. A new image keeps the old
		// frame while outlines exist on it (they then stretch with the image, so a larger or
		// smaller version of the same picture changes nothing); otherwise it takes the image's size.
		if ( isset( $row['image_id'] ) && ! isset( $row['coord_w'] ) ) {
			$before = $id ? self::get( $id ) : null;
			if ( ! $before || (int) $row['image_id'] !== $before->image_id ) {
				$keep = $before && $before->coord_w && $before->coord_h && self::has_outlines( $before->id );
				if ( ! $keep && $row['image_id'] ) {
					list( $row['coord_w'], $row['coord_h'] ) = self::image_size( (int) $row['image_id'] );
				}
			}
		}

		if ( $id ) {
			$old = self::get( $id );
			unset( self::$cache[ (int) $id ] );
			// Failed writes are reported (0), so the admin never says "Saved" for nothing.
			if ( false === $wpdb->update( $table, $row, array( 'id' => (int) $id ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return 0;
			}
			if ( $old && isset( $row['parent_id'] ) && (int) $row['parent_id'] !== $old->parent_id && ! self::move_path( $old, $parent ) ) {
				self::rebuild_paths();
				return 0;
			}
			self::changed( 'saved', array( (int) $id ) );
			return (int) $id;
		}

		$row['created_at'] = $row['updated_at'];
		if ( ! $wpdb->insert( $table, $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return 0;
		}
		$id     = (int) $wpdb->insert_id;
		$update = array( 'path' => ( $parent ? $parent->path : '/' ) . $id . '/' );
		// A new project is its own project_id.
		if ( isset( $row['level'] ) && 'project' === $row['level'] ) {
			$update['project_id'] = $id;
		}
		// Without its path the item would be invisible; undo the insert rather than keep it so.
		if ( false === $wpdb->update( $table, $update, array( 'id' => $id ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return 0;
		}
		self::changed( 'created', array( $id ) );
		return $id;
	}

	/**
	 * Announces a change of selector data (statuses, prices, outlines, texts…): page caches
	 * are purged (Cache) and other plugins can react, e.g. sync a CRM.
	 *
	 * @param string $what What happened: saved, created, deleted, status…
	 * @param int[]  $ids  Node ids concerned (empty when not about nodes).
	 */
	public static function changed( $what, array $ids = array() ) {
		/**
		 * Selector data changed.
		 *
		 * @param string $what What happened.
		 * @param int[]  $ids  Node ids concerned.
		 */
		do_action( 'solo_estate_data_changed', $what, $ids );
	}

	/**
	 * Rewrites the path (and project) of a moved node and everything inside it.
	 *
	 * @param object      $old    Node before the move.
	 * @param object|null $parent New parent.
	 * @return bool Done.
	 */
	private static function move_path( $old, $parent ) {
		global $wpdb;

		$table   = Install::table( 'nodes' );
		$new     = ( $parent ? $parent->path : '/' ) . $old->id . '/';
		$project = $parent ? ( 'project' === $parent->level ? $parent->id : $parent->project_id ) : $old->id;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$done = $wpdb->query( $wpdb->prepare( "UPDATE $table SET path = CONCAT(%s, SUBSTRING(path, %d)), project_id = %d WHERE path LIKE %s", $new, strlen( $old->path ) + 1, $project, $wpdb->esc_like( $old->path ) . '%' ) );
		// phpcs:enable
		self::flush();
		return false !== $done;
	}

	/**
	 * Recomputes every path from parent_id (upgrade and repair).
	 */
	public static function rebuild_paths() {
		global $wpdb;

		$table = Install::table( 'nodes' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows    = (array) $wpdb->get_results( "SELECT id, parent_id, path FROM $table" );
		$parents = array();
		$current = array();
		foreach ( $rows as $row ) {
			$parents[ (int) $row->id ] = (int) $row->parent_id;
			$current[ (int) $row->id ] = (string) $row->path;
		}
		$paths   = array();
		$resolve = static function ( $id ) use ( &$resolve, &$paths, $parents ) {
			if ( isset( $paths[ $id ] ) ) {
				return $paths[ $id ];
			}
			$chain = array();
			$node  = $id;
			$guard = 0;
			while ( $node && $guard++ < 20 ) {
				array_unshift( $chain, $node );
				$node = isset( $parents[ $node ] ) ? $parents[ $node ] : 0;
			}
			$paths[ $id ] = '/' . implode( '/', $chain ) . '/';
			return $paths[ $id ];
		};
		foreach ( array_keys( $parents ) as $id ) {
			$path = $resolve( $id );
			if ( $current[ $id ] !== $path ) {
				$wpdb->update( $table, array( 'path' => $path ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		self::flush();
	}

	/**
	 * Deletes a node with all descendants and their spec values.
	 *
	 * @param int $id Node id.
	 * @return int Number of deleted nodes.
	 */
	public static function delete( $id ) {
		global $wpdb;

		$ids   = self::descendant_ids( (int) $id );
		$ids[] = (int) $id;
		$in    = implode( ',', array_map( 'intval', $ids ) );

		$nodes  = Install::table( 'nodes' );
		$values = Install::table( 'spec_values' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM $values WHERE node_id IN ($in)" );
		$count = (int) $wpdb->query( "DELETE FROM $nodes WHERE id IN ($in)" );
		// phpcs:enable
		self::flush();
		self::changed( 'deleted', $ids );
		return $count;
	}

	/**
	 * All descendant ids (breadth-first).
	 *
	 * @param int $id Node id.
	 * @return int[]
	 */
	public static function descendant_ids( $id ) {
		global $wpdb;

		$table = Install::table( 'nodes' );
		$out   = array();
		$level = array( (int) $id );
		$guard = 0;
		while ( $level && $guard++ < 12 ) {
			$in = implode( ',', $level );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$level = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM $table WHERE parent_id IN ($in)" ) );
			$out   = array_merge( $out, $level );
		}
		return $out;
	}

	/**
	 * Deep-copies a node (with children and spec values) under the same parent.
	 *
	 * @param int      $id        Source node.
	 * @param int|null $parent_id Target parent; same parent when null.
	 * @return int New id.
	 */
	public static function duplicate( $id, $parent_id = null ) {
		$node = self::get( $id );
		if ( ! $node ) {
			return 0;
		}
		$data = array( 'parent_id' => null === $parent_id ? $node->parent_id : (int) $parent_id );
		foreach ( self::COLUMNS as $column ) {
			if ( 'parent_id' !== $column && 'legacy_id' !== $column ) {
				$data[ $column ] = $node->$column;
			}
		}
		$new_id = self::save( $data );
		if ( ! $new_id ) {
			return 0;
		}
		$values = Specs::values( $node->id );
		if ( $values ) {
			Specs::save_values( $new_id, $values );
		}
		foreach ( self::children( $node->id ) as $child ) {
			if ( ! self::duplicate( $child->id, $new_id ) ) {
				self::$copy_failures++;
			}
		}
		return $new_id;
	}

	/**
	 * Sets the status of many nodes at once.
	 *
	 * @param int[] $ids       Node ids.
	 * @param int   $status_id Status id.
	 */
	public static function bulk_status( array $ids, $status_id ) {
		global $wpdb;

		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! $ids ) {
			return;
		}
		$table = Install::table( 'nodes' );
		$in    = implode( ',', $ids );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET status_id = %d, updated_at = %s WHERE id IN ($in)", (int) $status_id, current_time( 'mysql' ) ) );
		self::flush();
		self::changed( 'status', $ids );
	}

	/**
	 * Gallery attachment ids.
	 *
	 * @param object $node Node.
	 * @return int[]
	 */
	public static function gallery( $node ) {
		return array_values( array_filter( array_map( 'absint', explode( ',', (string) $node->gallery ) ) ) );
	}

	/**
	 * Polygon points parsed from "x1,y1,x2,y2,…".
	 *
	 * @param string|null $coords Stored coords.
	 * @return array<int,array{0:float,1:float}>
	 */
	public static function points( $coords ) {
		$nums = array_values( array_filter( array_map( 'trim', explode( ',', (string) $coords ) ), 'is_numeric' ) );
		$out  = array();
		for ( $i = 0; $i + 1 < count( $nums ); $i += 2 ) {
			$out[] = array( (float) $nums[ $i ], (float) $nums[ $i + 1 ] );
		}
		return count( $out ) >= 3 ? $out : array();
	}

	/**
	 * Normalizes user-entered coords to "x,y,x,y" with at most 1 decimal.
	 *
	 * @param string $coords Raw value.
	 * @return string
	 */
	public static function sanitize_coords( $coords ) {
		preg_match_all( '/-?\d+(?:\.\d+)?/', (string) $coords, $m );
		$nums = array_map(
			static function ( $n ) {
				return (string) round( (float) $n, 1 );
			},
			$m[0]
		);
		if ( count( $nums ) % 2 ) {
			array_pop( $nums );
		}
		return count( $nums ) >= 6 ? implode( ',', $nums ) : '';
	}

	/**
	 * Converts a DB row into a typed object.
	 *
	 * @param object $row Row.
	 * @return object
	 */
	private static function hydrate( $row ) {
		$decimal          = static function ( $value ) {
			return null === $value ? null : (float) $value;
		};
		$row->id          = (int) $row->id;
		$row->parent_id   = (int) $row->parent_id;
		$row->project_id  = (int) $row->project_id;
		$row->status_id   = (int) $row->status_id;
		$row->access      = isset( $row->access ) ? (int) $row->access : 0;
		$row->info_modal  = isset( $row->info_modal ) ? (int) $row->info_modal : 0;
		$row->image_id    = (int) $row->image_id;
		$row->image2_id   = (int) $row->image2_id;
		$row->coord_w     = isset( $row->coord_w ) ? (int) $row->coord_w : 0;
		$row->coord_h     = isset( $row->coord_h ) ? (int) $row->coord_h : 0;
		$row->sort_order  = (int) $row->sort_order;
		$row->path        = isset( $row->path ) ? (string) $row->path : '';
		$row->entrance    = isset( $row->entrance ) ? (string) $row->entrance : '';
		$row->gallery     = isset( $row->gallery ) ? (string) $row->gallery : '';
		$row->tour_url    = isset( $row->tour_url ) ? (string) $row->tour_url : '';
		$row->area        = $decimal( $row->area );
		$row->area_living = isset( $row->area_living ) ? $decimal( $row->area_living ) : null;
		$row->area_summer = isset( $row->area_summer ) ? $decimal( $row->area_summer ) : null;
		$row->rooms       = ! isset( $row->rooms ) || null === $row->rooms ? null : (int) $row->rooms;
		$row->price_sqm   = $decimal( $row->price_sqm );
		$row->price_total = $decimal( $row->price_total );
		$row->i18n        = I18n::decode( $row->i18n );
		return $row;
	}

	/**
	 * Clears the in-request caches.
	 */
	public static function flush() {
		self::$cache     = array();
		self::$stats     = array();
		self::$child_ids = array();
	}
}
