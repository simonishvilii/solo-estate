<?php
/**
 * Activation, database schema and upgrades.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Install {

	/** Managers: everything, including deleting, statuses, settings and import. */
	const CAPABILITY = 'manage_solo_estate';

	/** Marketing / editors: add and change everything, but never delete. */
	const CAP_EDIT = 'edit_solo_estate';

	/** Sales: change statuses and read leads. */
	const CAP_SELL = 'sell_solo_estate';

	/** Access levels a WordPress role can be given, with the capabilities each one grants. */
	const LEVEL_CAPS = array(
		'manager'   => array( 'manage_solo_estate', 'edit_solo_estate', 'sell_solo_estate' ),
		'marketing' => array( 'edit_solo_estate', 'sell_solo_estate' ),
		'sales'     => array( 'sell_solo_estate' ),
	);

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Runs on every load; upgrades the schema when the stored version is older.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'solo_estate_db_version' ) !== SOLO_ESTATE_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Creates/updates tables, capabilities and default data.
	 */
	public static function install() {
		$from = (string) get_option( 'solo_estate_db_version', '' );
		self::create_tables();
		self::add_caps();
		self::seed_defaults();
		self::seed_project_statuses();
		if ( '' !== $from && version_compare( $from, '3', '<' ) ) {
			self::migrate_3();
		}
		if ( '' !== $from && version_compare( $from, '4', '<' ) ) {
			self::migrate_4();
		}
		if ( '' !== $from && version_compare( $from, '6', '<' ) ) {
			self::migrate_6();
		}
		if ( '' !== $from && version_compare( $from, '7', '<' ) ) {
			self::migrate_7();
		}
		if ( '' !== $from && version_compare( $from, '8', '<' ) ) {
			self::migrate_8();
		}
		update_option( 'solo_estate_db_version', SOLO_ESTATE_DB_VERSION );
	}

	/**
	 * Version 4: entrances are a field of the apartment, not a level (a floor plan covers the
	 * whole floor). Units inside an entrance get its number, its floors and parkings move up
	 * to the building, and the entrance item is removed.
	 */
	private static function migrate_4() {
		global $wpdb;

		$table = self::table( 'nodes' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$entrances = (array) $wpdb->get_results( "SELECT id, parent_id, number, path FROM $table WHERE level = 'entrance'" );
		foreach ( $entrances as $entrance ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "UPDATE $table SET entrance = %s WHERE path LIKE %s AND level IN ('flat','commercial') AND entrance = ''", mb_substr( (string) $entrance->number, 0, 50 ), $wpdb->esc_like( (string) $entrance->path ) . '%' ) );
			$children = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE parent_id = %d", (int) $entrance->id ) );
			// phpcs:enable
			foreach ( $children as $child ) {
				Nodes::save( array( 'parent_id' => (int) $entrance->parent_id ), (int) $child );
			}
			Nodes::delete( (int) $entrance->id );
		}
		Nodes::flush();
	}

	/**
	 * Version 3: paths for the flexible tree, the "sold" status flag, and reserved/rented
	 * apartments no longer open.
	 */
	private static function migrate_3() {
		Nodes::rebuild_paths();
		self::flag_statuses_by_title();
	}

	/**
	 * Version 6: sites whose apartment statuses came without the "sold" flag (an older import)
	 * showed 0% sold everywhere. When no apartment status is marked sold, mark them by name.
	 */
	private static function migrate_6() {
		global $wpdb;
		$table = self::table( 'statuses' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE scope = 'flat' AND sold = 1" ) ) {
			self::flag_statuses_by_title();
		}
	}

	/**
	 * Version 8: projects saved in the admin had their project_id reset to 0; a project is its
	 * own project.
	 */
	private static function migrate_8() {
		global $wpdb;
		$table = self::table( 'nodes' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE $table SET project_id = id WHERE level = 'project' AND project_id <> id" );
		Nodes::flush();
	}

	/**
	 * Version 7: the project status that sold-out projects move to (catalog tabs) is the one
	 * named Completed, unless one is already chosen.
	 */
	private static function migrate_7() {
		global $wpdb;
		$table = self::table( 'statuses' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE scope = 'project' AND sold = 1" ) ) {
			return;
		}
		$names = array( 'completed', 'დასრულებული', 'დასრულებულია', 'завершён', 'завершен', 'сдан' );
		foreach ( (array) $wpdb->get_results( "SELECT id, i18n FROM $table WHERE scope = 'project' ORDER BY sort_order, id" ) as $row ) {
			$i18n   = I18n::decode( $row->i18n );
			$titles = array_map(
				static function ( $t ) {
					return mb_strtolower( trim( (string) $t ) );
				},
				isset( $i18n['title'] ) ? (array) $i18n['title'] : array()
			);
			if ( array_intersect( $titles, $names ) ) {
				$wpdb->update( $table, array( 'sold' => 1 ), array( 'id' => (int) $row->id ) );
				break;
			}
		}
		// phpcs:enable
		Statuses::flush();
	}

	/**
	 * Apartment statuses named Sold get the "sold" flag (and close); Reserved / Rented close.
	 */
	private static function flag_statuses_by_title() {
		global $wpdb;

		$table = self::table( 'statuses' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = (array) $wpdb->get_results( "SELECT id, i18n FROM $table WHERE scope = 'flat'" );
		$sold   = array( 'sold', 'გაყიდულია', 'გაყიდული', 'продано', 'продана' );
		$closed = array( 'reserved', 'დაჯავშნილია', 'დაჯავშნილი', 'забронировано', 'rented', 'გაქირავებულია', 'გაქირავებული', 'сдано' );
		foreach ( $rows as $row ) {
			$i18n   = I18n::decode( $row->i18n );
			$titles = array_map(
				static function ( $t ) {
					return mb_strtolower( trim( (string) $t ) );
				},
				isset( $i18n['title'] ) ? (array) $i18n['title'] : array()
			);
			if ( array_intersect( $titles, $sold ) ) {
				$wpdb->update( $table, array( 'sold' => 1, 'clickable' => 0 ), array( 'id' => (int) $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} elseif ( array_intersect( $titles, $closed ) ) {
				$wpdb->update( $table, array( 'clickable' => 0 ), array( 'id' => (int) $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		Statuses::flush();
	}

	/**
	 * Table names keyed by short name.
	 *
	 * @return array<string,string>
	 */
	public static function tables() {
		global $wpdb;

		return array(
			'nodes'       => $wpdb->prefix . 'solo_estate_nodes',
			'statuses'    => $wpdb->prefix . 'solo_estate_statuses',
			'spec_fields' => $wpdb->prefix . 'solo_estate_spec_fields',
			'spec_values' => $wpdb->prefix . 'solo_estate_spec_values',
			'leads'       => $wpdb->prefix . 'solo_estate_leads',
		);
	}

	/**
	 * Table name helper.
	 *
	 * @param string $name Short table name.
	 * @return string
	 */
	public static function table( $name ) {
		$tables = self::tables();
		return $tables[ $name ];
	}

	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		// dbDelta needs: one column per line, two spaces after PRIMARY KEY, KEY instead of INDEX.
		$sql = "CREATE TABLE {$t['nodes']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
  project_id bigint(20) unsigned NOT NULL DEFAULT 0,
  path varchar(191) NOT NULL DEFAULT '',
  level varchar(20) NOT NULL DEFAULT '',
  number varchar(50) NOT NULL DEFAULT '',
  entrance varchar(50) NOT NULL DEFAULT '',
  status_id bigint(20) unsigned NOT NULL DEFAULT 0,
  access tinyint(1) unsigned NOT NULL DEFAULT 0,
  info_modal tinyint(1) unsigned NOT NULL DEFAULT 0,
  image_id bigint(20) unsigned NOT NULL DEFAULT 0,
  image2_id bigint(20) unsigned NOT NULL DEFAULT 0,
  gallery text NULL,
  tour_url varchar(255) NOT NULL DEFAULT '',
  coords text NULL,
  area decimal(10,2) NULL,
  area_living decimal(10,2) NULL,
  area_summer decimal(10,2) NULL,
  rooms tinyint(3) unsigned NULL,
  price_sqm decimal(12,2) NULL,
  price_total decimal(14,2) NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  i18n longtext NULL,
  legacy_id bigint(20) unsigned NULL,
  created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY  (id),
  KEY parent_id (parent_id),
  KEY path (path),
  KEY project_level (project_id,level),
  KEY legacy_id (legacy_id)
) $charset;
CREATE TABLE {$t['statuses']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  scope varchar(20) NOT NULL DEFAULT 'flat',
  color varchar(20) NOT NULL DEFAULT '#707d76',
  clickable tinyint(1) NOT NULL DEFAULT 1,
  available tinyint(1) NOT NULL DEFAULT 0,
  sold tinyint(1) NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT 0,
  i18n longtext NULL,
  legacy_id bigint(20) unsigned NULL,
  PRIMARY KEY  (id),
  KEY scope (scope)
) $charset;
CREATE TABLE {$t['spec_fields']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sort_order int(11) NOT NULL DEFAULT 0,
  highlight tinyint(1) NOT NULL DEFAULT 0,
  unit varchar(20) NOT NULL DEFAULT '',
  i18n longtext NULL,
  legacy_id bigint(20) unsigned NULL,
  PRIMARY KEY  (id)
) $charset;
CREATE TABLE {$t['spec_values']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  node_id bigint(20) unsigned NOT NULL DEFAULT 0,
  field_id bigint(20) unsigned NOT NULL DEFAULT 0,
  value varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY node_field (node_id,field_id),
  KEY field_id (field_id)
) $charset;
CREATE TABLE {$t['leads']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  name varchar(191) NOT NULL DEFAULT '',
  phone varchar(64) NOT NULL DEFAULT '',
  email varchar(191) NOT NULL DEFAULT '',
  message text NULL,
  node_id bigint(20) unsigned NOT NULL DEFAULT 0,
  project_id bigint(20) unsigned NOT NULL DEFAULT 0,
  lang varchar(20) NOT NULL DEFAULT '',
  page_url text NULL,
  ip varchar(64) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'new',
  PRIMARY KEY  (id),
  KEY created_at (created_at),
  KEY node_id (node_id)
) $charset;";

		dbDelta( $sql );
	}

	/**
	 * Administrators get every capability; three roles are created for staff who should
	 * only work in Solo Estate: Manager, Marketing and Sales.
	 */
	private static function add_caps() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::LEVEL_CAPS['manager'] as $cap ) {
				if ( ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
		}
		$roles = array(
			'solo_estate_manager'   => array( __( 'Solo Estate Manager', 'solo-estate' ), 'manager' ),
			'solo_estate_marketing' => array( __( 'Solo Estate Marketing', 'solo-estate' ), 'marketing' ),
			'solo_estate_sales'     => array( __( 'Solo Estate Sales', 'solo-estate' ), 'sales' ),
		);
		foreach ( $roles as $slug => $role ) {
			$caps = array( 'read' => true );
			if ( 'sales' !== $role[1] ) {
				$caps['upload_files'] = true;
			}
			foreach ( self::LEVEL_CAPS[ $role[1] ] as $cap ) {
				$caps[ $cap ] = true;
			}
			if ( ! get_role( $slug ) ) {
				add_role( $slug, $role[0], $caps );
			}
		}
	}

	/**
	 * Gives existing WordPress roles (e.g. Editor) a Solo Estate access level.
	 *
	 * @param array<string,string> $map role slug => manager|marketing|sales|'' (none).
	 */
	public static function apply_role_levels( array $map ) {
		$all = array_unique( call_user_func_array( 'array_merge', array_values( self::LEVEL_CAPS ) ) );
		foreach ( $map as $slug => $level ) {
			$role = get_role( $slug );
			if ( ! $role || 'administrator' === $slug || 0 === strpos( $slug, 'solo_estate_' ) ) {
				continue;
			}
			$grant = isset( self::LEVEL_CAPS[ $level ] ) ? self::LEVEL_CAPS[ $level ] : array();
			foreach ( $all as $cap ) {
				if ( in_array( $cap, $grant, true ) ) {
					$role->add_cap( $cap );
				} elseif ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Access level of a role.
	 *
	 * @param \WP_Role $role Role.
	 * @return string manager|marketing|sales|''.
	 */
	public static function role_level( $role ) {
		foreach ( self::LEVEL_CAPS as $level => $caps ) {
			if ( $role->has_cap( $caps[0] ) ) {
				return $level;
			}
		}
		return '';
	}

	/**
	 * Default statuses and spec fields, only when the tables are empty.
	 */
	private static function seed_defaults() {
		global $wpdb;

		$statuses = self::table( 'statuses' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM $statuses" ) ) {
			$defaults = array(
				// scope, color, clickable, available, sold, en, ka, ru.
				array( 'flat', '#3f9c6d', 1, 1, 0, 'For sale', 'იყიდება', 'В продаже' ),
				array( 'flat', '#906dd2', 1, 1, 0, 'For rent', 'ქირავდება', 'Сдаётся' ),
				array( 'flat', '#e3b53b', 0, 0, 0, 'Reserved', 'დაჯავშნილია', 'Забронировано' ),
				array( 'flat', '#c0504d', 0, 0, 1, 'Sold', 'გაყიდულია', 'Продано' ),
				array( 'flat', '#c08442', 0, 0, 0, 'Rented', 'გაქირავებულია', 'Сдано' ),
				array( 'floor', '#707d76', 1, 1, 0, 'For sale', 'იყიდება', 'В продаже' ),
				array( 'floor', '#f4e9b0', 0, 0, 0, 'Sold out', 'გაყიდულია', 'Продано' ),
				array( 'building', '#707d76', 1, 1, 0, 'For sale', 'იყიდება', 'В продаже' ),
				array( 'building', '#f4e9b0', 0, 0, 0, 'Sold out', 'გაყიდულია', 'Продано' ),
			);
			foreach ( $defaults as $i => $row ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$statuses,
					array(
						'scope'      => $row[0],
						'color'      => $row[1],
						'clickable'  => $row[2],
						'available'  => $row[3],
						'sold'       => $row[4],
						'sort_order' => $i + 1,
						'i18n'       => I18n::encode( array( 'title' => self::localized_defaults( $row[5], $row[6], $row[7] ) ) ),
					)
				);
			}
		}
	}

	/**
	 * Project statuses (ongoing / completed), added in schema version 2.
	 */
	private static function seed_project_statuses() {
		global $wpdb;

		$table = self::table( 'statuses' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE scope = 'project'" ) ) {
			return;
		}
		// Last value: sold-out projects move to this status in the catalog tabs.
		$defaults = array(
			array( '#be9645', 'Ongoing', 'მიმდინარე', 'Строится', 0 ),
			array( '#707d76', 'Completed', 'დასრულებული', 'Завершён', 1 ),
		);
		foreach ( $defaults as $i => $row ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'scope'      => 'project',
					'color'      => $row[0],
					'clickable'  => 1,
					'available'  => 0,
					'sold'       => $row[4],
					'sort_order' => $i + 1,
					'i18n'       => I18n::encode( array( 'title' => self::localized_defaults( $row[1], $row[2], $row[3] ) ) ),
				)
			);
		}
	}

	/**
	 * Maps the English/Georgian/Russian defaults onto the site's language codes.
	 *
	 * @param string $en English.
	 * @param string $ka Georgian.
	 * @param string $ru Russian.
	 * @return array<string,string>
	 */
	public static function localized_defaults( $en, $ka, $ru ) {
		$out = array();
		foreach ( I18n::languages() as $code => $lang ) {
			$prefix = strtolower( substr( $lang['locale'] ? $lang['locale'] : $code, 0, 2 ) );
			if ( 'ka' === $prefix || 'ge' === $code ) {
				$out[ $code ] = $ka;
			} elseif ( 'ru' === $prefix ) {
				$out[ $code ] = $ru;
			} else {
				$out[ $code ] = $en;
			}
		}
		return $out;
	}
}
