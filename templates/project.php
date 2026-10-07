<?php
/**
 * Project and phase level: masterplan with buildings, villas and parkings, plus cards.
 * Without a masterplan the cards alone are shown.
 *
 * Override by copying to yourtheme/solo-estate/project.php.
 *
 * @package SoloEstate
 *
 * @var object   $project    Project.
 * @var object   $node       Project or phase.
 * @var object[] $chain      [project] or [project, phase].
 * @var bool     $show_title Whether to print the project name.
 */

use SoloEstate\Frontend\Renderer;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

$solo_estate_children = Nodes::children( $node->id );
$solo_estate_stage    = Renderer::stage( $node, $solo_estate_children );
?>
<?php $solo_estate_crumbs = Renderer::breadcrumbs( $chain ); ?>
<div class="solo-estate-topbar">
<?php echo $solo_estate_crumbs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php if ( ! isset( $show_title ) || $show_title || Renderer::is_catalog() || 'project' !== $node->level ) : ?>
	<?php // The breadcrumbs end with the project's name: the heading stays for screen readers only. ?>
	<header class="solo-estate-head">
		<h2 class="solo-estate-title<?php echo '' !== $solo_estate_crumbs ? ' screen-reader-text' : ''; ?>"><?php echo esc_html( Nodes::display_title( $node ) ); ?></h2>
		<?php $solo_estate_completion = Nodes::field( $node, 'completion' ); ?>
		<?php if ( '' !== $solo_estate_completion ) : ?>
			<span class="solo-estate-head__meta"><?php echo esc_html( $solo_estate_completion ); ?></span>
		<?php endif; ?>
	</header>
<?php endif; ?>
</div>

<?php
// No apartment search on a completed project or when nothing is available any more.
$solo_estate_project_node = $project ? $project : Nodes::get( $node->project_id );
?>
<?php if ( \SoloEstate\Frontend\Search::shown_on( $solo_estate_project_node, $node ) ) : ?>
	<?php echo \SoloEstate\Frontend\Search::form( $solo_estate_project_node, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>

<?php echo $solo_estate_stage; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<?php // Cards repeat what the masterplan shows: only without one, or when enabled in Settings. ?>
<?php if ( ( '' === $solo_estate_stage || ( Settings::get( 'show_lists' ) && Settings::get( 'project_cards' ) ) ) && $solo_estate_children ) : ?>
	<ul class="solo-estate-cards">
		<?php foreach ( $solo_estate_children as $solo_estate_child ) : ?>
			<?php
			$solo_estate_target = Renderer::target( $solo_estate_child );
			$solo_estate_tag    = $solo_estate_target['href'] ? 'a' : 'div';
			$solo_estate_image  = 'villa' === $solo_estate_child->level ? ( $solo_estate_child->image2_id ? $solo_estate_child->image2_id : ( Nodes::gallery( $solo_estate_child ) ? Nodes::gallery( $solo_estate_child )[0] : $solo_estate_child->image_id ) ) : 0;
			?>
			<li data-solo-estate-for="<?php echo (int) $solo_estate_child->id; ?>">
				<<?php echo esc_attr( $solo_estate_tag ); ?> class="solo-estate-card solo-estate-card--<?php echo esc_attr( $solo_estate_child->level ); ?>"<?php echo $solo_estate_target['href'] ? ' href="' . esc_url( $solo_estate_target['href'] ) . '"' . ( $solo_estate_target['external'] ? ' target="_blank" rel="noopener"' : '' ) : ''; ?>>
					<?php if ( $solo_estate_image ) : ?>
						<span class="solo-estate-card__media"><?php echo wp_get_attachment_image( $solo_estate_image, 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy' ) ); ?></span>
					<?php endif; ?>
					<span class="solo-estate-card__title"><?php echo esc_html( Nodes::display_title( $solo_estate_child ) ); ?></span>
					<?php echo Renderer::node_badge( $solo_estate_child ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( Nodes::is_unit( $solo_estate_child->level ) ) : ?>
						<?php $solo_estate_area = Renderer::area( $solo_estate_child->area ); ?>
						<?php if ( '' !== $solo_estate_area ) : ?>
							<span class="solo-estate-card__meta"><?php echo esc_html( $solo_estate_area ); ?></span>
						<?php endif; ?>
						<?php if ( $solo_estate_target['href'] ) : ?>
							<span class="solo-estate-card__meta"><?php echo Renderer::price( Nodes::total_price( $solo_estate_child ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<?php endif; ?>
					<?php else : ?>
						<?php $solo_estate_completion = Nodes::field( $solo_estate_child, 'completion' ); ?>
						<?php if ( '' !== $solo_estate_completion ) : ?>
							<span class="solo-estate-card__meta"><?php echo esc_html( $solo_estate_completion ); ?></span>
						<?php endif; ?>
						<?php foreach ( Renderer::count_parts( $solo_estate_child ) as $solo_estate_part ) : ?>
							<span class="solo-estate-card__meta"><?php echo esc_html( $solo_estate_part[0] . ': ' . $solo_estate_part[1] ); ?></span>
						<?php endforeach; ?>
					<?php endif; ?>
				</<?php echo esc_attr( $solo_estate_tag ); ?>>
			</li>
		<?php endforeach; ?>
	</ul>
<?php elseif ( ! $solo_estate_children ) : ?>
	<p class="solo-estate-empty"><?php echo esc_html( Texts::get( 'no_items' ) ); ?></p>
<?php endif; ?>

<?php $solo_estate_description = Nodes::field( $node, 'description' ); ?>
<?php if ( '' !== $solo_estate_description ) : ?>
	<?php // Sanitized on save according to the editor's capabilities, like post content (keeps map iframes). ?>
	<div class="solo-estate-description"><?php echo do_shortcode( wpautop( $solo_estate_description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<?php endif; ?>

<?php if ( Renderer::show_lead_form( $node->level ) ) : ?>
	<?php echo Renderer::template( 'lead-form', array( 'node' => $node ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
