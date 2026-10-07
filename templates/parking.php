<?php
/**
 * Parking level: plan with parking spaces. Spaces have no page of their own; hovering
 * one shows its area and status.
 *
 * Override by copying to yourtheme/solo-estate/parking.php.
 *
 * @package SoloEstate
 *
 * @var object   $project Project.
 * @var object   $node    Parking.
 * @var object[] $chain   [project, …, parking].
 */

use SoloEstate\Frontend\Renderer;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Statuses;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

$solo_estate_spots  = Nodes::sorted_children( $node->id );
$solo_estate_prices = Settings::get( 'show_prices' ) && array_filter(
	$solo_estate_spots,
	static function ( $spot ) {
		return null !== Nodes::total_price( $spot ) && Statuses::is_clickable( Statuses::get( $spot->status_id ) );
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
	<?php if ( $solo_estate_prices ) : ?>
		<?php echo Renderer::currency_switch(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
</header>
</div>

<?php echo Renderer::stage( $node, $solo_estate_spots ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php echo Renderer::legend( $solo_estate_spots ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<?php if ( Settings::get( 'show_lists' ) && $solo_estate_spots ) : ?>
	<div class="solo-estate-table-wrap">
		<table class="solo-estate-table">
			<thead>
				<tr>
					<th><?php echo esc_html( Texts::get( 'spot' ) ); ?></th>
					<th><?php echo esc_html( Texts::get( 'area' ) ); ?></th>
					<?php if ( $solo_estate_prices ) : ?>
						<th><?php echo esc_html( Texts::get( 'price' ) ); ?></th>
					<?php endif; ?>
					<th><?php echo esc_html( Texts::get( 'status' ) ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $solo_estate_spots as $solo_estate_spot ) : ?>
					<?php $solo_estate_status = Statuses::get( $solo_estate_spot->status_id ); ?>
					<tr data-solo-estate-for="<?php echo (int) $solo_estate_spot->id; ?>">
						<td><strong><?php echo esc_html( '' !== $solo_estate_spot->number ? $solo_estate_spot->number : Nodes::display_title( $solo_estate_spot ) ); ?></strong></td>
						<td><?php echo esc_html( Renderer::area( $solo_estate_spot->area ) ); ?></td>
						<?php if ( $solo_estate_prices ) : ?>
							<td><?php echo Statuses::is_clickable( $solo_estate_status ) ? Renderer::price( Nodes::total_price( $solo_estate_spot ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<?php endif; ?>
						<td><?php echo Renderer::badge( $solo_estate_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<?php $solo_estate_description = Nodes::field( $node, 'description' ); ?>
<?php if ( '' !== $solo_estate_description ) : ?>
	<div class="solo-estate-description"><?php echo do_shortcode( wpautop( $solo_estate_description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<?php endif; ?>

<?php if ( Renderer::show_lead_form( 'parking' ) ) : ?>
	<?php echo Renderer::template( 'lead-form', array( 'node' => $node ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
