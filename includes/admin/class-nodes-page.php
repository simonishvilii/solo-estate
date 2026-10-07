<?php
/**
 * Projects and everything inside them: phases, buildings, floors, parkings,
 * apartments, commercial spaces, villas and parking spaces.
 *
 * Managers can do everything. Marketing can add and change everything but not delete.
 * Sales see the same screens read-only and can change statuses.
 *
 * @package SoloEstate\Admin
 */

namespace SoloEstate\Admin;

use SoloEstate\Frontend\Renderer;
use SoloEstate\I18n;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Specs;
use SoloEstate\Statuses;

defined( 'ABSPATH' ) || exit;

class Nodes_Page {

	/** Levels with the specification (rooms / sections) block. */
	const SPEC_LEVELS = array( 'flat', 'commercial', 'villa', 'villa_floor' );

	/** Levels whose number is optional because a name is usually used instead. */
	const OPTIONAL_NUMBER = array( 'phase', 'building', 'parking' );

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_post_solo_estate_save_node', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_solo_estate_delete_node', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_solo_estate_duplicate_node', array( __CLASS__, 'handle_duplicate' ) );
		add_action( 'admin_post_solo_estate_bulk_nodes', array( __CLASS__, 'handle_bulk' ) );
		add_action( 'admin_post_solo_estate_apply_layout', array( __CLASS__, 'handle_apply_layout' ) );
		add_action( 'admin_post_solo_estate_delete_misplaced', array( __CLASS__, 'handle_delete_misplaced' ) );
	}

	/**
	 * Screen router.
	 */
	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['node'] ) ? absint( $_GET['node'] ) : 0;
		$parent = isset( $_GET['parent'] ) ? absint( $_GET['parent'] ) : 0;
		$level  = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
		// phpcs:enable

		echo '<div class="wrap solo-estate-wrap">';

		if ( 'new' === $action ) {
			$parent_node = $parent ? Nodes::get( $parent ) : null;
			if ( '' === $level ) {
				$allowed = $parent_node ? Nodes::child_levels( $parent_node->level ) : array( 'project' );
				$level   = 1 === count( $allowed ) ? $allowed[0] : '';
			}
			if ( ! Admin::can_edit() ) {
				self::not_allowed();
			} elseif ( ( $parent && ! $parent_node ) || ! Nodes::allows( $parent_node ? $parent_node->level : '', $level ) ) {
				self::not_found();
			} else {
				self::render_form( null, $level, $parent_node );
			}
		} elseif ( $id ) {
			$node = Nodes::get( $id );
			if ( ! $node ) {
				self::not_found();
			} elseif ( Admin::can_edit() ) {
				self::render_form( $node, $node->level, $node->parent_id ? Nodes::get( $node->parent_id ) : null );
			} else {
				self::render_sales( $node );
			}
		} else {
			self::render_misplaced();
			self::render_projects();
		}

		echo '</div>';
	}

	/**
	 * Notice about units left inside other units by an old import, with a delete button.
	 */
	private static function render_misplaced() {
		$items = Nodes::misplaced();
		if ( ! $items ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( sprintf( /* translators: %d: number of items */ _n( '%d item is inside an apartment, where nothing can be.', '%d items are inside an apartment, where nothing can be.', count( $items ), 'solo-estate' ), count( $items ) ) ) . '</strong> ';
		echo esc_html__( 'They came from the old database by mistake (usually duplicates). They are not shown on the site or counted, so they can be deleted.', 'solo-estate' ) . '</p><ul class="ul-disc">';
		foreach ( $items as $item ) {
			$parts = array();
			foreach ( Nodes::ancestors( $item ) as $ancestor ) {
				$parts[] = Nodes::admin_title( $ancestor );
			}
			printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( Admin::url( 'solo-estate', array( 'node' => $item->id ) ) ), esc_html( implode( ' › ', array_filter( $parts, 'strlen' ) ) ) );
		}
		echo '</ul>';
		if ( Admin::can_manage() ) {
			printf(
				'<p><a class="button" href="%1$s" data-solo-estate-confirm>%2$s</a></p>',
				esc_url( wp_nonce_url( add_query_arg( 'action', 'solo_estate_delete_misplaced', admin_url( 'admin-post.php' ) ), 'solo_estate_delete_misplaced' ) ),
				esc_html__( 'Delete them', 'solo-estate' )
			);
		}
		echo '</div>';
	}

	/**
	 * Deletes the units that sit inside other units.
	 */
	public static function handle_delete_misplaced() {
		Admin::check( 'solo_estate_delete_misplaced' );
		foreach ( Nodes::misplaced() as $item ) {
			Nodes::delete( $item->id );
		}
		Admin::redirect( Admin::url(), 'deleted' );
	}

	private static function not_found() {
		echo '<h1>' . esc_html__( 'Not found', 'solo-estate' ) . '</h1>';
		echo '<p><a href="' . esc_url( Admin::url() ) . '">' . esc_html__( '← All projects', 'solo-estate' ) . '</a></p>';
	}

	private static function not_allowed() {
		echo '<h1>' . esc_html__( 'You are not allowed to do this.', 'solo-estate' ) . '</h1>';
		echo '<p><a href="' . esc_url( Admin::url() ) . '">' . esc_html__( '← All projects', 'solo-estate' ) . '</a></p>';
	}

	/**
	 * Top-level list of projects.
	 */
	private static function render_projects() {
		$projects = Nodes::projects();
		$counts   = Nodes::child_counts( wp_list_pluck( $projects, 'id' ) );

		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Projects', 'solo-estate' ) . '</h1>';
		if ( Admin::can_edit() ) {
			printf( ' <a class="page-title-action" href="%1$s">%2$s</a>', esc_url( Admin::url( 'solo-estate', array( 'action' => 'new' ) ) ), esc_html__( 'Add project', 'solo-estate' ) );
		}
		echo '<hr class="wp-header-end">';

		echo '<p class="description">' . wp_kses_post( sprintf( /* translators: %s: shortcode */ __( 'Put %s on a page to show all projects (Projects → project → building → floor → apartment). Use the per-project shortcode below to show a single project.', 'solo-estate' ), '<code class="solo-estate-copy">[solo_estate]</code>' ) ) . '</p>';
		if ( ! $projects ) {
			echo '<div class="solo-estate-empty"><p>' . esc_html__( 'No projects yet. Create one, or import apartments from a CSV/Excel file under Solo Estate → Import / Export.', 'solo-estate' ) . '</p></div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped solo-estate-table"><thead><tr>';
		echo '<th class="column-primary">' . esc_html__( 'Project', 'solo-estate' ) . '</th>';
		echo '<th>' . esc_html__( 'Items inside', 'solo-estate' ) . '</th>';
		echo '<th>' . esc_html__( 'Available', 'solo-estate' ) . '</th>';
		echo '<th>' . esc_html__( 'Sold', 'solo-estate' ) . '</th>';
		echo '<th>' . esc_html__( 'Shortcode', 'solo-estate' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $projects as $project ) {
			$edit  = Admin::url( 'solo-estate', array( 'node' => $project->id ) );
			$stats = Nodes::stats( $project );
			echo '<tr><td class="column-primary">';
			if ( $project->image_id || $project->image2_id ) {
				echo wp_get_attachment_image( $project->image2_id ? $project->image2_id : $project->image_id, array( 60, 40 ), false, array( 'class' => 'solo-estate-thumb' ) );
			}
			printf( '<strong><a class="row-title" href="%1$s">%2$s</a></strong>', esc_url( $edit ), esc_html( Nodes::admin_title( $project ) ) );
			echo '<div class="row-actions">';
			printf( '<span class="edit"><a href="%1$s">%2$s</a></span>', esc_url( $edit ), esc_html__( 'Open', 'solo-estate' ) );
			if ( Admin::can_manage() ) {
				printf( ' | <span class="trash"><a href="%1$s" data-solo-estate-confirm>%2$s</a></span>', esc_url( self::action_url( 'solo_estate_delete_node', $project->id ) ), esc_html__( 'Delete', 'solo-estate' ) );
			}
			echo '</div></td>';
			echo '<td>' . (int) ( isset( $counts[ $project->id ] ) ? $counts[ $project->id ] : 0 ) . '</td>';
			printf( '<td>%1$d / %2$d</td>', (int) $stats['available'], (int) $stats['total'] );
			echo '<td>' . self::sold_meter( $stats ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			printf( '<td><code class="solo-estate-copy" title="%2$s">[solo_estate id="%1$d"]</code></td>', (int) $project->id, esc_attr__( 'Click to copy', 'solo-estate' ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * "42% · 21 / 50" with a small bar.
	 *
	 * @param array $stats Nodes::stats().
	 * @return string HTML.
	 */
	private static function sold_meter( array $stats ) {
		if ( ! $stats['total'] ) {
			return '—';
		}
		return sprintf(
			'<span class="solo-estate-sold"><strong>%1$d%%</strong> <span class="description">%2$d / %3$d</span><span class="solo-estate-sold__bar"><span style="width:%1$d%%"></span></span></span>',
			(int) $stats['percent'],
			(int) $stats['sold'],
			(int) $stats['total']
		);
	}

	/**
	 * Edit (or create) form plus children list.
	 *
	 * @param object|null $node   Node or null when creating.
	 * @param string      $level  Level.
	 * @param object|null $parent Parent node.
	 */
	private static function render_form( $node, $level, $parent ) {
		$is_new = null === $node;
		$i18n   = $is_new ? array() : $node->i18n;
		$unit   = Nodes::is_unit( $level );
		$get    = static function ( $key, $fallback = '' ) use ( $node ) {
			return $node && null !== $node->$key ? $node->$key : $fallback;
		};

		self::breadcrumbs( $node ? $node : $parent, $is_new ? $level : '' );

		printf(
			'<h1 class="wp-heading-inline">%s</h1>',
			esc_html(
				$is_new
					/* translators: %s: level name, e.g. Floor */
					? sprintf( __( 'Add %s', 'solo-estate' ), Nodes::level_label( $level ) )
					: Nodes::admin_title( $node )
			)
		);
		if ( ! $is_new && 'project' === $level ) {
			printf( ' <code class="solo-estate-copy solo-estate-heading-code" title="%2$s">[solo_estate id="%1$d"]</code>', (int) $node->id, esc_attr__( 'Click to copy', 'solo-estate' ) );
		}
		echo '<hr class="wp-header-end">';

		if ( ! $is_new && ! $unit && ! in_array( $level, array( 'villa_floor', 'spot' ), true ) ) {
			$stats = Nodes::stats( $node );
			if ( $stats['total'] ) {
				printf(
					'<p class="solo-estate-summary">%1$s %2$s &nbsp; %3$s</p>',
					/* translators: 1: available, 2: total */
					esc_html( sprintf( __( 'Available: %1$d of %2$d.', 'solo-estate' ), $stats['available'], $stats['total'] ) ),
					esc_html__( 'Sold:', 'solo-estate' ),
					self::sold_meter( $stats ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="solo-estate-node-form">';
		wp_nonce_field( 'solo_estate_save_node' );
		echo '<input type="hidden" name="action" value="solo_estate_save_node">';
		printf( '<input type="hidden" name="id" value="%d">', $is_new ? 0 : (int) $node->id );
		printf( '<input type="hidden" name="parent_id" value="%d">', $parent ? (int) $parent->id : 0 );
		printf( '<input type="hidden" name="level" value="%s">', esc_attr( $level ) );

		echo '<div class="solo-estate-columns"><div class="solo-estate-col-main">';
		echo '<div class="solo-estate-card"><table class="form-table" role="presentation"><tbody>';

		// Name / number.
		if ( 'project' === $level ) {
			self::row( __( 'Project name', 'solo-estate' ), static function () use ( $i18n ) {
				Ui::i18n_field( 'i18n[title]', isset( $i18n['title'] ) ? $i18n['title'] : array() );
			} );
		} else {
			$number_labels = array(
				'phase'       => __( 'Phase number', 'solo-estate' ),
				'building'    => __( 'Building number', 'solo-estate' ),
				'floor'       => __( 'Floor number', 'solo-estate' ),
				'parking'     => __( 'Parking level', 'solo-estate' ),
				'flat'        => __( 'Apartment number', 'solo-estate' ),
				'commercial'  => __( 'Space number', 'solo-estate' ),
				'villa'       => __( 'Villa number', 'solo-estate' ),
				'villa_floor' => __( 'Floor number', 'solo-estate' ),
				'spot'        => __( 'Parking space number', 'solo-estate' ),
			);
			$required = ! in_array( $level, self::OPTIONAL_NUMBER, true );
			self::row( $number_labels[ $level ], static function () use ( $node, $required, $level ) {
				printf( '<input type="text" class="small-text solo-estate-number" name="number" value="%1$s"%2$s>', esc_attr( $node ? $node->number : '' ), $required ? ' required' : '' );
				if ( 'parking' === $level ) {
					echo '<p class="description">' . esc_html__( 'Optional, e.g. -1. Give the parking a name below instead if you like.', 'solo-estate' ) . '</p>';
				} elseif ( 'building' === $level ) {
					echo '<p class="description">' . esc_html__( 'Optional: a sports field, boulevard or commercial building needs no number, only a type below.', 'solo-estate' ) . '</p>';
				}
			} );
		}

		if ( 'building' === $level ) {
			self::row( __( 'Type (before the number)', 'solo-estate' ), static function () use ( $i18n ) {
				Ui::i18n_field( 'i18n[label]', isset( $i18n['label'] ) ? $i18n['label'] : array(), 'text', array( 'placeholder' => __( 'e.g. Block, Commercial building', 'solo-estate' ) ) );
				echo '<p class="description">' . esc_html__( 'The name is this word plus the number: "Block" + "A" = "Block A". Without a number the word alone is the name, e.g. "Sports field". Empty = "Building".', 'solo-estate' ) . '</p>';
			} );
		}

		if ( 'project' !== $level ) {
			$placeholders = array(
				'phase'      => __( 'e.g. Phase I', 'solo-estate' ),
				'floor'      => __( 'Optional, e.g. Ground floor', 'solo-estate' ),
				'parking'    => __( 'e.g. Underground parking', 'solo-estate' ),
				'flat'       => __( 'Optional, e.g. Penthouse', 'solo-estate' ),
				'commercial' => __( 'Optional, e.g. Shop 2', 'solo-estate' ),
				'villa'      => __( 'Optional, e.g. Villa Rose', 'solo-estate' ),
			);
			self::row( __( 'Custom name', 'solo-estate' ), static function () use ( $i18n, $placeholders, $level ) {
				Ui::i18n_field( 'i18n[title]', isset( $i18n['title'] ) ? $i18n['title'] : array(), 'text', array( 'placeholder' => isset( $placeholders[ $level ] ) ? $placeholders[ $level ] : __( 'Optional', 'solo-estate' ) ) );
				echo '<p class="description">' . esc_html(
					'building' === $level
						? __( 'Optional. When filled in, it replaces the whole name (type and number), e.g. "Rose Residence". Leave empty to show the type and number.', 'solo-estate' )
						: __( 'Leave empty to show the type and number, e.g. "Apartment 5".', 'solo-estate' )
				) . '</p>';
			} );
		}

		if ( in_array( $level, array( 'flat', 'commercial' ), true ) ) {
			self::row( __( 'Entrance', 'solo-estate' ), static function () use ( $node ) {
				printf( '<input type="text" class="small-text" name="entrance" value="%s">', esc_attr( $node ? $node->entrance : '' ) );
				echo '<p class="description">' . esc_html__( 'Optional, e.g. 2 or B. Shown in the apartment details.', 'solo-estate' ) . '</p>';
			} );
		}

		if ( in_array( $level, array( 'phase', 'building' ), true ) ) {
			self::row( __( 'Completion', 'solo-estate' ), static function () use ( $i18n ) {
				Ui::i18n_field( 'i18n[completion]', isset( $i18n['completion'] ) ? $i18n['completion'] : array(), 'text', array( 'placeholder' => __( 'e.g. Q4 2027', 'solo-estate' ) ) );
			} );
		}
		if ( 'building' === $level ) {
			self::row( __( 'External link', 'solo-estate' ), static function () use ( $i18n ) {
				Ui::i18n_field( 'i18n[link]', isset( $i18n['link'] ) ? $i18n['link'] : array(), 'url', array( 'placeholder' => 'https://' ) );
				echo '<p class="description">' . esc_html__( 'Optional. When set, clicking the building opens this page instead of its floors.', 'solo-estate' ) . '</p>';
			} );
		}

		// Status.
		if ( Statuses::for_level( $level ) || '' !== Statuses::scope_of( $level ) ) {
			self::row( __( 'Status', 'solo-estate' ), static function () use ( $node, $level ) {
				self::status_select( $level, $node ? $node->status_id : 0 );
				if ( Admin::can_manage() ) {
					echo ' <a href="' . esc_url( Admin::url( 'solo-estate-statuses' ) ) . '">' . esc_html__( 'Manage statuses', 'solo-estate' ) . '</a>';
				}
				if ( 'project' === $level ) {
					echo '<p class="description">' . esc_html__( 'Used for the tabs on the projects page (e.g. Ongoing / Completed).', 'solo-estate' ) . '</p>';
				}
			} );
		}

		// Visitor access (open / closed), independent of how much is sold.
		if ( ! in_array( $level, array( 'villa_floor', 'spot' ), true ) ) {
			self::row( __( 'Can visitors open it?', 'solo-estate' ), static function () use ( $node, $unit ) {
				$value   = $node ? $node->access : Nodes::ACCESS_AUTO;
				$options = array(
					Nodes::ACCESS_AUTO   => __( 'Automatic', 'solo-estate' ),
					Nodes::ACCESS_OPEN   => __( 'Always open', 'solo-estate' ),
					Nodes::ACCESS_CLOSED => __( 'Closed', 'solo-estate' ),
				);
				echo '<select name="access">';
				foreach ( $options as $key => $label ) {
					printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $key, selected( $value, $key, false ), esc_html( $label ) );
				}
				echo '</select><p class="description">' . esc_html(
					$unit
						? __( 'Automatic: follows the status (sold, reserved and rented units do not open).', 'solo-estate' )
						: __( 'Automatic: follows the status; sold out phases and buildings close when this is enabled in Settings. "Always open" and "Closed" ignore how much is sold.', 'solo-estate' )
				) . '</p>';
			} );
		}

		// Boulevard, sports field, park…: an info window instead of a page of its own.
		if ( in_array( $level, Nodes::INFO_LEVELS, true ) ) {
			self::row( __( 'Detailed page', 'solo-estate' ), static function () use ( $node ) {
				echo '<input type="hidden" name="info_modal_field" value="1">';
				printf(
					'<label><input type="checkbox" name="open_page" value="1"%1$s> %2$s</label>',
					checked( ! $node || ! $node->info_modal, true, false ),
					esc_html__( 'Clicking it opens the detailed page', 'solo-estate' )
				);
				echo '<p class="description">' . esc_html__( 'Turn off for a boulevard, sports field, park and the like: clicking then opens a window with its photos and description, and no status is shown for it on the site.', 'solo-estate' ) . '</p>';
			} );
		}

		// Areas and prices.
		if ( $unit || in_array( $level, array( 'villa_floor', 'spot' ), true ) ) {
			$unit_label = (string) Settings::get( 'area_unit' );
			$base       = (string) Settings::get( 'base_currency' );
			if ( in_array( $level, array( 'flat', 'villa' ), true ) ) {
				self::row( __( 'Rooms', 'solo-estate' ), static function () use ( $get ) {
					printf( '<input type="number" min="0" max="20" step="1" class="small-text" name="rooms" value="%s">', esc_attr( $get( 'rooms' ) ) );
					echo '<p class="description">' . esc_html__( 'Used by the apartment filter. 0 = studio.', 'solo-estate' ) . '</p>';
				} );
			}
			self::row( __( 'Total area', 'solo-estate' ), static function () use ( $get, $unit_label ) {
				printf( '<input type="number" step="0.01" min="0" class="small-text" name="area" value="%1$s" data-solo-estate-calc="area"> %2$s', esc_attr( $get( 'area' ) ), esc_html( $unit_label ) );
			} );
			if ( 'spot' !== $level ) {
				self::row( __( 'Living area', 'solo-estate' ), static function () use ( $get, $unit_label ) {
					printf( '<input type="number" step="0.01" min="0" class="small-text" name="area_living" value="%1$s"> %2$s', esc_attr( $get( 'area_living' ) ), esc_html( $unit_label ) );
				} );
				self::row( __( 'Summer area', 'solo-estate' ), static function () use ( $get, $unit_label ) {
					printf( '<input type="number" step="0.01" min="0" class="small-text" name="area_summer" value="%1$s"> %2$s', esc_attr( $get( 'area_summer' ) ), esc_html( $unit_label ) );
					echo '<p class="description">' . esc_html__( 'Balconies, terraces, loggias.', 'solo-estate' ) . '</p>';
				} );
			}
			if ( $unit ) {
				self::row( __( 'Price per m²', 'solo-estate' ), static function () use ( $get, $base ) {
					printf( '<input type="number" step="0.01" min="0" class="regular-text" name="price_sqm" value="%1$s" data-solo-estate-calc="sqm"> %2$s', esc_attr( $get( 'price_sqm' ) ), esc_html( $base ) );
				} );
			}
			if ( 'villa_floor' !== $level ) {
				self::row( __( 'Total price', 'solo-estate' ), static function () use ( $get, $base, $unit ) {
					printf( '<input type="number" step="0.01" min="0" class="regular-text" name="price_total" value="%1$s" data-solo-estate-calc="total"> %2$s', esc_attr( $get( 'price_total' ) ), esc_html( $base ) );
					if ( $unit ) {
						echo '<p class="description">' . esc_html__( 'Filled in automatically: total area × price per m². Type a different total if needed.', 'solo-estate' ) . '</p>';
					}
				} );
			}
		}

		// Images.
		$image_labels = array(
			'project'     => __( 'Masterplan (optional)', 'solo-estate' ),
			'phase'       => __( 'Phase render (with buildings)', 'solo-estate' ),
			'building'    => __( 'Building render (with floors)', 'solo-estate' ),
			'floor'       => __( 'Floor plan (with apartments)', 'solo-estate' ),
			'parking'     => __( 'Parking plan (with parking spaces)', 'solo-estate' ),
			'flat'        => __( '2D plan', 'solo-estate' ),
			'commercial'  => __( '2D plan', 'solo-estate' ),
			'villa'       => __( '2D plan', 'solo-estate' ),
			'villa_floor' => __( 'Floor plan', 'solo-estate' ),
		);
		if ( isset( $image_labels[ $level ] ) ) {
			self::row( $image_labels[ $level ], static function () use ( $node, $level, $unit ) {
				Ui::image_field( 'image_id', $node ? $node->image_id : 0 );
				if ( 'project' === $level ) {
					echo '<p class="description">' . esc_html__( 'Buildings, villas and parkings are drawn on it. Without a masterplan visitors see a list instead.', 'solo-estate' ) . '</p>';
				} elseif ( ! $unit && 'villa_floor' !== $level ) {
					echo '<p class="description">' . esc_html__( 'Polygons of the items inside are drawn on this image.', 'solo-estate' ) . '</p>';
				}
			} );
		}
		if ( 'project' === $level ) {
			self::row( __( 'Card image', 'solo-estate' ), static function () use ( $node ) {
				Ui::image_field( 'image2_id', $node ? $node->image2_id : 0 );
				echo '<p class="description">' . esc_html__( 'Shown on the projects page. The masterplan is used when empty.', 'solo-estate' ) . '</p>';
			} );
		}
		if ( $unit ) {
			self::row( __( '3D plan', 'solo-estate' ), static function () use ( $node ) {
				Ui::image_field( 'image2_id', $node ? $node->image2_id : 0 );
			} );
		}
		if ( $unit || 'villa_floor' === $level ) {
			self::row( __( 'Photos', 'solo-estate' ), static function () use ( $node ) {
				Ui::gallery_field( 'gallery', $node ? Nodes::gallery( $node ) : array() );
				echo '<p class="description">' . esc_html__( 'Renders, interior photos, views. Drag to change the order.', 'solo-estate' ) . '</p>';
			} );
		} elseif ( in_array( $level, Nodes::INFO_LEVELS, true ) ) {
			self::row( __( 'Photos', 'solo-estate' ), static function () use ( $node ) {
				Ui::gallery_field( 'gallery', $node ? Nodes::gallery( $node ) : array() );
				echo '<p class="description">' . esc_html__( 'Shown in the info window when the detailed page is turned off. Drag to change the order.', 'solo-estate' ) . '</p>';
			} );
		}
		if ( $unit ) {
			self::row( __( 'Virtual tour link', 'solo-estate' ), static function () use ( $node ) {
				printf( '<input type="url" class="large-text" name="tour_url" value="%s" placeholder="https://">', esc_attr( $node ? $node->tour_url : '' ) );
				echo '<p class="description">' . esc_html__( 'Matterport, Kuula, 3DVista or any other 360° tour. It opens embedded on the page.', 'solo-estate' ) . '</p>';
			} );
		}

		if ( in_array( $level, array( 'project', 'phase', 'building', 'parking', 'flat', 'commercial', 'villa' ), true ) ) {
			self::row( __( 'Description', 'solo-estate' ), static function () use ( $i18n ) {
				Ui::i18n_field( 'i18n[description]', isset( $i18n['description'] ) ? $i18n['description'] : array(), 'editor', array( 'rows' => 6 ) );
			} );
		}

		self::row( __( 'Order', 'solo-estate' ), static function () use ( $node ) {
			printf( '<input type="number" class="small-text" name="sort_order" value="%d">', $node ? (int) $node->sort_order : 0 );
			echo '<p class="description">' . esc_html__( 'Lower numbers come first.', 'solo-estate' ) . '</p>';
		} );

		echo '</tbody></table></div>';

		if ( in_array( $level, self::SPEC_LEVELS, true ) ) {
			self::render_specs( $node );
		}

		// Polygon on the parent image.
		if ( $parent && 'villa_floor' !== $level ) {
			self::render_polygon_editor( $node, $parent );
		}

		echo '</div><div class="solo-estate-col-side"><div class="solo-estate-card solo-estate-card--sticky">';
		submit_button( $is_new ? __( 'Create', 'solo-estate' ) : __( 'Save changes', 'solo-estate' ), 'primary large', 'submit', false );
		if ( ! $is_new ) {
			echo '<p class="solo-estate-side-actions">';
			if ( 'project' !== $level ) {
				printf( '<a href="%1$s">%2$s</a><br>', esc_url( self::action_url( 'solo_estate_duplicate_node', $node->id ) ), esc_html__( 'Duplicate (with everything inside)', 'solo-estate' ) );
			}
			if ( Admin::can_manage() ) {
				printf( '<a class="solo-estate-delete" href="%1$s" data-solo-estate-confirm>%2$s</a>', esc_url( self::action_url( 'solo_estate_delete_node', $node->id ) ), esc_html__( 'Delete', 'solo-estate' ) );
			}
			echo '</p>';
		}
		echo '</div></div></div>';
		echo '</form>';

		if ( ! $is_new && Nodes::child_levels( $level ) ) {
			self::render_children( $node );
		}
		if ( ! $is_new && 'floor' === $level ) {
			self::render_apply_layout( $node );
		}
	}

	/**
	 * Status dropdown for a level.
	 *
	 * @param string $level   Level.
	 * @param int    $current Selected status.
	 */
	private static function status_select( $level, $current ) {
		printf( '<select name="status_id"><option value="0">— %s —</option>', esc_html__( 'No status', 'solo-estate' ) );
		foreach ( Statuses::for_level( $level ) as $status ) {
			printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $status->id, selected( $current, $status->id, false ), esc_html( Statuses::title( $status, I18n::default_code() ) ) );
		}
		echo '</select>';
	}

	/**
	 * Sales view: facts read-only, status editable, children with bulk status.
	 *
	 * @param object $node Node.
	 */
	private static function render_sales( $node ) {
		self::breadcrumbs( $node, '' );
		echo '<h1 class="wp-heading-inline">' . esc_html( Nodes::admin_title( $node ) ) . '</h1><hr class="wp-header-end">';

		echo '<div class="solo-estate-card"><table class="form-table" role="presentation"><tbody>';
		self::row( __( 'Type', 'solo-estate' ), static function () use ( $node ) {
			echo esc_html( Nodes::level_label( $node->level ) );
		} );
		if ( Nodes::is_unit( $node->level ) || 'spot' === $node->level ) {
			if ( null !== $node->area ) {
				self::row( __( 'Total area', 'solo-estate' ), static function () use ( $node ) {
					echo esc_html( number_format_i18n( $node->area, 2 ) . ' ' . Settings::get( 'area_unit' ) );
				} );
			}
			$total = Nodes::total_price( $node );
			if ( null !== $total ) {
				self::row( __( 'Total price', 'solo-estate' ), static function () use ( $total ) {
					echo esc_html( number_format_i18n( $total ) . ' ' . Settings::get( 'base_currency' ) );
				} );
			}
		} else {
			$stats = Nodes::stats( $node );
			if ( $stats['total'] ) {
				self::row( __( 'Sold', 'solo-estate' ), static function () use ( $stats ) {
					echo self::sold_meter( $stats ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} );
			}
		}
		echo '</tbody></table>';

		if ( '' !== Statuses::scope_of( $node->level ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="solo-estate-status-form">';
			wp_nonce_field( 'solo_estate_save_node' );
			echo '<input type="hidden" name="action" value="solo_estate_save_node">';
			printf( '<input type="hidden" name="id" value="%d">', (int) $node->id );
			echo '<label><strong>' . esc_html__( 'Status', 'solo-estate' ) . '</strong> ';
			self::status_select( $node->level, $node->status_id );
			echo '</label> ';
			submit_button( __( 'Save status', 'solo-estate' ), 'primary', 'submit', false );
			echo '</form>';
		}
		echo '</div>';

		if ( Nodes::child_levels( $node->level ) ) {
			self::render_children( $node );
		}
	}

	/**
	 * "Apply this floor's layout to other floors" — apartments stacked on top of each other
	 * share the same outline, so draw one floor and copy it to the rest.
	 *
	 * @param object $floor Floor.
	 */
	private static function render_apply_layout( $floor ) {
		$flats   = Nodes::sorted_children( $floor->id );
		$targets = array_filter(
			Nodes::sorted_children( $floor->parent_id, 'floor' ),
			static function ( $item ) use ( $floor ) {
				return $item->id !== $floor->id;
			}
		);
		if ( ! $flats || ! $targets ) {
			return;
		}
		$counts = Nodes::child_counts( wp_list_pluck( $targets, 'id' ) );

		echo '<div class="solo-estate-card solo-estate-apply-layout"><h2>' . esc_html__( 'Apply this layout to other floors', 'solo-estate' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Copies the outline of each apartment to the apartment in the same position on the selected floors (1st to 1st, 2nd to 2nd… by apartment number order). Use it for floors with the same plan.', 'solo-estate' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'solo_estate_apply_layout_' . $floor->id );
		echo '<input type="hidden" name="action" value="solo_estate_apply_layout">';
		printf( '<input type="hidden" name="node" value="%d">', (int) $floor->id );
		echo '<p><label><input type="checkbox" data-solo-estate-check-group="layout-targets"> <strong>' . esc_html__( 'Select all', 'solo-estate' ) . '</strong></label></p><div class="solo-estate-apply-layout__floors">';
		foreach ( $targets as $target ) {
			$count = isset( $counts[ $target->id ] ) ? $counts[ $target->id ] : 0;
			printf(
				'<label class="%4$s"><input type="checkbox" name="targets[]" value="%1$d" data-group="layout-targets"> %2$s <span class="description">(%3$s)</span></label>',
				(int) $target->id,
				esc_html( Nodes::admin_title( $target ) ),
				/* translators: %d: number of apartments */
				esc_html( sprintf( _n( '%d apartment', '%d apartments', $count, 'solo-estate' ), $count ) ),
				count( $flats ) === $count ? '' : 'solo-estate-mismatch'
			);
		}
		echo '</div>';
		echo '<p><label><input type="checkbox" name="copy_image" value="1" checked> ' . esc_html__( 'Also use this floor plan image', 'solo-estate' ) . '</label><br>';
		echo '<label><input type="checkbox" name="copy_details" value="1"> ' . esc_html__( 'Also copy area and specification of each apartment', 'solo-estate' ) . '</label><br>';
		echo '<label><input type="checkbox" name="overwrite" value="1"> ' . esc_html__( 'Overwrite outlines that are already drawn', 'solo-estate' ) . '</label></p>';
		/* translators: %d: number of apartments on this floor */
		echo '<p class="description">' . esc_html( sprintf( __( 'This floor has %d apartments. Floors marked in orange have a different number, only the matching positions are copied.', 'solo-estate' ), count( $flats ) ) ) . '</p>';
		submit_button( __( 'Apply layout', 'solo-estate' ), 'secondary', 'submit', false );
		echo '</form></div>';
	}

	/**
	 * Breadcrumb trail.
	 *
	 * @param object|null $node   Deepest existing node.
	 * @param string      $adding Level being added.
	 */
	private static function breadcrumbs( $node, $adding ) {
		echo '<nav class="solo-estate-breadcrumbs"><a href="' . esc_url( Admin::url() ) . '">' . esc_html__( 'Projects', 'solo-estate' ) . '</a>';
		if ( $node ) {
			foreach ( Nodes::ancestors( $node ) as $item ) {
				printf( ' <span>›</span> <a href="%1$s">%2$s</a>', esc_url( Admin::url( 'solo-estate', array( 'node' => $item->id ) ) ), esc_html( Nodes::admin_title( $item ) ) );
			}
		}
		if ( $adding ) {
			/* translators: %s: level name */
			echo ' <span>›</span> ' . esc_html( sprintf( __( 'New %s', 'solo-estate' ), Nodes::level_label( $adding ) ) );
		}
		echo '</nav>';
	}

	/**
	 * Table row helper.
	 *
	 * @param string   $label    Label.
	 * @param callable $callback Renders the field.
	 */
	private static function row( $label, $callback ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		$callback();
		echo '</td></tr>';
	}

	/**
	 * Specification (rooms and sections) of a unit, with new sections added right here.
	 * A new section is saved to the shared list, so it can be used on every unit.
	 *
	 * @param object|null $node Unit.
	 */
	private static function render_specs( $node ) {
		$fields = Specs::fields();
		$values = $node ? Specs::values( $node->id ) : array();

		echo '<div class="solo-estate-card"><h2>' . esc_html__( 'Rooms and sections', 'solo-estate' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Area of each room (bedroom, kitchen, balcony…). Empty fields are not shown on the site.', 'solo-estate' ) . '</p>';
		if ( $fields ) {
			echo '<div class="solo-estate-spec-grid">';
			foreach ( $fields as $field ) {
				printf(
					'<label class="solo-estate-spec-grid__item"><span>%1$s</span><input type="text" name="specs[%2$d]" value="%3$s" inputmode="decimal"> <em>%4$s</em></label>',
					esc_html( Specs::title( $field, I18n::default_code() ) ),
					(int) $field->id,
					esc_attr( isset( $values[ $field->id ] ) ? $values[ $field->id ] : '' ),
					esc_html( Specs::unit( $field ) )
				);
			}
			echo '</div>';
		}

		// New sections: name + value (+ unit). Rows are added in JS from the template.
		echo '<div class="solo-estate-new-specs" data-solo-estate-new-specs>';
		echo '<table class="solo-estate-new-specs__table"><tbody data-solo-estate-new-specs-rows></tbody></table>';
		printf(
			'<template data-solo-estate-new-spec-template><tr><td><input type="text" name="new_specs[__i__][title]" placeholder="%1$s" class="regular-text"></td><td><input type="text" name="new_specs[__i__][value]" placeholder="%2$s" class="small-text" inputmode="decimal"></td><td><input type="text" name="new_specs[__i__][unit]" placeholder="%3$s" class="small-text"></td><td><button type="button" class="button-link button-link-delete" data-solo-estate-remove-row>%4$s</button></td></tr></template>',
			esc_attr__( 'Name, e.g. Bedroom 3', 'solo-estate' ),
			esc_attr__( 'Value', 'solo-estate' ),
			esc_attr( Settings::get( 'area_unit' ) ),
			esc_html__( 'Remove', 'solo-estate' )
		);
		printf( '<button type="button" class="button" data-solo-estate-add-spec>+ %s</button>', esc_html__( 'Add a new section', 'solo-estate' ) );
		echo '<p class="description">' . esc_html__( 'A new section (e.g. "Bedroom 3") is saved to the shared list, so it can be filled on any unit later. Translate its name under Specification.', 'solo-estate' ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * Visual polygon editor over the parent image.
	 *
	 * @param object|null $node   Node being edited.
	 * @param object      $parent Parent node.
	 */
	private static function render_polygon_editor( $node, $parent ) {
		echo '<div class="solo-estate-card"><h2>' . esc_html__( 'Position on the image', 'solo-estate' ) . '</h2>';

		$src = $parent->image_id ? wp_get_attachment_image_url( $parent->image_id, 'full' ) : '';
		if ( ! $src ) {
			printf(
				'<p>%s</p>',
				wp_kses_post(
					sprintf(
						/* translators: 1: parent title, 2: edit URL */
						__( 'Add an image to <a href="%2$s">%1$s</a> first, then draw this item on it.', 'solo-estate' ),
						esc_html( Nodes::admin_title( $parent ) ),
						esc_url( Admin::url( 'solo-estate', array( 'node' => $parent->id ) ) )
					)
				)
			);
			printf( '<input type="hidden" name="coords" value="%s">', esc_attr( $node ? $node->coords : '' ) );
			echo '</div>';
			return;
		}

		$siblings = array();
		foreach ( Nodes::children( $parent->id ) as $sibling ) {
			if ( $node && $sibling->id === $node->id ) {
				continue;
			}
			$points = Nodes::points( $sibling->coords );
			if ( $points ) {
				$siblings[] = array(
					'points' => $points,
					'label'  => Nodes::admin_title( $sibling ),
				);
			}
		}

		echo '<ul class="solo-estate-poly__help description">';
		echo '<li>' . esc_html__( 'Click on the image to add a point. The outline always closes by itself.', 'solo-estate' ) . '</li>';
		echo '<li>' . esc_html__( 'Drag a point to move it. Click a point to select it and press Delete to remove it.', 'solo-estate' ) . '</li>';
		echo '<li>' . esc_html__( 'Point at an edge: a new point appears — press and drag to add it there.', 'solo-estate' ) . '</li>';
		echo '<li>' . esc_html__( 'Drag inside the outline to move the whole outline. Other items on this image are shown in grey.', 'solo-estate' ) . '</li>';
		echo '<li>' . esc_html__( 'Mouse wheel zooms in and out; drag outside the outline to move the zoomed image.', 'solo-estate' ) . '</li>';
		echo '</ul>';
		// Same coordinate frame as the front end (Renderer::stage), which reads the size from the
		// attachment metadata. The file itself can differ (resized by an optimization plugin,
		// replaced in the media library); drawing in its pixels shifted outlines on the site.
		list( $frame_w, $frame_h ) = Renderer::image_size( $parent->image_id );
		printf(
			'<div class="solo-estate-poly" data-solo-estate-poly data-src="%1$s" data-siblings="%2$s" data-width="%3$d" data-height="%4$d">',
			esc_url( $src ),
			esc_attr( wp_json_encode( $siblings ) ),
			(int) $frame_w,
			(int) $frame_h
		);
		echo '<div class="solo-estate-poly__toolbar">';
		printf( '<button type="button" class="button" data-poly-undo>%s</button> ', esc_html__( 'Undo last point', 'solo-estate' ) );
		printf( '<button type="button" class="button" data-poly-delete disabled>%s</button> ', esc_html__( 'Delete point', 'solo-estate' ) );
		printf( '<button type="button" class="button" data-poly-clean title="%2$s">%1$s</button> ', esc_html__( 'Clean up points', 'solo-estate' ), esc_attr__( 'Removes points that sit on top of each other (common in imported outlines)', 'solo-estate' ) );
		printf( '<button type="button" class="button" data-poly-clear>%s</button> ', esc_html__( 'Clear', 'solo-estate' ) );
		printf( '<label><input type="checkbox" data-poly-siblings checked> %s</label> ', esc_html__( 'Show other items', 'solo-estate' ) );
		printf( '<span class="solo-estate-poly__zoom"><span class="solo-estate-poly__zoom-level" data-poly-zoom-level>100%%</span><button type="button" class="button" data-poly-zoom="-1" aria-label="%1$s">−</button><button type="button" class="button" data-poly-zoom="1" aria-label="%2$s">+</button></span>', esc_attr__( 'Zoom out', 'solo-estate' ), esc_attr__( 'Zoom in', 'solo-estate' ) );
		echo '</div><div class="solo-estate-poly__viewport"><div class="solo-estate-poly__canvas"></div></div>';
		echo '<div class="solo-estate-poly__raw">';
		echo '<label class="solo-estate-poly__raw-label" for="solo-estate-coords">' . esc_html__( 'Coordinates', 'solo-estate' ) . '</label>';
		printf( '<textarea id="solo-estate-coords" name="coords" class="large-text code" rows="2" data-poly-input placeholder="%1$s">%2$s</textarea>', esc_attr__( 'Draw on the image or paste coordinates copied from another item', 'solo-estate' ), esc_textarea( $node ? $node->coords : '' ) );
		printf( '<button type="button" class="button" data-poly-copy>%1$s</button> <span class="description">%2$s</span>', esc_html__( 'Copy', 'solo-estate' ), esc_html__( 'Filled in automatically while you draw. Paste coordinates here to reuse an outline — the drawing updates instantly.', 'solo-estate' ) );
		echo '</div></div></div>';
	}

	/**
	 * Children list with bulk actions and an overview map.
	 *
	 * @param object $node Parent node.
	 */
	private static function render_children( $node ) {
		$levels   = Nodes::child_levels( $node->level );
		$children = Nodes::children( $node->id );
		$counts   = Nodes::child_counts( wp_list_pluck( $children, 'id' ) );
		$mixed    = count( $levels ) > 1;
		$can_edit = Admin::can_edit();

		echo '<div class="solo-estate-children">';
		echo '<h2 class="wp-heading-inline">' . esc_html( $mixed ? __( 'Inside', 'solo-estate' ) : Nodes::level_label( $levels[0], true ) ) . '</h2>';
		if ( $can_edit ) {
			foreach ( $levels as $child_level ) {
				printf(
					' <a class="page-title-action" href="%1$s">%2$s</a>',
					esc_url( Admin::url( 'solo-estate', array( 'action' => 'new', 'parent' => $node->id, 'level' => $child_level ) ) ),
					/* translators: %s: level name */
					esc_html( sprintf( __( 'Add %s', 'solo-estate' ), Nodes::level_label( $child_level ) ) )
				);
			}
		}
		if ( in_array( 'phase', $levels, true ) ) {
			echo '<p class="description">' . esc_html__( 'Phases are optional: add buildings directly when the project does not use them.', 'solo-estate' ) . '</p>';
		}

		if ( ! $children ) {
			echo '<p>' . esc_html__( 'Nothing here yet.', 'solo-estate' ) . '</p></div>';
			return;
		}

		// Overview map: every child polygon on this node's image.
		$src = $node->image_id ? wp_get_attachment_image_url( $node->image_id, 'full' ) : '';
		if ( $src ) {
			list( $w, $h ) = Renderer::image_size( $node->image_id );
			if ( $w && $h ) {
				echo '<details class="solo-estate-card solo-estate-overview" open><summary>' . esc_html__( 'Map', 'solo-estate' ) . '</summary><div class="solo-estate-overview__stage">';
				printf( '<img src="%s" alt="">', esc_url( $src ) );
				printf( '<svg viewBox="0 0 %1$d %2$d" preserveAspectRatio="none">', (int) $w, (int) $h );
				foreach ( $children as $child ) {
					$points = Nodes::points( $child->coords );
					if ( ! $points ) {
						continue;
					}
					$status = Statuses::get( $child->status_id );
					printf(
						'<a href="%1$s"><polygon points="%2$s" style="fill:%3$s"><title>%4$s</title></polygon></a>',
						esc_url( Admin::url( 'solo-estate', array( 'node' => $child->id ) ) ),
						esc_attr( self::points_attr( $points ) ),
						esc_attr( $status ? $status->color : ( 'commercial' === $child->level ? Settings::get( 'commercial_color' ) : '#2271b1' ) ),
						esc_html( Nodes::admin_title( $child ) )
					);
				}
				echo '</svg></div></details>';
			}
		}

		// Status choices, grouped by the kind of item they apply to.
		$scopes = array();
		foreach ( $children as $child ) {
			$scope = Statuses::scope_of( $child->level );
			if ( '' !== $scope ) {
				$scopes[ $scope ] = $child->level;
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'solo_estate_bulk_nodes' );
		echo '<input type="hidden" name="action" value="solo_estate_bulk_nodes">';
		printf( '<input type="hidden" name="parent" value="%d">', (int) $node->id );

		echo '<div class="tablenav top"><div class="alignleft actions bulkactions">';
		echo '<select name="bulk"><option value="">' . esc_html__( 'Bulk actions', 'solo-estate' ) . '</option>';
		foreach ( $scopes as $scope => $sample_level ) {
			if ( count( $scopes ) > 1 ) {
				printf( '<optgroup label="%s">', esc_attr( Nodes::level_label( $sample_level, true ) ) );
			}
			foreach ( Statuses::for_scope( $scope ) as $status ) {
				/* translators: %s: status name */
				printf( '<option value="status:%1$d">%2$s</option>', (int) $status->id, esc_html( sprintf( __( 'Set status: %s', 'solo-estate' ), Statuses::title( $status, I18n::default_code() ) ) ) );
			}
			if ( count( $scopes ) > 1 ) {
				echo '</optgroup>';
			}
		}
		if ( $can_edit ) {
			foreach ( array( Nodes::ACCESS_AUTO => __( 'Visitors: automatic', 'solo-estate' ), Nodes::ACCESS_OPEN => __( 'Visitors: always open', 'solo-estate' ), Nodes::ACCESS_CLOSED => __( 'Visitors: closed', 'solo-estate' ) ) as $key => $label ) {
				printf( '<option value="access:%1$d">%2$s</option>', (int) $key, esc_html( $label ) );
			}
		}
		if ( Admin::can_manage() ) {
			echo '<option value="delete">' . esc_html__( 'Delete', 'solo-estate' ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Apply', 'solo-estate' ), 'action', 'apply', false, array( 'data-solo-estate-bulk-confirm' => '1' ) );
		echo '</div></div>';

		echo '<table class="wp-list-table widefat fixed striped solo-estate-table"><thead><tr>';
		echo '<td class="manage-column check-column"><input type="checkbox" data-solo-estate-check-all></td>';
		echo '<th class="column-primary">' . esc_html__( 'Name', 'solo-estate' ) . '</th>';
		if ( $mixed ) {
			echo '<th>' . esc_html__( 'Type', 'solo-estate' ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Status', 'solo-estate' ) . '</th>';
		echo '<th>' . esc_html__( 'Area / items', 'solo-estate' ) . '</th>';
		echo '<th>' . esc_html__( 'Price / available', 'solo-estate' ) . '</th>';
		echo '<th>' . esc_html__( 'Sold', 'solo-estate' ) . '</th>';
		echo '<th>' . esc_html__( 'On map', 'solo-estate' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $children as $child ) {
			$edit = Admin::url( 'solo-estate', array( 'node' => $child->id ) );
			echo '<tr>';
			printf( '<th scope="row" class="check-column"><input type="checkbox" name="ids[]" value="%d"></th>', (int) $child->id );
			echo '<td class="column-primary">';
			printf( '<strong><a class="row-title" href="%1$s">%2$s</a></strong>', esc_url( $edit ), esc_html( Nodes::admin_title( $child ) ) );
			if ( Nodes::ACCESS_AUTO !== $child->access ) {
				printf( ' <span class="solo-estate-access solo-estate-access--%1$d">%2$s</span>', (int) $child->access, esc_html( Nodes::ACCESS_OPEN === $child->access ? __( 'always open', 'solo-estate' ) : __( 'closed', 'solo-estate' ) ) );
			}
			echo '<div class="row-actions">';
			$actions = array( sprintf( '<span class="edit"><a href="%1$s">%2$s</a></span>', esc_url( $edit ), esc_html( $can_edit ? __( 'Edit', 'solo-estate' ) : __( 'Open', 'solo-estate' ) ) ) );
			if ( $can_edit ) {
				$actions[] = sprintf( '<span><a href="%1$s">%2$s</a></span>', esc_url( self::action_url( 'solo_estate_duplicate_node', $child->id ) ), esc_html__( 'Duplicate', 'solo-estate' ) );
			}
			if ( Admin::can_manage() ) {
				$actions[] = sprintf( '<span class="trash"><a href="%1$s" data-solo-estate-confirm>%2$s</a></span>', esc_url( self::action_url( 'solo_estate_delete_node', $child->id ) ), esc_html__( 'Delete', 'solo-estate' ) );
			}
			echo implode( ' | ', $actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			echo '</div></td>';
			if ( $mixed ) {
				echo '<td>' . esc_html( Nodes::level_label( $child->level ) ) . '</td>';
			}
			echo '<td>' . Ui::status_pill( Statuses::get( $child->status_id ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( Nodes::is_unit( $child->level ) || in_array( $child->level, array( 'spot', 'villa_floor' ), true ) ) {
				echo '<td>' . ( null !== $child->area ? esc_html( number_format_i18n( $child->area, 2 ) . ' ' . Settings::get( 'area_unit' ) ) : '—' ) . '</td>';
				$total = Nodes::total_price( $child );
				echo '<td>' . ( null !== $total ? esc_html( number_format_i18n( $total ) . ' ' . Settings::get( 'base_currency' ) ) : '—' ) . '</td>';
				echo '<td>—</td>';
			} else {
				$stats = Nodes::stats( $child );
				echo '<td>' . (int) ( isset( $counts[ $child->id ] ) ? $counts[ $child->id ] : 0 ) . '</td>';
				printf( '<td>%1$d / %2$d</td>', (int) $stats['available'], (int) $stats['total'] );
				echo '<td>' . self::sold_meter( $stats ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '<td>';
			if ( 'villa_floor' === $child->level ) {
				echo '—';
			} elseif ( Nodes::points( $child->coords ) ) {
				echo '<span class="dashicons dashicons-yes-alt solo-estate-ok"></span> ';
				printf( '<button type="button" class="button-link solo-estate-copy-coords" data-coords="%1$s">%2$s</button>', esc_attr( $child->coords ), esc_html__( 'Copy coordinates', 'solo-estate' ) );
			} else {
				echo '<span class="dashicons dashicons-warning solo-estate-warn" title="' . esc_attr__( 'Not drawn on the image yet', 'solo-estate' ) . '"></span>';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></form></div>';
	}

	/**
	 * "x,y x,y" for an SVG points attribute.
	 *
	 * @param array $points Points.
	 * @return string
	 */
	public static function points_attr( array $points ) {
		return implode(
			' ',
			array_map(
				static function ( $p ) {
					return $p[0] . ',' . $p[1];
				},
				$points
			)
		);
	}

	/**
	 * Nonce-protected admin-post URL for a node action.
	 *
	 * @param string $action Action.
	 * @param int    $id     Node id.
	 * @return string
	 */
	private static function action_url( $action, $id ) {
		return wp_nonce_url( add_query_arg( array( 'action' => $action, 'node' => (int) $id ), admin_url( 'admin-post.php' ) ), $action . '_' . (int) $id );
	}

	/**
	 * Saves the edit form. Sales can only change the status of an existing item.
	 */
	public static function handle_save() {
		Admin::check( 'solo_estate_save_node', \SoloEstate\Install::CAP_SELL );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in Admin::check().
		$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$existing = $id ? Nodes::get( $id ) : null;
		if ( $id && ! $existing ) {
			Admin::redirect( Admin::url(), 'error' );
		}

		if ( ! Admin::can_edit() ) {
			if ( ! $existing ) {
				Admin::deny();
			}
			$status_id = isset( $_POST['status_id'] ) ? absint( $_POST['status_id'] ) : 0;
			Nodes::save( array( 'status_id' => self::valid_status( $status_id, $existing->level ) ), $existing->id );
			Admin::redirect( Admin::url( 'solo-estate', array( 'node' => $existing->id ) ) );
		}

		$parent_id = $existing ? $existing->parent_id : ( isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0 );
		$parent    = $parent_id ? Nodes::get( $parent_id ) : null;
		$level     = $existing ? $existing->level : ( isset( $_POST['level'] ) ? sanitize_key( wp_unslash( $_POST['level'] ) ) : '' );

		if ( ( $parent_id && ! $parent ) || ( ! $existing && ! Nodes::allows( $parent ? $parent->level : '', $level ) ) ) {
			Admin::redirect( Admin::url(), 'error' );
		}

		$raw_i18n = isset( $_POST['i18n'] ) && is_array( $_POST['i18n'] ) ? $_POST['i18n'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$types    = array(
			'title'       => 'text',
			'label'       => 'text',
			'completion'  => 'text',
			'link'        => 'url',
			'description' => 'html',
		);
		$i18n     = $existing ? $existing->i18n : array();
		foreach ( Nodes::I18N_FIELDS[ $level ] as $field ) {
			if ( isset( $raw_i18n[ $field ] ) ) {
				$i18n[ $field ] = Ui::read_i18n( $raw_i18n[ $field ], $types[ $field ] );
			}
		}

		$data = array(
			'parent_id'  => $parent_id,
			'level'      => $level,
			'number'     => isset( $_POST['number'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['number'] ) ), 0, 50 ) : '',
			'status_id'  => self::valid_status( isset( $_POST['status_id'] ) ? absint( $_POST['status_id'] ) : 0, $level ),
			'sort_order' => isset( $_POST['sort_order'] ) ? intval( $_POST['sort_order'] ) : 0,
			'i18n'       => $i18n,
		);
		// Only fields that are on this form; the others keep their value.
		foreach ( array( 'image_id', 'image2_id' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$data[ $key ] = absint( $_POST[ $key ] );
			}
		}
		if ( isset( $_POST['coords'] ) ) {
			$data['coords'] = Nodes::sanitize_coords( sanitize_text_field( wp_unslash( $_POST['coords'] ) ) );
		}
		if ( isset( $_POST['entrance'] ) ) {
			$data['entrance'] = mb_substr( sanitize_text_field( wp_unslash( $_POST['entrance'] ) ), 0, 50 );
		}
		if ( isset( $_POST['info_modal_field'] ) ) {
			$data['info_modal'] = empty( $_POST['open_page'] ) ? 1 : 0;
		}
		if ( isset( $_POST['access'] ) ) {
			$access         = absint( $_POST['access'] );
			$data['access'] = in_array( $access, array( Nodes::ACCESS_AUTO, Nodes::ACCESS_OPEN, Nodes::ACCESS_CLOSED ), true ) ? $access : Nodes::ACCESS_AUTO;
		}
		foreach ( array( 'area', 'area_living', 'area_summer', 'price_sqm', 'price_total' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$data[ $key ] = Ui::read_decimal( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- numeric only.
			}
		}
		// Empty total with area and price per m²: store the product (the form fills it in too).
		if ( array_key_exists( 'price_total', $data ) && null === $data['price_total'] && ! empty( $data['area'] ) && ! empty( $data['price_sqm'] ) ) {
			$data['price_total'] = round( $data['area'] * $data['price_sqm'], 2 );
		}
		if ( isset( $_POST['rooms'] ) ) {
			$rooms         = trim( sanitize_text_field( wp_unslash( $_POST['rooms'] ) ) );
			$data['rooms'] = '' === $rooms ? null : min( 20, absint( $rooms ) );
		}
		if ( isset( $_POST['gallery'] ) ) {
			$data['gallery'] = Ui::read_gallery( $_POST['gallery'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ids only.
		}
		if ( isset( $_POST['tour_url'] ) ) {
			$data['tour_url'] = mb_substr( esc_url_raw( wp_unslash( $_POST['tour_url'] ), array( 'https', 'http' ) ), 0, 255 );
		}

		$saved = Nodes::save( $data, $id );
		if ( ! $saved ) {
			Admin::redirect( Admin::url(), 'error' );
		}

		if ( in_array( $level, self::SPEC_LEVELS, true ) ) {
			$values = array();
			if ( isset( $_POST['specs'] ) && is_array( $_POST['specs'] ) ) {
				foreach ( wp_unslash( $_POST['specs'] ) as $field_id => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					if ( Specs::field( absint( $field_id ) ) ) {
						$values[ absint( $field_id ) ] = sanitize_text_field( $value );
					}
				}
			}
			if ( isset( $_POST['new_specs'] ) && is_array( $_POST['new_specs'] ) ) {
				foreach ( array_slice( wp_unslash( $_POST['new_specs'] ), 0, 30 ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$title = isset( $row['title'] ) ? mb_substr( sanitize_text_field( $row['title'] ), 0, 100 ) : '';
					if ( '' === $title ) {
						continue;
					}
					$field_id = Specs::find_or_create( $title, isset( $row['unit'] ) ? sanitize_text_field( $row['unit'] ) : '' );
					if ( $field_id && isset( $row['value'] ) ) {
						$values[ $field_id ] = sanitize_text_field( $row['value'] );
					}
				}
			}
			Specs::save_values( $saved, $values );
		}
		// phpcs:enable

		Admin::redirect( Admin::url( 'solo-estate', array( 'node' => $saved ) ) );
	}

	/**
	 * A status id if it belongs to the level's status list, 0 otherwise.
	 *
	 * @param int    $status_id Status.
	 * @param string $level     Level.
	 * @return int
	 */
	private static function valid_status( $status_id, $level ) {
		$status = $status_id ? Statuses::get( $status_id ) : null;
		return ( $status && $status->scope === Statuses::scope_of( $level ) ) ? (int) $status->id : 0;
	}

	/**
	 * Deletes a node and everything inside (managers only).
	 */
	public static function handle_delete() {
		$id = isset( $_GET['node'] ) ? absint( $_GET['node'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		Admin::check( 'solo_estate_delete_node_' . $id );

		$node = Nodes::get( $id );
		if ( ! $node ) {
			Admin::redirect( Admin::url(), 'error' );
		}
		Nodes::delete( $id );
		Admin::redirect( $node->parent_id ? Admin::url( 'solo-estate', array( 'node' => $node->parent_id ) ) : Admin::url(), 'deleted' );
	}

	/**
	 * Duplicates a node; numeric floor/building numbers are incremented.
	 */
	public static function handle_duplicate() {
		$id = isset( $_GET['node'] ) ? absint( $_GET['node'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		Admin::check( 'solo_estate_duplicate_node_' . $id, \SoloEstate\Install::CAP_EDIT );

		$node   = Nodes::get( $id );
		$new_id = ( $node && 'project' !== $node->level ) ? Nodes::duplicate( $id ) : 0;
		if ( ! $new_id ) {
			Admin::redirect( Admin::url(), 'error' );
		}
		if ( is_numeric( $node->number ) && in_array( $node->level, array( 'floor', 'building', 'phase', 'parking', 'villa_floor' ), true ) ) {
			Nodes::save( array( 'number' => (string) ( (int) $node->number + 1 ) ), $new_id );
		}
		Admin::redirect( Admin::url( 'solo-estate', array( 'node' => $new_id ) ), 'duplicated' );
	}

	/**
	 * Copies apartment outlines (and optionally plan, area, specs) from one floor to others.
	 */
	public static function handle_apply_layout() {
		$id = isset( $_POST['node'] ) ? absint( $_POST['node'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified next.
		Admin::check( 'solo_estate_apply_layout_' . $id, \SoloEstate\Install::CAP_EDIT );

		$source = Nodes::get( $id );
		if ( ! $source || 'floor' !== $source->level ) {
			Admin::redirect( Admin::url(), 'error' );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$targets      = isset( $_POST['targets'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['targets'] ) ) : array();
		$copy_image   = ! empty( $_POST['copy_image'] );
		$copy_details = ! empty( $_POST['copy_details'] );
		$overwrite    = ! empty( $_POST['overwrite'] );
		// phpcs:enable

		$back    = Admin::url( 'solo-estate', array( 'node' => $source->id ) );
		$flats   = Nodes::sorted_children( $source->id );
		$allowed = wp_list_pluck( Nodes::children( $source->parent_id, 'floor' ), 'id' );
		$changed = 0;

		foreach ( array_intersect( $targets, $allowed ) as $target_id ) {
			if ( $target_id === $source->id ) {
				continue;
			}
			$target = Nodes::get( $target_id );
			if ( $copy_image && $source->image_id && ( $overwrite || ! $target->image_id ) ) {
				Nodes::save( array( 'image_id' => $source->image_id ), $target->id );
			}
			foreach ( Nodes::sorted_children( $target->id ) as $i => $flat ) {
				if ( ! isset( $flats[ $i ] ) ) {
					break;
				}
				$from = $flats[ $i ];
				$data = array();
				if ( '' !== (string) $from->coords && ( $overwrite || ! Nodes::points( $flat->coords ) ) ) {
					$data['coords'] = $from->coords;
				}
				if ( $copy_details ) {
					foreach ( array( 'area', 'area_living', 'area_summer', 'rooms' ) as $column ) {
						$data[ $column ] = $from->$column;
					}
					Specs::save_values( $flat->id, Specs::values( $from->id ) );
				}
				if ( $data ) {
					Nodes::save( $data, $flat->id );
					$changed++;
				}
			}
		}
		wp_safe_redirect( add_query_arg( array( 'solo_estate_msg' => 'layout', 'solo_estate_count' => $changed ), $back ) );
		exit;
	}

	/**
	 * Bulk status / visitor access / delete on children.
	 */
	public static function handle_bulk() {
		Admin::check( 'solo_estate_bulk_nodes', \SoloEstate\Install::CAP_SELL );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in Admin::check().
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		$bulk   = isset( $_POST['bulk'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk'] ) ) : '';
		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		// phpcs:enable

		// Only act on direct children of the parent shown on screen.
		$children = array();
		foreach ( Nodes::children( $parent ) as $child ) {
			if ( in_array( $child->id, $ids, true ) ) {
				$children[ $child->id ] = $child;
			}
		}
		$back = Admin::url( 'solo-estate', array( 'node' => $parent ) );

		if ( ! $children || '' === $bulk ) {
			Admin::redirect( $back, 'error' );
		}
		if ( 'delete' === $bulk ) {
			if ( ! Admin::can_manage() ) {
				Admin::deny();
			}
			foreach ( array_keys( $children ) as $id ) {
				Nodes::delete( $id );
			}
			Admin::redirect( $back, 'deleted' );
		}
		if ( 0 === strpos( $bulk, 'access:' ) ) {
			$access = absint( substr( $bulk, 7 ) );
			if ( ! Admin::can_edit() || ! in_array( $access, array( Nodes::ACCESS_AUTO, Nodes::ACCESS_OPEN, Nodes::ACCESS_CLOSED ), true ) ) {
				Admin::redirect( $back, 'error' );
			}
			foreach ( array_keys( $children ) as $id ) {
				Nodes::save( array( 'access' => $access ), $id );
			}
			Admin::redirect( $back, 'updated' );
		}
		if ( 0 === strpos( $bulk, 'status:' ) ) {
			$status = Statuses::get( absint( substr( $bulk, 7 ) ) );
			if ( ! $status ) {
				Admin::redirect( $back, 'error' );
			}
			// A status only applies to the kinds of item it was made for.
			$matching = array();
			foreach ( $children as $child ) {
				if ( Statuses::scope_of( $child->level ) === $status->scope ) {
					$matching[] = $child->id;
				}
			}
			if ( ! $matching ) {
				Admin::redirect( $back, 'error' );
			}
			Nodes::bulk_status( $matching, $status->id );
			Admin::redirect( $back, 'updated' );
		}
		Admin::redirect( $back, 'error' );
	}
}
