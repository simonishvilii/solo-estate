<?php
/**
 * Tooltip shown when hovering a polygon.
 *
 * Override by copying to yourtheme/solo-estate/tooltip.php.
 *
 * @package SoloEstate
 *
 * @var object      $node   Hovered node.
 * @var object|null $status Its status.
 * @var array       $target ['href' => string, 'external' => bool].
 */

use SoloEstate\Frontend\Renderer;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Statuses;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

$solo_estate_state = Renderer::state( $node );
$solo_estate_stats = array(); // [label, value HTML (escaped)].
$solo_estate_thumb = '';
$solo_estate_meter = null;    // [count, label, total, show percentage].

switch ( $node->level ) {
	case 'spot':
		// Parking spaces have no page: the tooltip is all there is — area, status, price.
		$solo_estate_area = Renderer::area( $node->area );
		if ( '' !== $solo_estate_area ) {
			$solo_estate_stats[] = array( Texts::get( 'area' ), esc_html( $solo_estate_area ) );
		}
		$solo_estate_price = Statuses::is_clickable( $status ) ? Renderer::price( Nodes::total_price( $node ) ) : '';
		if ( '' !== $solo_estate_price ) {
			$solo_estate_stats[] = array( Texts::get( 'price' ), $solo_estate_price );
		}
		break;

	case 'flat':
	case 'commercial':
	case 'villa':
		// Where it is: building, floor, entrance.
		$solo_estate_building = Nodes::ancestor( $node, 'building' );
		if ( $solo_estate_building ) {
			$solo_estate_stats[] = array( Texts::get( 'building' ), esc_html( '' !== $solo_estate_building->number ? $solo_estate_building->number : Nodes::display_title( $solo_estate_building ) ) );
		}
		$solo_estate_floor = 'villa' === $node->level ? null : Nodes::ancestor( $node, 'floor' );
		if ( $solo_estate_floor && '' !== $solo_estate_floor->number ) {
			$solo_estate_stats[] = array( Texts::get( 'floor' ), esc_html( $solo_estate_floor->number ) );
		}
		if ( '' !== $node->entrance ) {
			$solo_estate_stats[] = array( Texts::get( 'entrance' ), esc_html( $node->entrance ) );
		}
		if ( null !== $node->rooms && 'commercial' !== $node->level ) {
			$solo_estate_stats[] = array( Texts::get( 'rooms' ), esc_html( 0 === $node->rooms ? Texts::get( 'studio' ) : $node->rooms ) );
		}
		// Area: the total area, else the living area, else the first highlighted section
		// (imported projects often keep their areas only in the specification).
		$solo_estate_area = Renderer::area( $node->area );
		$solo_estate_area_label = Texts::get( 'area' );
		if ( '' === $solo_estate_area && '' !== Renderer::area( $node->area_living ) ) {
			$solo_estate_area       = Renderer::area( $node->area_living );
			$solo_estate_area_label = Texts::get( 'living_area' );
		}
		if ( '' === $solo_estate_area ) {
			$solo_estate_values = \SoloEstate\Specs::values( $node->id );
			foreach ( \SoloEstate\Specs::fields() as $solo_estate_field ) {
				$solo_estate_raw = isset( $solo_estate_values[ $solo_estate_field->id ] ) ? str_replace( ',', '.', $solo_estate_values[ $solo_estate_field->id ] ) : '';
				if ( $solo_estate_field->highlight && is_numeric( $solo_estate_raw ) && (float) $solo_estate_raw > 0 ) {
					$solo_estate_area       = Renderer::number( (float) $solo_estate_raw ) . ' ' . \SoloEstate\Specs::unit( $solo_estate_field );
					$solo_estate_area_label = \SoloEstate\Specs::title( $solo_estate_field );
					break;
				}
			}
		}
		if ( '' !== $solo_estate_area ) {
			$solo_estate_stats[] = array( $solo_estate_area_label, esc_html( $solo_estate_area ) );
		}
		$solo_estate_price = $target['href'] ? Renderer::price( Nodes::total_price( $node ) ) : '';
		if ( '' !== $solo_estate_price ) {
			$solo_estate_stats[] = array( Texts::get( 'price' ), $solo_estate_price );
		}
		if ( Settings::get( 'tooltip_image' ) && $node->image_id ) {
			$solo_estate_thumb = wp_get_attachment_image(
				$node->image_id,
				'medium',
				false,
				array(
					'alt'     => '',
					'loading' => 'lazy',
				)
			);
		}
		break;

	case 'floor':
	case 'parking':
	case 'building':
	case 'phase':
		$solo_estate_total = Nodes::flat_count( $node );
		$solo_estate_parts = Renderer::count_parts( $node, false );
		if ( ! $solo_estate_total ) {
			$solo_estate_meter = null;
		} elseif ( in_array( $node->level, array( 'building', 'phase' ), true ) ) {
			// Buildings and phases: how many are sold, then available and reserved as tiles.
			$solo_estate_meter = array( Nodes::stats( $node )['sold'], Texts::get( 'sold_label' ), $solo_estate_total, false );
			foreach ( $solo_estate_parts as $solo_estate_part ) {
				$solo_estate_stats[] = array( $solo_estate_part[0], esc_html( $solo_estate_part[1] ) );
			}
		} elseif ( $solo_estate_parts ) {
			// Floors and parkings: how many are available, then reserved (or rented…) as tiles.
			$solo_estate_meter = array( Nodes::stats( $node )['available'], Texts::get( 'available_label' ), $solo_estate_total, false );
			foreach ( $solo_estate_parts as $solo_estate_part ) {
				if ( 'available' !== $solo_estate_part[2] ) {
					$solo_estate_stats[] = array( $solo_estate_part[0], esc_html( $solo_estate_part[1] ) );
				}
			}
		}
		if ( in_array( $node->level, array( 'building', 'phase' ), true ) ) {
			$solo_estate_completion = Nodes::field( $node, 'completion' );
			if ( '' !== $solo_estate_completion ) {
				$solo_estate_stats[] = array( Texts::get( 'completion' ), esc_html( $solo_estate_completion ) );
			}
		}
		break;
}
?>
<div class="solo-estate-tip__head">
	<span class="solo-estate-tip__title"><?php echo esc_html( Nodes::display_title( $node ) ); ?></span>
	<?php if ( '' !== $solo_estate_state['label'] ) : ?>
		<span class="solo-estate-tip__status" style="--solo-estate-shape:<?php echo esc_attr( $solo_estate_state['color'] ); ?>"><?php echo esc_html( $solo_estate_state['label'] ); ?></span>
	<?php endif; ?>
