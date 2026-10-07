<?php
/**
 * Builds the interactive front end. Templates live in /templates and can be
 * overridden by a theme in /solo-estate/{name}.php.
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

use SoloEstate\I18n;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Statuses;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

class Renderer {

	const QUERY_ARG = 'solo_estate_node';

	/** @var bool Rendering the all-projects catalog (breadcrumbs start at "Projects"). */
	private static $catalog = false;

	/** @var string Element id of the app, used as URL anchor. */
	private static $anchor = 'solo-estate-app';

	/** @var object[] Info-window items linked while rendering, by id; their windows are printed once at the end. */
	private static $info = array();

	/**
	 * Renders the catalog (all projects) or one project, at the level requested in the URL.
	 *
	 * @param int  $project_id Project id; 0 = catalog of all projects.
	 * @param bool $show_title Show the project name on the project level.
	 * @return string
	 */
	public static function project( $project_id, $show_title = true ) {
		self::$catalog = ! $project_id;
		$project       = null;

		if ( ! self::$catalog ) {
			$project = Nodes::get( $project_id );
			if ( ! $project || 'project' !== $project->level ) {
				return current_user_can( 'manage_solo_estate' ) ? '<p class="solo-estate-notice">' . esc_html__( 'Solo Estate: project not found. Check the id in the shortcode.', 'solo-estate' ) . '</p>' : '';
			}
		}
		self::$anchor = self::$catalog ? 'solo-estate-catalog' : 'solo-estate-' . $project->id;

		$current = $project;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only navigation.
		$requested = isset( $_GET[ self::QUERY_ARG ] ) ? absint( $_GET[ self::QUERY_ARG ] ) : 0;
		if ( $requested && ( ! $project || $requested !== $project->id ) ) {
			$node = Nodes::get( $requested );
			if ( $node && ( self::$catalog || $node->project_id === $project->id ) ) {
				// A closed item (e.g. a sold apartment from an old link) shows its nearest open parent.
				foreach ( Nodes::ancestors( $node ) as $item ) {
					if ( ! self::is_open( $item ) && ( 'project' !== $item->level || self::$catalog ) ) {
						break;
					}
					$current = $item;
				}
			}
		}
		// Optional levels: a project, phase or building without an image and with a
		// single item inside shows that item straight away.
		$guard = 0;
		while ( $current && self::skips( $current ) && $guard++ < 5 ) {
			$current = Nodes::children( $current->id )[0];
		}

		$scope = $current ? Nodes::get( $current->project_id ? $current->project_id : $current->id ) : null;

		if ( Search::is_active() && Settings::get( 'show_filter' ) ) {
			$level = 'search';
			$html  = Search::render( self::$catalog ? null : $project );
		} elseif ( ! $current ) {
			$level = 'catalog';
			$html  = self::template( 'catalog', array( 'projects' => Nodes::projects() ) );
		} else {
			$level = $current->level;
			$html  = self::template(
				self::template_for( $current->level ),
				array(
					'project'    => $scope,
					'node'       => $current,
					'chain'      => Nodes::ancestors( $current ),
					'show_title' => $show_title && ! self::$catalog,
				)
			);
		}

		// Contents of the info windows linked above (boulevard, sports field…), opened by JS.
		foreach ( self::$info as $info_node ) {
			$html .= sprintf( '<template id="solo-estate-info-%1$d">%2$s</template>', (int) $info_node->id, self::template( 'info', array( 'node' => $info_node ) ) );
		}
		self::$info = array();

		return sprintf(
			'<div class="solo-estate-app solo-estate-app--%1$s solo-estate-tips--%5$s alignwide" id="%2$s" data-currency="%3$s" data-version="%6$s">%4$s</div>',
			esc_attr( $level ),
			esc_attr( self::$anchor ),
			esc_attr( Settings::get( 'alt_enabled' ) ? Settings::get( 'default_currency' ) : 'base' ),
			$html,
			'light' === Settings::get( 'tooltip_style' ) ? 'light' : 'dark',
			esc_attr( SOLO_ESTATE_VERSION )
		);
	}

	/**
	 * Whether the catalog (all projects) is being rendered.
	 *
	 * @return bool
	 */
	public static function is_catalog() {
		return self::$catalog;
	}

	/**
	 * Current page URL without Solo Estate navigation and filter arguments.
	 *
	 * @return string
	 */
	public static function base_url() {
		return remove_query_arg( array_merge( array( self::QUERY_ARG ), Search::ARGS ) );
	}

	/**
	 * Link to the catalog root (or the single project).
	 *
	 * @return string
	 */
	public static function root_url() {
		return self::base_url() . '#' . self::$anchor;
	}

	/**
	 * Link to a node on the current page.
	 *
	 * @param object $node Node.
	 * @return string
	 */
	public static function url( $node ) {
		$base = self::base_url();
		$url  = ( 'project' === $node->level && ! self::$catalog ) ? $base : add_query_arg( self::QUERY_ARG, $node->id, $base );
		return $url . '#' . self::$anchor;
	}

	/**
	 * Renders a template with variables.
	 *
	 * @param string $name Template name without .php.
	 * @param array  $vars Variables.
	 * @return string
	 */
	public static function template( $name, array $vars = array() ) {
		$file = locate_template( array( 'solo-estate/' . $name . '.php' ) );
		if ( ! $file ) {
			$file = SOLO_ESTATE_DIR . 'templates/' . $name . '.php';
		}
		if ( ! is_readable( $file ) ) {
			return '';
		}
		ob_start();
		( static function ( $__file, $__vars ) {
			extract( $__vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include $__file;
		} )( $file, $vars );
		return (string) ob_get_clean();
	}

	/**
	 * Template used for a level. Phases share the project page, commercial spaces and villas
	 * the unit page.
	 *
	 * @param string $level Level.
	 * @return string
	 */
	public static function template_for( $level ) {
		$map = array(
			'phase'      => 'project',
			'commercial' => 'flat',
			'villa'      => 'flat',
		);
		return isset( $map[ $level ] ) ? $map[ $level ] : $level;
	}

	/**
	 * Whether visitors can open a node's page.
	 *
	 * "Always open" / "Closed" set on the item win. Otherwise the status decides, and sold
	 * out phases and buildings close when that setting is on. Parking spaces and villa floors
	 * never have their own page.
	 *
	 * @param object $node Node.
	 * @return bool
	 */
	public static function is_open( $node ) {
		if ( in_array( $node->level, array( 'spot', 'villa_floor' ), true ) ) {
			return false;
		}
		if ( Nodes::ACCESS_OPEN === $node->access ) {
			return true;
		}
		if ( Nodes::ACCESS_CLOSED === $node->access ) {
			return false;
		}
		// Info-window items have no sale status to follow.
		if ( self::is_info( $node ) ) {
			return true;
		}
		if ( ! Statuses::is_clickable( Statuses::get( $node->status_id ) ) ) {
			return false;
		}
		if ( in_array( $node->level, array( 'phase', 'building' ), true ) && Settings::get( 'sold_out_nolink' ) && self::state( $node )['sold_out'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Status a project shows on the projects page: a sold-out project (every unit sold) moves
	 * to the project status marked "Sold-out projects move here" (e.g. Completed); otherwise
	 * its own status.
	 *
	 * @param object $project Project.
	 * @return object|null
	 */
	public static function project_status( $project ) {
		$stats = Nodes::stats( $project );
		if ( $stats['total'] > 0 && $stats['sold'] === $stats['total'] ) {
			foreach ( Statuses::for_scope( 'project' ) as $status ) {
				if ( ! empty( $status->sold ) ) {
					return $status;
				}
			}
		}
		return Statuses::get( $project->status_id );
	}

	/**
	 * Whether a node opens an info window (photos + description) instead of its page:
	 * a boulevard, sports field or park with "Clicking it opens the detailed page" off.
	 *
	 * @param object $node Node.
	 * @return bool
	 */
	public static function is_info( $node ) {
		return ! empty( $node->info_modal ) && in_array( $node->level, Nodes::INFO_LEVELS, true );
	}

	/**
	 * Whether an optional level is skipped: no image and exactly one item inside that is
	 * not a unit (e.g. a project with one building and no masterplan).
	 *
	 * @param object $node Node.
	 * @return bool
	 */
	public static function skips( $node ) {
		if ( $node->image_id || ! in_array( $node->level, array( 'project', 'phase', 'building' ), true ) ) {
			return false;
		}
		$children = Nodes::children( $node->id );
		return 1 === count( $children ) && ! Nodes::is_unit( $children[0]->level ) && self::is_open( $children[0] );
	}

	/**
	 * Where clicking a child leads: internal link, external link, or nowhere.
	 *
	 * @param object $child Child node.
	 * @return array{href:string,external:bool}
	 */
	public static function target( $child ) {
		if ( ! self::is_open( $child ) ) {
			return array(
				'href'     => '',
				'external' => false,
			);
		}
		if ( self::is_info( $child ) ) {
			self::$info[ $child->id ] = $child;
			return array(
				'href'     => '#solo-estate-info-' . $child->id,
				'external' => false,
				'info'     => true,
			);
		}
		$external = 'building' === $child->level ? Nodes::field( $child, 'link' ) : '';
		if ( '' !== $external ) {
			return array(
				'href'     => $external,
				'external' => true,
			);
		}
		return array(
			'href'     => self::url( $child ),
			'external' => false,
		);
	}

	/**
	 * Image with clickable SVG polygons for the children.
	 *
	 * @param object   $node     Node whose image is shown.
	 * @param object[] $children Children drawn on it.
	 * @return string
	 */
	public static function stage( $node, array $children ) {
		if ( ! $node->image_id ) {
			return '';
		}
		list( $w, $h ) = self::image_size( $node->image_id );
		$img           = wp_get_attachment_image(
			$node->image_id,
			'full',
			false,
			array(
				'class'    => 'solo-estate-stage__img',
				'loading'  => 'eager',
				'alt'      => Nodes::display_title( $node ),
				'decoding' => 'async',
			)
		);
		if ( ! $w || ! $h ) {
			return '<div class="solo-estate-stage"><div class="solo-estate-stage__frame">' . $img . '</div>' . self::fullscreen_button() . '</div>';
		}

		$shapes = '';
		$tips   = '';
		foreach ( $children as $child ) {
			$points = Nodes::points( $child->coords );
			if ( ! $points ) {
				continue;
			}
			$status = Statuses::get( $child->status_id );
			$target = self::target( $child );
			$state  = self::state( $child );
			$style  = '--solo-estate-shape:' . $state['color'];
			// Commercial spaces for sale / rent get their own colours to stand out from apartments.
			if ( 'commercial' === $child->level && ( ! $status || $status->available ) ) {
				$style = '--solo-estate-shape:' . Settings::get( 'commercial_color' ) . ';--solo-estate-shape-hover:' . Settings::get( 'commercial_hover' );
			}
			$tip_id = 'solo-estate-tip-' . $child->id;
			$poly   = sprintf(
				'<polygon points="%1$s"></polygon>',
				esc_attr(
					implode(
						' ',
						array_map(
							static function ( $p ) {
								return $p[0] . ',' . $p[1];
							},
							$points
						)
					)
				)
			);
			$attrs = sprintf(
				'class="solo-estate-shape solo-estate-shape--%5$s%1$s" style="%2$s" data-tip="%3$s" aria-label="%4$s"',
				( $target['href'] ? ' is-link' : ' is-disabled' ) . ( $state['sold_out'] || ( ! Nodes::is_unit( $child->level ) && 'spot' !== $child->level && ! $target['href'] ) ? ' is-sold-out' : '' ),
				esc_attr( $style ),
				esc_attr( $tip_id ),
				esc_attr( Nodes::display_title( $child ) ),
				esc_attr( $child->level )
			);
			if ( $target['href'] ) {
				$shapes .= sprintf( '<a href="%1$s"%2$s %3$s>%4$s</a>', esc_url( $target['href'] ), $target['external'] ? ' target="_blank" rel="noopener"' : '', $attrs, $poly );
			} else {
				$shapes .= sprintf( '<g %1$s>%2$s</g>', $attrs, $poly );
			}
			$tips .= sprintf( '<template id="%1$s">%2$s</template>', esc_attr( $tip_id ), self::template( 'tooltip', array( 'node' => $child, 'status' => $status, 'target' => $target ) ) );
		}

		// The frame holds the image and its polygons together, so on full screen they scale as one.
		return sprintf(
			'<div class="solo-estate-stage" style="--solo-estate-ratio:%7$s"><div class="solo-estate-stage__frame">%1$s<svg class="solo-estate-stage__svg" viewBox="0 0 %2$d %3$d" preserveAspectRatio="none" role="group">%4$s</svg></div><div class="solo-estate-tip" role="tooltip" data-close="%6$s" hidden></div>%8$s%5$s</div>',
			$img,
			(int) $w,
			(int) $h,
			$shapes,
			$tips,
			esc_attr( Texts::get( 'close' ) ),
			sprintf( '%.4F', $w / $h ),
			self::fullscreen_button()
		);
	}

	/**
	 * "Full screen" button of an image with polygons; the polygons and tooltips go along.
	 * One label for each state, switched by CSS (frontend.js toggles .is-fullscreen).
	 *
	 * @return string
	 */
	public static function fullscreen_button() {
		$open = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>';
		$shut = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>';
		return sprintf(
			'<button type="button" class="solo-estate-fs" data-solo-estate-fullscreen><span class="solo-estate-fs__on">%1$s<span class="solo-estate-fs__text">%2$s</span></span><span class="solo-estate-fs__off">%3$s<span class="solo-estate-fs__text">%4$s</span></span></button>',
			$open,
			esc_html( Texts::get( 'fullscreen' ) ),
			$shut,
			esc_html( Texts::get( 'fullscreen_exit' ) )
		);
	}

	/**
	 * Natural pixel size of an attachment.
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
	 * Price in base currency with the optional second currency, switched in JS.
	 *
	 * @param float|null $amount Amount in base currency.
	 * @return string
	 */
	public static function price( $amount ) {
		if ( null === $amount || ! Settings::get( 'show_prices' ) ) {
			return '';
		}
		$base = sprintf( '<span class="solo-estate-price__value" data-cur="base">%s</span>', esc_html( self::money( $amount, Settings::get( 'base_symbol' ) ) ) );
		if ( ! Settings::get( 'alt_enabled' ) ) {
			return '<span class="solo-estate-price">' . $base . '</span>';
		}
		$alt = sprintf( '<span class="solo-estate-price__value" data-cur="alt">%s</span>', esc_html( self::money( $amount * \SoloEstate\Rates::rate(), Settings::get( 'alt_symbol' ) ) ) );
		return '<span class="solo-estate-price">' . $base . $alt . '</span>';
	}

	/**
	 * Currency switcher buttons.
	 *
	 * @return string
	 */
	public static function currency_switch() {
		if ( ! Settings::get( 'show_prices' ) || ! Settings::get( 'alt_enabled' ) ) {
			return '';
		}
		$first  = 'base' === Settings::get( 'default_currency' ) ? 'base' : 'alt';
		$second = 'base' === $first ? 'alt' : 'base';
		$labels = array(
			'base' => Settings::get( 'base_symbol' ) ? Settings::get( 'base_symbol' ) : Settings::get( 'base_currency' ),
			'alt'  => Settings::get( 'alt_symbol' ) ? Settings::get( 'alt_symbol' ) : Settings::get( 'alt_currency' ),
		);
		return sprintf(
			'<span class="solo-estate-currency" role="group" aria-label="%5$s"><button type="button" data-set-cur="%1$s">%3$s</button><button type="button" data-set-cur="%2$s">%4$s</button></span>',
			esc_attr( $first ),
			esc_attr( $second ),
			esc_html( $labels[ $first ] ),
			esc_html( $labels[ $second ] ),
			esc_attr( Texts::get( 'price' ) )
		);
	}

	/**
	 * Formatted amount with symbol.
	 *
	 * @param float  $amount Amount.
	 * @param string $symbol Symbol.
	 * @return string
	 */
	public static function money( $amount, $symbol ) {
		$formatted = number_format_i18n( round( (float) $amount ) ) . ' ' . $symbol;
		/**
		 * Filters a formatted price.
		 *
		 * @param string $formatted Formatted price.
		 * @param float  $amount    Amount.
		 * @param string $symbol    Currency symbol.
		 */
		return (string) apply_filters( 'solo_estate_format_money', trim( $formatted ), $amount, $symbol );
	}

	/**
	 * Area with unit.
	 *
	 * @param float|null $area Area.
	 * @return string
	 */
	public static function area( $area ) {
		if ( null === $area || $area <= 0 ) {
			return '';
		}
		return self::number( $area ) . ' ' . Settings::get( 'area_unit' );
	}

	/**
	 * Number with only the decimals it needs (52 → "52", 52.5 → "52.5").
	 *
	 * @param float $value Value.
	 * @return string
	 */
	public static function number( $value ) {
		$value    = round( (float) $value, 2 );
		$decimals = ( floor( $value ) === $value ) ? 0 : ( round( $value, 1 ) === $value ? 1 : 2 );
		return number_format_i18n( $value, $decimals );
	}

	/**
	 * What to show for a node: its status, or "Sold out" for buildings/floors whose
	 * apartments are all closed (when enabled and no closed status was set by hand).
	 *
	 * @param object $node Node.
	 * @return array{label:string,color:string,sold_out:bool}
	 */
	public static function state( $node ) {
		if ( self::is_info( $node ) ) {
			return array(
				'label'    => '',
				'color'    => (string) Settings::get( 'accent_color' ),
				'sold_out' => false,
			);
		}
		$status = Statuses::get( $node->status_id );
		$state  = array(
			'label'    => Statuses::title( $status ),
			'color'    => $status ? $status->color : (string) Settings::get( 'accent_color' ),
			'sold_out' => false,
		);
		if ( in_array( $node->level, array( 'project', 'phase', 'building', 'floor', 'parking' ), true ) && Settings::get( 'auto_sold_out' ) && Statuses::is_clickable( $status ) ) {
			// Sold out = every unit has a "sold" status. Reserved or rented ones do not count.
			$stats = Nodes::stats( $node );
			if ( $stats['total'] > 0 && $stats['sold'] === $stats['total'] ) {
				$state = array(
					'label'    => Texts::get( 'sold_out' ),
					'color'    => (string) Settings::get( 'sold_color' ),
					'sold_out' => true,
				);
			} elseif ( $stats['total'] > 0 && 0 === $stats['available'] ) {
				// Nothing left for sale, but some units are reserved or rented: it no longer reads
				// "For sale". It stays open and its counts still show the reserved ones.
				$state['label'] = Texts::get( 'sold_out' );
				$state['color'] = (string) Settings::get( 'sold_color' );
			}
		}
		return $state;
	}

	/**
	 * "Available: N" for buildings/floors that have apartments and are on sale; '' otherwise
	 * (sold out, closed by status, or no apartments at all — e.g. a sports field).
	 *
	 * @param object $node Node.
	 * @return string Plain text.
	 */
	public static function available_label( $node ) {
		if ( Nodes::is_unit( $node->level ) || self::state( $node )['sold_out'] || ! Statuses::is_clickable( Statuses::get( $node->status_id ) ) || Nodes::ACCESS_CLOSED === $node->access ) {
			return '';
		}
		if ( 0 === Nodes::flat_count( $node ) ) {
			return '';
		}
		return sprintf( Texts::get( 'available_count' ), Nodes::available_count( $node ) );
	}

	/**
	 * Unit counts of a building, floor or parking as [label, count, type] pairs: available
	 * first, then every other status by its own name (Reserved, For rent, Rented…), then sold.
	 * Type is 'available', 'status' or 'sold'. "Available: 0" is left out when other counts say
	 * more (the item then shows its "Sold" badge, see state()). Empty for sold-out items and
	 * items without units.
	 *
	 * @param object $node      Node.
	 * @param bool   $with_sold Include the sold count.
	 * @return array<int,array{0:string,1:int,2:string}>
	 */
	public static function count_parts( $node, $with_sold = true ) {
		$stats = Nodes::stats( $node );
		if ( ! $stats['total'] || $stats['sold'] === $stats['total'] || self::state( $node )['sold_out'] ) {
			return array();
		}
		$parts  = array();
		$others = Statuses::for_level( 'parking' === $node->level ? 'spot' : 'flat' );
		foreach ( $others as $status ) {
			$count = isset( $stats['by_status'][ (int) $status->id ] ) ? $stats['by_status'][ (int) $status->id ] : 0;
			if ( $count && ! $status->available && ! $status->sold ) {
				$parts[] = array( Statuses::title( $status ), $count, 'status' );
			}
		}
		if ( $with_sold && $stats['sold'] ) {
			$parts[] = array( Texts::get( 'sold_label' ), $stats['sold'], 'sold' );
		}
		if ( $stats['available'] || ! $parts ) {
			array_unshift( $parts, array( Texts::get( 'available_label' ), $stats['available'], 'available' ) );
		}
		return $parts;
	}

	/**
	 * "Sold: 42%" for items with units, '' when nothing is sold yet or there are no units.
	 *
	 * @param object $node Node.
	 * @return string Plain text.
	 */
	public static function sold_label( $node ) {
		$stats = Nodes::stats( $node );
		return ( $stats['total'] && $stats['sold'] ) ? sprintf( Texts::get( 'sold_percent' ), $stats['percent'] ) : '';
	}

	/**
	 * Area facts of a unit: total, living, summer.
	 *
	 * @param object $node Unit or villa floor.
	 * @return array<int,array{0:string,1:string}> [label, value].
	 */
	public static function areas( $node ) {
		$out = array();
		foreach ( array( 'area' => 'total_area', 'area_living' => 'living_area', 'area_summer' => 'summer_area' ) as $column => $key ) {
			$value = self::area( $node->$column );
			if ( '' !== $value ) {
				$out[] = array( Texts::get( $key ), $value );
			}
		}
		return $out;
	}

	/**
	 * Small line icon (inline SVG, inherits the text colour).
	 *
	 * @param string $name area|rooms|floor|entrance|building|tour|phone|zoom.
	 * @return string
	 */
	public static function icon( $name ) {
		$paths = array(
			'area'     => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/><rect x="8" y="8" width="8" height="8" rx="1"/>',
			'rooms'    => '<path d="M3 20V11l9-7 9 7v9"/><path d="M9 20v-6h6v6"/>',
			'floor'    => '<path d="M12 3 2 8l10 5 10-5-10-5Z"/><path d="m2 13 10 5 10-5"/><path d="m2 17.5 10 5 10-5" opacity=".5"/>',
			'entrance' => '<path d="M14 3H6v18h8"/><path d="M14 3l5 2v14l-5 2V3Z"/><circle cx="16" cy="12" r=".6" fill="currentColor"/>',
			'building' => '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M10.5 21v-3h3v3"/>',
			'tour'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3Z"/>',
			'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
			'zoom'     => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M11 8v6M8 11h6"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="solo-estate-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Embeddable URL of a virtual tour, or '' when it is not a safe http(s) link.
	 *
	 * @param object $node Unit.
	 * @return string
	 */
	public static function tour_url( $node ) {
		$url    = (string) $node->tour_url;
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		return ( '' !== $url && in_array( $scheme, array( 'http', 'https' ), true ) ) ? $url : '';
	}

	/**
	 * Badge for a node based on state().
	 *
	 * @param object $node Node.
	 * @return string
	 */
	public static function node_badge( $node ) {
		$state = self::state( $node );
		if ( '' === $state['label'] ) {
			return '';
		}
		return sprintf( '<span class="solo-estate-badge%3$s" style="--solo-estate-shape:%1$s">%2$s</span>', esc_attr( $state['color'] ), esc_html( $state['label'] ), $state['sold_out'] ? ' is-sold-out' : '' );
	}

	/**
	 * Status badge.
	 *
	 * @param object|null $status Status.
	 * @return string
	 */
	public static function badge( $status ) {
		if ( ! $status ) {
			return '';
		}
		return sprintf( '<span class="solo-estate-badge" style="--solo-estate-shape:%1$s">%2$s</span>', esc_attr( $status->color ), esc_html( Statuses::title( $status ) ) );
	}

	/**
	 * Whether the lead form should be shown for a level.
	 *
	 * @param string $level Level.
	 * @return bool
	 */
	public static function show_lead_form( $level ) {
		if ( ! Settings::get( 'leads_enabled' ) ) {
			return false;
		}
		$where = Settings::get( 'lead_show_on' );
		return 'all' === $where || ( 'flat' === $where && Nodes::is_unit( $level ) );
	}

	/**
	 * Breadcrumbs for the chain.
	 *
	 * @param object[] $chain Nodes from project to current.
	 * @return string
	 */
	public static function breadcrumbs( array $chain ) {
		$items = array();
		if ( self::$catalog ) {
			$items[] = array( Texts::get( 'projects' ), self::root_url() );
		}
		foreach ( $chain as $i => $item ) {
			// Levels that are skipped automatically would only lead back to the same page.
			if ( $i > 0 && $i < count( $chain ) - 1 && self::skips( $item ) ) {
				continue;
			}
			$items[] = array( Nodes::display_title( $item ), self::url( $item ) );
		}
		if ( count( $items ) < 2 ) {
			return '';
		}
		$last  = count( $items ) - 1;
		$parts = array();
		foreach ( $items as $i => $item ) {
			$parts[] = $i === $last
				? '<span aria-current="page">' . esc_html( $item[0] ) . '</span>'
				: '<a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a>';
		}
		return sprintf(
			'<nav class="solo-estate-crumbs" aria-label="breadcrumbs"><a class="solo-estate-back" href="%1$s" aria-label="%2$s">←</a>%3$s</nav>',
			esc_url( $items[ $last - 1 ][1] ),
			esc_attr( Texts::get( 'back' ) ),
			implode( '<span class="solo-estate-crumbs__sep">/</span>', $parts )
		);
	}

	/**
	 * Legend of statuses used by the given nodes.
	 *
	 * @param object[] $nodes Nodes.
	 * @return string
	 */
	public static function legend( array $nodes ) {
		$used = array();
		foreach ( $nodes as $node ) {
			if ( $node->status_id ) {
				$used[ $node->status_id ] = true;
			}
		}
		$out = '';
		foreach ( Statuses::all() as $status ) {
			if ( isset( $used[ $status->id ] ) ) {
				$out .= self::badge( $status );
			}
		}
		return $out ? '<div class="solo-estate-legend">' . $out . '</div>' : '';
	}

	/**
	 * Current language code (templates).
	 *
	 * @return string
	 */
	public static function lang() {
		return I18n::current();
	}
}
