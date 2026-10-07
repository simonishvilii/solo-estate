<?php
/**
 * Building level: render with polygons of floors and parkings, and a list beside it.
 *
 * Override by copying to yourtheme/solo-estate/building.php.
 *
 * @package SoloEstate
 *
 * @var object   $project Project.
 * @var object   $node    Building.
 * @var object[] $chain   [project, …, building].
 */

use SoloEstate\Frontend\Renderer;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

$solo_estate_children = Nodes::children( $node->id );

// Floors read top-down like the building itself; parkings follow in number order.
$solo_estate_sorted = $solo_estate_children;
usort(
	$solo_estate_sorted,
	static function ( $a, $b ) {
		$rank = array(
			'floor'   => 0,
			'parking' => 1,
		);
		if ( $a->level !== $b->level ) {
			return $rank[ $a->level ] - $rank[ $b->level ];
		}
		return 'floor' === $a->level ? strnatcmp( (string) $b->number, (string) $a->number ) : strnatcmp( (string) $a->number, (string) $b->number );
	}
);
$solo_estate_only_floors = ! array_filter(
	$solo_estate_children,
	static function ( $child ) {
		return 'floor' !== $child->level;
	}
);
$solo_estate_stage       = Renderer::stage( $node, $solo_estate_children );
?>
<?php $solo_estate_crumbs = Renderer::breadcrumbs( $chain ); ?>
<?php // The breadcrumbs end with this item's name: the heading is kept for screen readers only, the counts sit on their right. ?>
<div class="solo-estate-topbar">
<?php echo $solo_estate_crumbs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<header class="solo-estate-head">
	<h2 class="solo-estate-title<?php echo '' !== $solo_estate_crumbs ? ' screen-reader-text' : ''; ?>"><?php echo esc_html( Nodes::display_title( $node ) ); ?></h2>
	<?php $solo_estate_completion = Nodes::field( $node, 'completion' ); ?>
	<?php if ( '' !== $solo_estate_completion ) : ?>
		<span class="solo-estate-head__meta"><?php echo esc_html( $solo_estate_completion ); ?></span>
	<?php endif; ?>
	<?php $solo_estate_parts = Renderer::count_parts( $node ); ?>
	<?php // The status badge when there are no counts, or when nothing is available any more. ?>
	<?php if ( ! $solo_estate_parts || 'available' !== $solo_estate_parts[0][2] ) : ?>
		<?php echo Renderer::node_badge( $node ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
	<?php foreach ( $solo_estate_parts as $solo_estate_part ) : ?>
		<span class="solo-estate-head__meta"><?php echo esc_html( $solo_estate_part[0] . ': ' . $solo_estate_part[1] ); ?></span>
	<?php endforeach; ?>
</header>
</div>

<?php $solo_estate_project_node = $project ? $project : Nodes::get( $node->project_id ); ?>
<?php if ( \SoloEstate\Frontend\Search::shown_on( $solo_estate_project_node, $node ) ) : ?>
	<?php echo \SoloEstate\Frontend\Search::form( $solo_estate_project_node, true, array( 'building' => (int) $node->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>

<?php $solo_estate_list = ( Settings::get( 'show_lists' ) || '' === $solo_estate_stage ) && $solo_estate_sorted; ?>
<?php if ( $solo_estate_list ) : ?>
	<h3 class="solo-estate-section-title"><?php echo esc_html( Texts::get( $solo_estate_only_floors ? 'select_floor' : 'select_item' ) ); ?></h3>
<?php endif; ?>
<div class="solo-estate-layout<?php echo '' === $solo_estate_stage ? ' solo-estate-layout--list' : ''; ?>">
	<div class="solo-estate-layout__main">
		<?php echo $solo_estate_stage; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php if ( $solo_estate_list ) : ?>
		<aside class="solo-estate-layout__side">
			<ul class="solo-estate-floor-list">
				<?php foreach ( $solo_estate_sorted as $solo_estate_item ) : ?>
					<?php $solo_estate_target = Renderer::target( $solo_estate_item ); ?>
					<li data-solo-estate-for="<?php echo (int) $solo_estate_item->id; ?>">
						<?php if ( $solo_estate_target['href'] ) : ?>
							<a href="<?php echo esc_url( $solo_estate_target['href'] ); ?>">
						<?php else : ?>
							<span class="is-disabled">
						<?php endif; ?>
							<strong><?php echo esc_html( ( 'floor' === $solo_estate_item->level && $solo_estate_only_floors && '' !== $solo_estate_item->number ) ? $solo_estate_item->number : Nodes::display_title( $solo_estate_item ) ); ?></strong>
							<?php
							// Available and reserved (or other) counts; sold ones are implied.
							$solo_estate_parts = Renderer::count_parts( $solo_estate_item, false );
							$solo_estate_line  = implode(
								' · ',
								array_map(
									static function ( $part ) {
										return $part[0] . ': ' . $part[1];
									},
									$solo_estate_parts
								)
							);
							?>
							<?php if ( $solo_estate_parts && 'available' !== $solo_estate_parts[0][2] ) : ?>
								<?php // Nothing available: the "Sold" badge, then the reserved count. ?>
								<span class="solo-estate-floor-list__counts"><?php echo Renderer::node_badge( $solo_estate_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo esc_html( $solo_estate_line ); ?></span></span>
							<?php elseif ( '' !== $solo_estate_line ) : ?>
								<span><?php echo esc_html( $solo_estate_line ); ?></span>
							<?php else : ?>
								<?php echo Renderer::node_badge( $solo_estate_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						<?php echo $solo_estate_target['href'] ? '</a>' : '</span>'; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</aside>
	<?php endif; ?>
</div>

<?php $solo_estate_description = Nodes::field( $node, 'description' ); ?>
<?php if ( '' !== $solo_estate_description ) : ?>
	<div class="solo-estate-description"><?php echo do_shortcode( wpautop( $solo_estate_description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<?php endif; ?>

<?php if ( Renderer::show_lead_form( $node->level ) ) : ?>
	<?php echo Renderer::template( 'lead-form', array( 'node' => $node ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
