<?php
/**
 * Rooms and sections of units (bedrooms, kitchen, terraces, …): a shared list of fields
 * and per-unit values.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Specs {

	/** @var object[]|null */
	private static $fields = null;

	/**
	 * All fields ordered by sort.
	 *
	 * @return object[] Keyed by id.
	 */
	public static function fields() {
		global $wpdb;

		if ( null === self::$fields ) {
			$table = Install::table( 'spec_fields' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows         = (array) $wpdb->get_results( "SELECT * FROM $table ORDER BY sort_order ASC, id ASC" );
			self::$fields = array();
			foreach ( $rows as $row ) {
				$row->id                   = (int) $row->id;
				$row->sort_order           = (int) $row->sort_order;
				$row->highlight            = (int) $row->highlight;
				$row->i18n                 = I18n::decode( $row->i18n );
				self::$fields[ $row->id ] = $row;
			}
		}
		return self::$fields;
	}

	/**
	 * Single field.
	 *
	 * @param int $id Field id.
	 * @return object|null
	 */
	public static function field( $id ) {
		$fields = self::fields();
		return isset( $fields[ (int) $id ] ) ? $fields[ (int) $id ] : null;
	}

	/**
	 * Field whose name matches in any language (case-insensitive), or a new one at the end
	 * of the list. Lets editors add "Bedroom 3" right on an apartment, like WooCommerce
	 * attributes: once added, it can be filled on every unit.
	 *
	 * @param string $title Name in the default language.
	 * @param string $unit  Unit, e.g. m²; empty = the global area unit.
	 * @return int Field id.
	 */
	public static function find_or_create( $title, $unit = '' ) {
		$needle = mb_strtolower( trim( $title ) );
		$max    = 0;
		foreach ( self::fields() as $field ) {
			$max = max( $max, $field->sort_order );
			foreach ( isset( $field->i18n['title'] ) ? (array) $field->i18n['title'] : array() as $name ) {
				if ( mb_strtolower( trim( (string) $name ) ) === $needle ) {
					return $field->id;
				}
			}
		}
		$unit = mb_substr( trim( $unit ), 0, 20 );
		return self::save_field(
			array(
				'sort_order' => $max + 1,
				'highlight'  => 0,
				'unit'       => $unit === (string) Settings::get( 'area_unit' ) ? '' : $unit,
				'i18n'       => array( 'title' => array( I18n::default_code() => trim( $title ) ) ),
			)
		);
	}

	/**
	 * Translated field title.
	 *
	 * @param object      $field Field.
	 * @param string|null $lang  Language.
	 * @return string
	 */
	public static function title( $field, $lang = null ) {
		return isset( $field->i18n['title'] ) ? I18n::pick( $field->i18n['title'], $lang ) : '';
	}

	/**
	 * Unit for a field, falling back to the global area unit.
	 *
	 * @param object $field Field.
	 * @return string
	 */
	public static function unit( $field ) {
		return '' !== $field->unit ? $field->unit : (string) Settings::get( 'area_unit' );
	}

	/**
	 * Inserts or updates a field.
	 *
	 * @param array $data Data (`i18n` as array).
	 * @param int   $id   Existing id.
	 * @return int
	 */
	public static function save_field( array $data, $id = 0 ) {
		global $wpdb;

		$table = Install::table( 'spec_fields' );
		$row   = array_intersect_key( $data, array_flip( array( 'sort_order', 'highlight', 'unit', 'i18n', 'legacy_id' ) ) );
		if ( isset( $row['i18n'] ) && is_array( $row['i18n'] ) ) {
			$row['i18n'] = I18n::encode( $row['i18n'] );
		}
		self::$fields = null;

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $id;
		}
		$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->insert_id;
	}

	/**
	 * Deletes a field and its values.
	 *
	 * @param int $id Field id.
	 */
	public static function delete_field( $id ) {
		global $wpdb;

		$wpdb->delete( Install::table( 'spec_fields' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Install::table( 'spec_values' ), array( 'field_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::$fields = null;
	}

	/**
	 * Values of a node keyed by field id.
	 *
	 * @param int $node_id Node id.
	 * @return array<int,string>
	 */
	public static function values( $node_id ) {
		global $wpdb;

		$table = Install::table( 'spec_values' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT field_id, value FROM $table WHERE node_id = %d", (int) $node_id ) );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->field_id ] = (string) $row->value;
		}
		return $out;
	}

	/**
	 * Replaces the values of a node. Empty values are removed.
	 *
	 * @param int                $node_id Node id.
	 * @param array<int,string> $values  field id => value.
	 */
	public static function save_values( $node_id, array $values ) {
		global $wpdb;

		$table = Install::table( 'spec_values' );
		$wpdb->delete( $table, array( 'node_id' => (int) $node_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $values as $field_id => $value ) {
			$value = trim( (string) $value );
			if ( '' === $value || ! (int) $field_id ) {
				continue;
			}
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'node_id'  => (int) $node_id,
					'field_id' => (int) $field_id,
					'value'    => mb_substr( $value, 0, 100 ),
				)
			);
		}
	}

	/**
	 * Clears the in-request cache.
	 */
	public static function flush() {
		self::$fields = null;
	}
}
