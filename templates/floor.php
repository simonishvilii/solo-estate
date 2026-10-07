<?php
/**
 * Floor level: plan with apartment and commercial space polygons, floor switcher, table.
 *
 * Override by copying to yourtheme/solo-estate/floor.php.
 *
 * @package SoloEstate
 *
 * @var object   $project Project.
 * @var object   $node    Floor.
 * @var object[] $chain   [project, …, floor].
 */

use SoloEstate\Frontend\Renderer;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Statuses;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

$solo_estate_children = Nodes::children( $node->id );
$solo_estate_floors   = $node->parent_id ? Nodes::children( $node->parent_id, 'floor' ) : array();
usort(
	$solo_estate_floors,
	static function ( $a, $b ) {
		return strnatcmp( (string) $b->number, (string) $a->number );
	}
);
$solo_estate_units = $solo_estate_children;
// Hide the price column when no unit on this floor has a price.
$solo_estate_prices = Settings::get( 'show_prices' ) && array_filter(
	$solo_estate_units,
	static function ( $unit ) {
		return null !== Nodes::total_price( $unit );
	}
);
usort(
	$solo_estate_units,
	static function ( $a, $b ) {
		return strnatcmp( (string) $a->number, (string) $b->number );
	}
);
?>
<?php $solo_estate_crumbs = Renderer::breadcrumbs( $chain ); ?>
<?php // The breadcrumbs end with this item's name: the heading is kept for screen readers only, the counts sit on their right. ?>
<div class="solo-estate-topbar">
<?php echo $solo_estate_crumbs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<header class="solo-estate-head">
	<h2 class="solo-estate-title<?php echo '' !== $solo_estate_crumbs ? ' screen-reader-text' : ''; ?>"><?php echo esc_html( Nodes::display_title( $node ) ); ?></h2>
	<?php $solo_estate_parts = Renderer::count_parts( $node ); ?>
	<?php // The status badge when there are no counts, or when nothing is available any more. ?>
	<?php if ( ! $solo_estate_parts || 'available' !== $solo_estate_parts[0][2] ) : ?>
		<?php echo Renderer::node_badge( $node ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
	<?php foreach ( $solo_estate_parts as $solo_estate_part ) : ?>
		<span class="solo-estate-head__meta"><?php echo esc_html( $solo_estate_part[0] . ': ' . $solo_estate_part[1] ); ?></span>
	<?php endforeach; ?>
	<?php if ( count( $solo_estate_floors ) > 1 ) : ?>
		<label class="solo-estate-floor-switch">
			<span class="screen-reader-text"><?php echo esc_html( Texts::get( 'select_floor' ) ); ?></span>
			<select data-solo-estate-nav>
				<?php foreach ( $solo_estate_floors as $solo_estate_floor ) : ?>
					<?php $solo_estate_target = Renderer::target( $solo_estate_floor ); ?>
					<option value="<?php echo esc_url( $solo_estate_target['href'] ); ?>"<?php selected( $solo_estate_floor->id, $node->id ); ?><?php disabled( '' === $solo_estate_target['href'] && $solo_estate_floor->id !== $node->id ); ?>>
						<?php echo esc_html( Texts::get( 'floor' ) . ' ' . ( '' !== $solo_estate_floor->number ? $solo_estate_floor->number : Nodes::display_title( $solo_estate_floor ) ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
	<?php endif; ?>
	<?php if ( $solo_estate_prices ) : ?>
		<?php echo Renderer::currency_switch(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
</header>
</div>

<?php $solo_estate_stage = Renderer::stage( $node, $solo_estate_children ); ?>
<?php $solo_estate_list = ( Settings::get( 'show_lists' ) || '' === $solo_estate_stage ) && $solo_estate_units; ?>
<?php if ( $solo_estate_list ) : ?>
	<h3 class="solo-estate-section-title"><?php echo esc_html( Texts::get( 'select_flat' ) ); ?></h3>
<?php endif; ?>
<?php // Plan on the left, apartment list on the right, tops level; pointing at a row shows its tooltip on the plan. ?>
<div class="solo-estate-layout solo-estate-layout--floor<?php echo '' === $solo_estate_stage || ! $solo_estate_list ? ' solo-estate-layout--list' : ''; ?>">
	<div class="solo-estate-layout__main">
		<?php echo $solo_estate_stage; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo Renderer::legend( $solo_estate_children ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
<?php if ( $solo_estate_list ) : ?>
	<aside class="solo-estate-layout__side">
	<div class="solo-estate-table-wrap">
		<table class="solo-estate-table">
			<thead>
				<tr>
					<th><?php echo esc_html( Texts::get( 'flat' ) ); ?></th>
					<th><?php echo esc_html( Texts::get( 'area' ) ); ?></th>
					<?php if ( $solo_estate_prices ) : ?>
						<th><?php echo esc_html( Texts::get( 'price' ) ); ?></th>
					<?php endif; ?>
					<th><?php echo esc_html( Texts::get( 'status' ) ); ?></th>
					<?php if ( '' === $solo_estate_stage ) : ?>
						<th></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $solo_estate_units as $solo_estate_unit ) : ?>
					<?php
					$solo_estate_status = Statuses::get( $solo_estate_unit->status_id );
					$solo_estate_target = Renderer::target( $solo_estate_unit );
					?>
					<tr data-solo-estate-for="<?php echo (int) $solo_estate_unit->id; ?>" class="<?php echo $solo_estate_target['href'] ? 'is-link' : 'is-disabled'; ?> solo-estate-row--<?php echo esc_attr( $solo_estate_unit->level ); ?>"<?php echo $solo_estate_target['href'] ? ' data-href="' . esc_url( $solo_estate_target['href'] ) . '"' : ''; ?>>
						<td><strong><?php echo esc_html( ( 'flat' === $solo_estate_unit->level && '' !== $solo_estate_unit->number ) ? $solo_estate_unit->number : Nodes::display_title( $solo_estate_unit ) ); ?></strong></td>
						<td><?php echo esc_html( Renderer::area( $solo_estate_unit->area ) ); ?></td>
						<?php if ( $solo_estate_prices ) : ?>
							<td><?php echo $solo_estate_target['href'] ? Renderer::price( Nodes::total_price( $solo_estate_unit ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<?php endif; ?>
						<td><?php echo Renderer::badge( $solo_estate_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<?php // Beside the plan the whole row opens the apartment; a full-width list keeps the link. ?>
						<?php if ( '' === $solo_estate_stage ) : ?>
							<td class="solo-estate-table__action">
								<?php if ( $solo_estate_target['href'] ) : ?>
									<a href="<?php echo esc_url( $solo_estate_target['href'] ); ?>"><?php echo esc_html( Texts::get( 'details' ) ); ?> →</a>
								<?php endif; ?>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	</aside>
<?php endif; ?>
</div>

<?php if ( Renderer::show_lead_form( 'floor' ) ) : ?>
	<?php echo Renderer::template( 'lead-form', array( 'node' => $node ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
