<?php
/**
 * CSV (Excel) export and import of units: apartments, commercial spaces, villas and
 * parking spaces.
 *
 * One row per unit: the id, or phase / building / floor and unit numbers identify it, and
 * status, entrance, areas, prices and specification values can be changed in bulk.
 * Empty cells leave a value unchanged; a single "-" clears it.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Csv {

	const CLEAR    = '-';
	const MAX_ROWS = 20000;

	/** Location columns, then value columns. */
	const COLUMNS = array( 'project', 'id', 'type', 'phase', 'building', 'entrance', 'floor', 'unit', 'status', 'rooms', 'area', 'area_living', 'area_summer', 'price_sqm', 'price_total', 'tour_url' );

	/** Numeric value columns. */
	const NUMBERS = array( 'area', 'area_living', 'area_summer', 'price_sqm', 'price_total' );

	/** Unit types in the file. */
	const TYPES = array( 'flat', 'commercial', 'villa', 'spot' );

	/**
	 * Header row of an export.
	 *
	 * @return string[]
	 */
	public static function header() {
		$cols = self::COLUMNS;
		foreach ( Specs::fields() as $field ) {
			$cols[] = 'spec_' . $field->id . ': ' . Specs::title( $field, I18n::default_code() );
		}
		return $cols;
	}

	/**
	 * Rows of every unit in a project, in tree order (phase → building → floor → unit).
	 *
	 * @param object $project Project.
	 * @return array<int,array<int,string>>
	 */
	public static function rows( $project ) {
		$lang   = I18n::default_code();
		$fields = Specs::fields();
		$rows   = array();
		$name   = Nodes::display_title( $project, $lang );
		foreach ( self::units( $project ) as $unit ) {
			$by = array();
			foreach ( Nodes::ancestors( $unit ) as $item ) {
				$by[ $item->level ] = $item;
			}
			// A parking space sits in a parking; its number goes to the floor column.
			$floor  = isset( $by['floor'] ) ? $by['floor'] : ( isset( $by['parking'] ) ? $by['parking'] : null );
			$values = Specs::values( $unit->id );
			$row    = array(
				$name,
				(string) $unit->id,
				$unit->level,
				isset( $by['phase'] ) ? (string) $by['phase']->number : '',
				isset( $by['building'] ) ? (string) $by['building']->number : '',
				(string) $unit->entrance,
				$floor ? (string) $floor->number : '',
				(string) $unit->number,
				Statuses::title( Statuses::get( $unit->status_id ), $lang ),
				null === $unit->rooms ? '' : (string) $unit->rooms,
				self::num( $unit->area ),
				self::num( $unit->area_living ),
				self::num( $unit->area_summer ),
				self::num( $unit->price_sqm ),
				self::num( $unit->price_total ),
				(string) $unit->tour_url,
			);
			foreach ( $fields as $field ) {
				$row[] = isset( $values[ $field->id ] ) ? $values[ $field->id ] : '';
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Units of a project in tree order: siblings sorted naturally by number.
	 *
	 * @param object $node Project or container.
	 * @return object[]
	 */
	private static function units( $node ) {
		$out = array();
		foreach ( Nodes::sorted_children( $node->id ) as $child ) {
			if ( in_array( $child->level, self::TYPES, true ) ) {
				$out[] = $child;
			} elseif ( 'villa_floor' !== $child->level ) {
				$out = array_merge( $out, self::units( $child ) );
			}
		}
		return $out;
	}

	/**
	 * Streams a CSV download of one or more projects and exits.
	 * UTF-8 with BOM so Excel shows Georgian/Russian correctly.
	 *
	 * @param object[] $projects Projects.
	 */
	public static function download( array $projects ) {
		$slug = 1 === count( $projects ) ? sanitize_title( Nodes::admin_title( $projects[0] ) ) : 'projects';
		$name = sanitize_file_name( 'solo-estate-' . $slug . '-' . gmdate( 'Y-m-d' ) . '.csv' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $name );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, self::header(), ',', '"', '' );
		foreach ( $projects as $project ) {
			foreach ( self::rows( $project ) as $row ) {
				fputcsv( $out, array_map( array( __CLASS__, 'cell' ), $row ), ',', '"', '' );
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Imports (or previews) a CSV file.
	 *
	 * Rows go to the project named in the `project` column (created when it does not exist
	 * and $create is on), or all to $project when one is given.
	 *
	 * @param string      $file    Path of the uploaded file.
	 * @param object|null $project Fixed target project, or null to use the project column.
	 * @param bool        $apply   Write changes (false = preview only).
	 * @param bool        $create  Create missing projects, buildings, floors and apartments.
	 * @return array{created:array,updated:int,unchanged:int,errors:array,rows:int}
	 */
	public static function import( $file, $project, $apply, $create ) {
		$report = array(
			'created'   => array(
				'project'  => 0,
				'phase'    => 0,
				'building' => 0,
				'floor'    => 0,
				'flat'     => 0,
			),
			'updated'   => 0,
			'unchanged' => 0,
			'errors'    => array(),
			'rows'      => 0,
		);

		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			$report['errors'][] = array( 0, __( 'The file could not be read.', 'solo-estate' ) );
			return $report;
		}

		$first = (string) fgets( $handle );
		$first = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		// Excel saves with ";" in many European locales.
		$delim  = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';
		$header = array_map( 'trim', str_getcsv( $first, $delim, '"', '' ) );
		$map    = self::map_header( $header );

		if ( ! isset( $map['unit'] ) || ( ! isset( $map['id'] ) && ( ! isset( $map['building'] ) || ! isset( $map['floor'] ) ) && ! isset( $map['type'] ) ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$report['errors'][] = array( 1, __( 'The first row must contain the column names: building, floor, unit (or id). Export a file first to get the right format.', 'solo-estate' ) );
			return $report;
		}
		if ( ! $project && ! isset( $map['project'] ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$report['errors'][] = array( 1, __( 'The file has no "project" column. Choose the project to import into, or add the column.', 'solo-estate' ) );
			return $report;
		}
		$projects = array(); // Lower-cased project name => project (null = would be created, in a preview).

		$statuses = self::status_lookup();
		$fields   = Specs::fields();
		$virtual  = array(); // Buildings/floors already created (or, in a preview, that would be).
		$line     = 1;

		while ( ( $cells = fgetcsv( $handle, 0, $delim, '"', '' ) ) !== false ) {
			$line++;
			if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
				continue;
			}
			if ( ++$report['rows'] > self::MAX_ROWS ) {
				$report['errors'][] = array( $line, __( 'Too many rows; the rest were skipped.', 'solo-estate' ) );
				break;
			}
			$get = static function ( $key ) use ( $map, $cells ) {
				$value = isset( $map[ $key ], $cells[ $map[ $key ] ] ) ? trim( (string) $cells[ $map[ $key ] ] ) : '';
				// Undo the quote added on export against formula injection.
				if ( strlen( $value ) > 1 && "'" === $value[0] && in_array( $value[1], array( '=', '+', '-', '@' ), true ) ) {
					$value = substr( $value, 1 );
				}
				return $value;
			};

			// Which project the row belongs to (created when missing).
			if ( $project ) {
				$target = $project;
				$p_key  = 'p' . $project->id;
			} else {
				$p_name = mb_substr( sanitize_text_field( $get( 'project' ) ), 0, 190 );
				if ( '' === $p_name ) {
					$report['errors'][] = array( $line, __( 'The project name is empty.', 'solo-estate' ) );
					continue;
				}
				$p_key = mb_strtolower( $p_name );
				if ( ! array_key_exists( $p_key, $projects ) ) {
					$found = self::find_project( $p_name );
					if ( $found ) {
						$projects[ $p_key ] = $found;
					} elseif ( ! $create ) {
						/* translators: %s: project name */
						$report['errors'][] = array( $line, sprintf( __( 'Project "%s" not found.', 'solo-estate' ), $p_name ) );
						continue;
					} else {
						$projects[ $p_key ] = $apply ? Nodes::get(
							Nodes::save(
								array(
									'parent_id' => 0,
									'level'     => 'project',
									'number'    => '',
									'i18n'      => array( 'title' => array( I18n::default_code() => $p_name ) ),
								)
							)
						) : null;
						// Counted only when it was really created (or would be, in a preview).
						if ( $apply && ! $projects[ $p_key ] ) {
							unset( $projects[ $p_key ] );
							/* translators: %s: project name */
							$report['errors'][] = array( $line, sprintf( __( 'Project "%s" could not be created.', 'solo-estate' ), $p_name ) );
							continue;
						}
						$report['created']['project']++;
					}
				}
				$target = $projects[ $p_key ];
			}

			// Find (or create) the unit.
			$flat   = null;
			$is_new = false;
			$type   = strtolower( $get( 'type' ) );
			$type   = in_array( $type, self::TYPES, true ) ? $type : 'flat';
			$id     = absint( $get( 'id' ) );
			if ( $id && $target ) {
				$flat = Nodes::get( $id );
				if ( ! $flat || ! in_array( $flat->level, self::TYPES, true ) || $flat->project_id !== $target->id ) {
					$flat = null;
				}
			}
			if ( ! $flat ) {
				$u_num = self::text( $get( 'unit' ) );
				if ( '' === $u_num ) {
					$report['errors'][] = array( $line, __( 'Building, floor and unit numbers are required when there is no id.', 'solo-estate' ) );
					continue;
				}
				if ( 'spot' === $type ) {
					$report['errors'][] = array( $line, __( 'Parking spaces can only be updated by id. Add new ones in wp-admin.', 'solo-estate' ) );
					continue;
				}

				// Walk down: phase (optional) → building → floor.
				// Villas sit directly in the project or phase.
				$steps = array( 'phase' );
				if ( 'villa' !== $type ) {
					$steps = array( 'phase', 'building', 'floor' );
				}
				$parent = $target;
				$key    = $p_key;
				$failed = false;
				foreach ( $steps as $level ) {
					$num = self::text( $get( $level ) );
					if ( '' === $num ) {
						if ( in_array( $level, array( 'building', 'floor' ), true ) ) {
							$report['errors'][] = array( $line, __( 'Building, floor and unit numbers are required when there is no id.', 'solo-estate' ) );
							$failed = true;
							break;
						}
						continue; // Optional level not used here.
					}
					$key .= '/' . $level . ':' . $num;
					$node = $parent ? self::find_child( $parent->id, $num, $level ) : null;
					if ( ! $node ) {
						if ( ! $create ) {
							/* translators: 1: e.g. Building, 2: number */
							$report['errors'][] = array( $line, sprintf( __( '%1$s %2$s not found.', 'solo-estate' ), Nodes::level_label( $level ), $num ) );
							$failed = true;
							break;
						}
						if ( ! isset( $virtual[ $key ] ) ) {
							$virtual[ $key ] = ( $apply && $parent ) ? Nodes::save( array( 'parent_id' => $parent->id, 'level' => $level, 'number' => $num ) ) : -1;
							if ( $virtual[ $key ] ) {
								$report['created'][ $level ]++;
							}
						}
						$node = $apply ? Nodes::get( $virtual[ $key ] ) : null;
						if ( $apply && ! $node ) {
							unset( $virtual[ $key ] );
							/* translators: 1: e.g. Building, 2: number */
							$report['errors'][] = array( $line, sprintf( __( '%1$s %2$s could not be created.', 'solo-estate' ), Nodes::level_label( $level ), $num ) );
							$failed = true;
							break;
						}
					}
					$parent = $node;
				}
				if ( $failed ) {
					continue;
				}

				$flat = $parent ? self::find_child( $parent->id, $u_num, $type ) : null;
				if ( ! $flat ) {
					if ( ! $create ) {
						/* translators: 1: e.g. Apartment, 2: number */
						$report['errors'][] = array( $line, sprintf( __( '%1$s %2$s not found.', 'solo-estate' ), Nodes::level_label( $type ), $u_num ) );
						continue;
					}
					$is_new = true;
					if ( $apply ) {
						$flat = ( $parent && Nodes::allows( $parent->level, $type ) ) ? Nodes::get( Nodes::save( array( 'parent_id' => $parent->id, 'level' => $type, 'number' => $u_num ) ) ) : null;
						if ( ! $flat ) {
							$report['errors'][] = array( $line, __( 'Could not create the apartment.', 'solo-estate' ) );
							continue;
						}
					}
					$report['created']['flat']++;
					if ( ! $apply ) {
						// Preview: validate the row against an empty unit.
						$flat = (object) array(
							'id'          => 0,
							'status_id'   => 0,
							'rooms'       => null,
							'area'        => null,
							'area_living' => null,
							'area_summer' => null,
							'price_sqm'   => null,
							'price_total' => null,
							'tour_url'    => '',
							'entrance'    => '',
						);
					}
				}
			}

			// Collect changes.
			$data   = array();
			$status = $get( 'status' );
			if ( '' !== $status ) {
				$key = mb_strtolower( $status );
				if ( isset( $statuses[ $key ] ) ) {
					$data['status_id'] = $statuses[ $key ];
				} elseif ( self::CLEAR === $status ) {
					$data['status_id'] = 0;
				} else {
					/* translators: %s: status name */
					$report['errors'][] = array( $line, sprintf( __( 'Unknown status "%s" — status left unchanged.', 'solo-estate' ), $status ) );
				}
			}
			$rooms = $get( 'rooms' );
			if ( '' !== $rooms ) {
				if ( self::CLEAR === $rooms ) {
					$data['rooms'] = null;
				} elseif ( ctype_digit( $rooms ) && (int) $rooms <= 20 ) {
					$data['rooms'] = (int) $rooms;
				} else {
					/* translators: %s: value */
					$report['errors'][] = array( $line, sprintf( __( 'Column rooms: "%s" must be a whole number 0–20.', 'solo-estate' ), $rooms ) );
				}
			}
			$entrance = $get( 'entrance' );
			if ( '' !== $entrance ) {
				$data['entrance'] = self::CLEAR === $entrance ? '' : self::text( $entrance );
			}
			$tour = $get( 'tour_url' );
			if ( '' !== $tour ) {
				$data['tour_url'] = self::CLEAR === $tour ? '' : mb_substr( esc_url_raw( $tour, array( 'https', 'http' ) ), 0, 255 );
			}
			foreach ( self::NUMBERS as $col ) {
				$raw = $get( $col );
				if ( '' === $raw ) {
					continue;
				}
				if ( self::CLEAR === $raw ) {
					$data[ $col ] = null;
					continue;
				}
				$num = self::parse_number( $raw );
				if ( null === $num ) {
					/* translators: 1: column, 2: value */
					$report['errors'][] = array( $line, sprintf( __( 'Column %1$s: "%2$s" is not a number.', 'solo-estate' ), $col, $raw ) );
					continue;
				}
				$data[ $col ] = $num;
			}

			$old_specs = $flat->id ? Specs::values( $flat->id ) : array();
			$specs     = $old_specs;
			foreach ( $fields as $field ) {
				$raw = $get( 'spec_' . $field->id );
				if ( '' === $raw ) {
					continue;
				}
				if ( self::CLEAR === $raw ) {
					unset( $specs[ $field->id ] );
				} else {
					$specs[ $field->id ] = mb_substr( sanitize_text_field( $raw ), 0, 100 );
				}
			}

			$changed = array_filter(
				$data,
				static function ( $value, $col ) use ( $flat ) {
					$old = $flat->$col;
					return is_float( $value ) || is_float( $old ) ? (float) $old !== (float) $value || ( null === $old ) !== ( null === $value ) : $old !== $value;
				},
				ARRAY_FILTER_USE_BOTH
			);
			$specs_changed = $specs != $old_specs; // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- order-insensitive compare.

			if ( ! $is_new && ! $changed && ! $specs_changed ) {
				$report['unchanged']++;
				continue;
			}
			if ( $apply ) {
				if ( $changed && ! Nodes::save( $changed, $flat->id ) ) {
					$report['errors'][] = array( $line, __( 'Could not save the changes of this row.', 'solo-estate' ) );
					continue;
				}
				if ( $specs_changed ) {
					Specs::save_values( $flat->id, $specs );
				}
			}
			// Counted after the write succeeded (or would, in a preview).
			if ( ! $is_new ) {
				$report['updated']++;
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		Nodes::flush();

		return $report;
	}

	/**
	 * Column name → index. Spec columns are recognised by their "spec_ID" prefix.
	 *
	 * @param string[] $header Header cells.
	 * @return array<string,int>
	 */
	private static function map_header( array $header ) {
		$map = array();
		foreach ( $header as $i => $name ) {
			$key = strtolower( trim( $name ) );
			if ( preg_match( '/^spec_(\d+)/', $key, $m ) ) {
				$map[ 'spec_' . $m[1] ] = $i;
			} elseif ( in_array( $key, self::COLUMNS, true ) ) {
				$map[ $key ] = $i;
			}
		}
		return $map;
	}

	/**
	 * Apartment statuses by lower-cased title in every language, and by id.
	 *
	 * @return array<string,int>
	 */
	private static function status_lookup() {
		$out = array();
		foreach ( Statuses::for_scope( 'flat' ) as $status ) {
			$out[ (string) $status->id ] = $status->id;
			foreach ( isset( $status->i18n['title'] ) ? (array) $status->i18n['title'] : array() as $title ) {
				$out[ mb_strtolower( trim( $title ) ) ] = $status->id;
			}
		}
		return $out;
	}

	/**
	 * Existing project whose name matches in any language (case-insensitive).
	 *
	 * @param string $name Name.
	 * @return object|null
	 */
	private static function find_project( $name ) {
		$needle = mb_strtolower( trim( $name ) );
		foreach ( Nodes::projects() as $p ) {
			$titles = isset( $p->i18n['title'] ) ? (array) $p->i18n['title'] : array();
			foreach ( $titles as $title ) {
				if ( mb_strtolower( trim( (string) $title ) ) === $needle ) {
					return $p;
				}
			}
		}
		return null;
	}

	/**
	 * Child with the given number (and level).
	 *
	 * @param int         $parent_id Parent.
	 * @param string      $number    Number.
	 * @param string|null $level     Level.
	 * @return object|null
	 */
	private static function find_child( $parent_id, $number, $level = null ) {
		foreach ( Nodes::children( $parent_id, $level ) as $child ) {
			if ( (string) $child->number === (string) $number ) {
				return $child;
			}
		}
		return null;
	}

	/**
	 * Short text cell (numbers of buildings, floors, units).
	 *
	 * @param string $value Cell.
	 * @return string
	 */
	private static function text( $value ) {
		return mb_substr( sanitize_text_field( $value ), 0, 50 );
	}

	/**
	 * Parses "1 250,50" / "1,250.50" / "1250.5" into a float.
	 *
	 * @param string $raw Cell.
	 * @return float|null
	 */
	public static function parse_number( $raw ) {
		$raw = str_replace( array( ' ', "\xC2\xA0" ), '', (string) $raw );
		if ( preg_match( '/^\d{1,3}(,\d{3})+(\.\d+)?$/', $raw ) ) {
			$raw = str_replace( ',', '', $raw ); // 1,250.50
		} elseif ( preg_match( '/^\d{1,3}(\.\d{3})+(,\d+)?$/', $raw ) ) {
			$raw = str_replace( array( '.', ',' ), array( '', '.' ), $raw ); // 1.250,50
		} else {
			$raw = str_replace( ',', '.', $raw );
		}
		return is_numeric( $raw ) && (float) $raw >= 0 ? (float) $raw : null;
	}

	private static function num( $value ) {
		return null === $value ? '' : rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Neutralises spreadsheet formula injection.
	 *
	 * @param mixed $value Cell.
	 * @return string
	 */
	public static function cell( $value ) {
		$value = (string) $value;
		return ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $value ) && self::CLEAR !== $value ) ? "'" . $value : $value;
	}
}