</div>

<?php if ( $solo_estate_thumb ) : ?>
	<div class="solo-estate-tip__thumb"><?php echo $solo_estate_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<?php endif; ?>

<?php if ( $solo_estate_meter ) : ?>
	<?php $solo_estate_pct = (int) floor( 100 * $solo_estate_meter[0] / max( 1, $solo_estate_meter[2] ) ); ?>
	<div class="solo-estate-tip__meter">
		<span class="solo-estate-tip__big"><?php echo (int) $solo_estate_meter[0]; ?></span>
		<span class="solo-estate-tip__meter-label"><?php echo esc_html( $solo_estate_meter[1] ); ?> <small>/ <?php echo (int) $solo_estate_meter[2]; ?></small></span>
		<?php if ( $solo_estate_meter[3] ) : ?>
			<span class="solo-estate-tip__pct"><?php echo (int) $solo_estate_pct; ?>%</span>
		<?php endif; ?>
		<span class="solo-estate-tip__bar"><span style="width:<?php echo (int) $solo_estate_pct; ?>%"></span></span>
	</div>
<?php endif; ?>

<?php if ( $solo_estate_stats ) : ?>
	<dl class="solo-estate-tip__stats">
		<?php foreach ( $solo_estate_stats as $solo_estate_stat ) : ?>
			<div><dt><?php echo esc_html( $solo_estate_stat[0] ); ?></dt><dd><?php echo $solo_estate_stat[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></dd></div>
		<?php endforeach; ?>
	</dl>
<?php endif; ?>

<?php if ( $target['href'] ) : ?>
	<span class="solo-estate-tip__hint"><?php echo esc_html( Texts::get( 'click_hint' ) ); ?></span>
	<a class="solo-estate-tip__more" href="<?php echo esc_url( $target['href'] ); ?>"<?php echo $target['external'] ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( Texts::get( 'details' ) ); ?> →</a>
<?php endif; ?>
