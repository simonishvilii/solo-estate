<?php
/**
 * Apartment filter: rooms, area, price, building, availability.
 *
 * Plain GET form rendered on the server, so results have shareable URLs and work without JS.
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

use SoloEstate\Install;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Statuses;

defined( 'ABSPATH' ) || exit;

class Search {

	const PER_PAGE = 24;

	/** Query args owned by the filter. */
	const ARGS = array( 'se_q', 'se_project', 'se_building', 'se_rooms', 'se_area_min', 'se_area_max', 'se_floor_min', 'se_floor_max', 'se_price_min', 'se_price_max', 'se_available', 'se_sort', 'se_page' );

	const SORTS = array( 'default', 'price_asc', 'price_desc', 'area_asc', 'area_desc' );

	/** Units the filter searches: apartments and villas. */
	const LEVELS = "'flat','villa'";

	/**
	 * Whether a search was submitted.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return isset( $_GET['se_q'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only filter.
	}

	/**
	 * Sanitized filter values from the URL.
	 *
	 * @return array
	 */
	public static function params() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public read-only filter.
		$num = static function ( $key ) {
			if ( ! isset( $_GET[ $key ] ) ) {
				return null;
			}
			$value = \SoloEstate\Csv::parse_number( sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) );
			return null === $value ? null : $value;
		};
		$rooms = isset( $_GET['se_rooms'] ) ? preg_replace( '/[^0-9+]/', '', (string) wp_unslash( $_GET['se_rooms'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- digits and + only.
		$sort  = isset( $_GET['se_sort'] ) ? sanitize_key( wp_unslash( $_GET['se_sort'] ) ) : 'default';

		$params = array(
			'project'   => isset( $_GET['se_project'] ) ? absint( $_GET['se_project'] ) : 0,
			'building'  => isset( $_GET['se_building'] ) ? absint( $_GET['se_building'] ) : 0,
			'rooms'     => preg_match( '/^\d{1,2}\+?$/', $rooms ) ? $rooms : '',
			'area_min'  => $num( 'se_area_min' ),
			'area_max'  => $num( 'se_area_max' ),
			'floor_min' => null === $num( 'se_floor_min' ) ? null : (int) $num( 'se_floor_min' ),
			'floor_max' => null === $num( 'se_floor_max' ) ? null : (int) $num( 'se_floor_max' ),
			'price_min' => $num( 'se_price_min' ),
			'price_max' => $num( 'se_price_max' ),
			'available' => self::is_active() ? ! empty( $_GET['se_available'] ) : true,
			'sort'      => in_array( $sort, self::SORTS, true ) ? $sort : 'default',
			'page'      => isset( $_GET['se_page'] ) ? max( 1, absint( $_GET['se_page'] ) ) : 1,
		);
		// phpcs:enable
		return $params;
	}

	/**
	 * Results page (form + list).
	 *
	 * @param object|null $project Fixed project, or null in the catalog.
	 * @return string
	 */
	public static function render( $project ) {
		$params = self::params();
		if ( $project ) {
			$params['project'] = $project->id;
		}
		$result = self::query( $params );

		return Renderer::template(
			'search',
			array(
				'project' => $project,
				'params'  => $params,
				'rows'    => $result['rows'],
				'total'   => $result['total'],
				'pages'   => (int) ceil( $result['total'] / self::PER_PAGE ),
			)
		);
	}

	/**
	 * The filter form.
	 *
	 * @param object|null $project Fixed project (hidden field), or null to let visitors choose.
	 * @param bool        $open    Render expanded.
	 * @return string
	 */
	public static function form( $project, $open = false, array $defaults = array() ) {
		if ( ! Settings::get( 'show_filter' ) ) {
			return '';
		}
		// Preset values (e.g. this building on a building page) until the visitor searches.
		$params = self::is_active() ? self::params() : array_merge( self::params(), $defaults );
		return Renderer::template(
			'search-form',
			array(
				'project' => $project,
				'params'  => $params,
				'open'    => $open || self::is_active(),
				'options' => self::options( $project ),
			)
		);
	}

	/**
	 * Whether a project or building page shows the apartment search: not on a completed
	 * project (status marked "Sold-out projects move here") and not when nothing is available.
	 *
	 * @param object|null $project Project.
	 * @param object      $node    Project, phase or building on screen.
	 * @return bool
	 */
	public static function shown_on( $project, $node ) {
		if ( ! $project ) {
			return false;
		}
		$status = Renderer::project_status( $project );
		return ! ( $status && $status->sold ) && Nodes::stats( $node )['available'] > 0;
	}

	/**
	 * Choices for the form: projects, buildings, room counts, area and price ranges.
	 *
	 * @param object|null $project Project scope.
	 * @return array
	 */
	public static function options( $project ) {
		global $wpdb;

		$table = Install::table( 'nodes' );
		$where = $project ? $wpdb->prepare( 'project_id = %d AND ', $project->id ) : '';
		$price = self::price_sql( '' );

		$levels = self::LEVELS;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rooms = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT rooms FROM $table WHERE {$where}level IN ($levels) AND rooms IS NOT NULL ORDER BY rooms" ) );
		$range = $wpdb->get_row( "SELECT MIN(area) amin, MAX(area) amax, MIN($price) pmin, MAX($price) pmax FROM $table WHERE {$where}level IN ($levels)" );
		$ids   = (array) $wpdb->get_col( "SELECT id FROM $table WHERE {$where}level = 'building' ORDER BY project_id, sort_order, id" );
		$floor = $wpdb->get_row( "SELECT MIN(CAST(number AS SIGNED)) fmin, MAX(CAST(number AS SIGNED)) fmax FROM $table WHERE {$where}level = 'floor' AND number <> ''" );
		// phpcs:enable

		// Buildings with apartments, also those inside phases ("Phase I — Block A").
		$buildings = array();
		foreach ( array_filter( array_map( array( Nodes::class, 'get' ), $ids ) ) as $building ) {
			if ( ! Nodes::flat_count( $building ) ) {
				continue;
			}
			$phase       = Nodes::ancestor( $building, 'phase' );
			$buildings[] = array(
				'id'      => $building->id,
				'project' => $building->project_id,
				'label'   => ( $phase ? Nodes::display_title( $phase ) . ' — ' : '' ) . Nodes::display_title( $building ),
			);
		}

		return array(
			'projects'  => $project ? array() : Nodes::projects(),
			'buildings' => $buildings,
			'rooms'     => $rooms,
			'area'      => array( $range && null !== $range->amin ? floor( (float) $range->amin ) : null, $range && null !== $range->amax ? ceil( (float) $range->amax ) : null ),
			'price'     => array( $range && null !== $range->pmin ? floor( (float) $range->pmin * self::price_factor() ) : null, $range && null !== $range->pmax ? ceil( (float) $range->pmax * self::price_factor() ) : null ),
			'symbol'    => self::price_symbol(),
			// Floor range; no field when there is only one floor number.
			'floor'     => $floor && null !== $floor->fmin && (int) $floor->fmin !== (int) $floor->fmax ? array( (int) $floor->fmin, (int) $floor->fmax ) : null,
		);
	}

	/**
	 * Runs the search.
	 *
	 * @param array $p Params.
	 * @return array{rows:object[],total:int}
	 */
	public static function query( array $p ) {
		global $wpdb;

		$t      = Install::table( 'nodes' );
		$price  = self::price_sql( 'fl.' );
		$where  = array(
			'fl.level IN (' . self::LEVELS . ')',
			'fl.access <> ' . (int) Nodes::ACCESS_CLOSED,
			// Not a unit left inside another unit by an old import (Nodes::misplaced()).
			"NOT EXISTS (SELECT 1 FROM $t pu WHERE pu.id = fl.parent_id AND pu.level IN ('" . implode( "','", Nodes::UNITS ) . "'))",
		);
		$params = array();

		if ( $p['project'] ) {
			$where[]  = 'fl.project_id = %d';
			$params[] = $p['project'];
		}
		if ( $p['building'] ) {
			$where[]  = 'fl.path LIKE %s';
			$params[] = '%/' . (int) $p['building'] . '/%';
		}
		if ( '' !== $p['rooms'] ) {
			$where[]  = '+' === substr( $p['rooms'], -1 ) ? 'fl.rooms >= %d' : 'fl.rooms = %d';
			$params[] = (int) $p['rooms'];
		}
		// Floor by number of the apartment's floor (villas have none, so they drop out).
		foreach ( array( 'floor_min' => "f.level = 'floor' AND CAST(f.number AS SIGNED) >= %d", 'floor_max' => "f.level = 'floor' AND CAST(f.number AS SIGNED) <= %d" ) as $key => $sql ) {
			if ( null !== $p[ $key ] ) {
				$where[]  = $sql;
				$params[] = $p[ $key ];
			}
		}
		foreach ( array( 'area_min' => 'fl.area >= %f', 'area_max' => 'fl.area <= %f' ) as $key => $sql ) {
			if ( null !== $p[ $key ] ) {
				$where[]  = $sql;
				$params[] = $p[ $key ];
			}
		}
		// Prices are typed in the currency shown first on the site; stored prices are in base currency.
		$factor = self::price_factor();
		foreach ( array( 'price_min' => "$price >= %f", 'price_max' => "$price <= %f" ) as $key => $sql ) {
			if ( null !== $p[ $key ] ) {
				$where[]  = $sql;
				$params[] = $p[ $key ] / $factor;
			}
		}

		// Never list apartments visitors cannot open; "only available" narrows to available statuses.
		$closed = array();
		foreach ( Statuses::for_scope( 'flat' ) as $status ) {
			if ( ! $status->clickable ) {
				$closed[] = (int) $status->id;
			}
		}
		if ( $closed ) {
			$where[] = 'fl.status_id NOT IN (' . implode( ',', $closed ) . ')';
		}
		if ( $p['available'] ) {
			$ids     = Statuses::available_ids( 'flat' );
			$where[] = $ids ? 'fl.status_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' : '1 = 0';
		}

		$orders = array(
			'default'    => 'fl.project_id, f.parent_id, f.sort_order, CAST(f.number AS SIGNED), CAST(fl.number AS SIGNED), fl.id',
			'price_asc'  => "$price IS NULL, $price ASC, fl.id",
			'price_desc' => "$price IS NULL, $price DESC, fl.id",
			'area_asc'   => 'fl.area IS NULL, fl.area ASC, fl.id',
			'area_desc'  => 'fl.area IS NULL, fl.area DESC, fl.id',
		);
		$from   = "FROM $t fl LEFT JOIN $t f ON f.id = fl.parent_id WHERE " . implode( ' AND ', $where );
		$offset = ( $p['page'] - 1 ) * self::PER_PAGE;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) $from";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );
		$rows_sql  = "SELECT fl.id $from ORDER BY {$orders[ $p['sort'] ]} LIMIT %d OFFSET %d";
		$ids       = $wpdb->get_col( $wpdb->prepare( $rows_sql, array_merge( $params, array( self::PER_PAGE, $offset ) ) ) );
		// phpcs:enable

		return array(
			'rows'  => array_values( array_filter( array_map( array( Nodes::class, 'get' ), (array) $ids ) ) ),
			'total' => $total,
		);
	}

	/**
	 * Multiplier from base currency to the currency visitors see first.
	 *
	 * @return float
	 */
	public static function price_factor() {
		$rate = \SoloEstate\Rates::rate();
		return ( Settings::get( 'alt_enabled' ) && 'alt' === Settings::get( 'default_currency' ) && $rate > 0 ) ? $rate : 1.0;
	}

	/**
	 * Symbol of the currency used in the price filter.
	 *
	 * @return string
	 */
	public static function price_symbol() {
		return 1.0 === self::price_factor() ? (string) Settings::get( 'base_symbol' ) : (string) Settings::get( 'alt_symbol' );
	}

	/**
	 * SQL expression for the total price in base currency.
	 *
	 * @param string $alias Table alias with dot, or ''.
	 * @return string
	 */
	private static function price_sql( $alias ) {
		return "COALESCE(NULLIF({$alias}price_total, 0), {$alias}area * {$alias}price_sqm)";
	}

	/**
	 * URL of another results page, keeping the filters.
	 *
	 * @param int $page Page number.
	 * @return string
	 */
	public static function page_url( $page ) {
		return add_query_arg( 'se_page', (int) $page ) . '#' . self::anchor();
	}

	/**
	 * Anchor of the app element.
	 *
	 * @return string
	 */
	private static function anchor() {
		return wp_parse_url( Renderer::root_url(), PHP_URL_FRAGMENT );
	}

	/**
	 * Hidden inputs that keep unrelated query args (e.g. ?lang=, ?page_id=) when the form is submitted.
	 *
	 * @return string
	 */
	public static function hidden_inputs() {
		$out = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( $_GET as $key => $value ) {
			$key = sanitize_key( $key );
			if ( in_array( $key, self::ARGS, true ) || Renderer::QUERY_ARG === $key || is_array( $value ) ) {
				continue;
			}
			$out .= sprintf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( $key ), esc_attr( sanitize_text_field( wp_unslash( $value ) ) ) );
		}
		return $out;
	}
}
