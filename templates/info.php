<?php
/**
 * Info window of an item without a page of its own (boulevard, sports field, park…):
 * photos and description. Printed inside a <template> and opened by JS on click.
 *
 * Override by copying to yourtheme/solo-estate/info.php.
 *
 * @package SoloEstate
 *
 * @var object $node Item.
 */

use SoloEstate\Nodes;

defined( 'ABSPATH' ) || exit;

// Photos; without any, the item's own image (e.g. an imported render).
$solo_estate_photos = Nodes::gallery( $node );
if ( ! $solo_estate_photos ) {
	$solo_estate_photos = array_values( array_filter( array( $node->image_id, $node->image2_id ) ) );
}
$solo_estate_group       = 'info-' . (int) $node->id;
$solo_estate_completion  = Nodes::field( $node, 'completion' );
$solo_estate_description = Nodes::field( $node, 'description' );
?>
<div class="solo-estate-info__body">
	<h3 class="solo-estate-info__title"><?php echo esc_html( Nodes::display_title( $node ) ); ?></h3>
	<?php if ( '' !== $solo_estate_completion ) : ?>
		<p class="solo-estate-info__meta"><?php echo esc_html( $solo_estate_completion ); ?></p>
	<?php endif; ?>

	<?php if ( $solo_estate_photos ) : ?>
		<div class="solo-estate-info__photos<?php echo count( $solo_estate_photos ) > 1 ? ' has-thumbs' : ''; ?>">
			<?php foreach ( $solo_estate_photos as $solo_estate_index => $solo_estate_photo ) : ?>
				<?php $solo_estate_full = wp_get_attachment_image_url( $solo_estate_photo, 'full' ); ?>
				<?php if ( $solo_estate_full ) : ?>
					<a class="solo-estate-info__photo" href="<?php echo esc_url( $solo_estate_full ); ?>" data-solo-estate-lightbox="<?php echo esc_attr( $solo_estate_group ); ?>">
						<?php echo wp_get_attachment_image( $solo_estate_photo, 0 === $solo_estate_index ? 'large' : 'medium', false, array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $solo_estate_description ) : ?>
		<?php // Sanitized on save according to the editor's capabilities, like post content. ?>
		<div class="solo-estate-info__text"><?php echo do_shortcode( wpautop( $solo_estate_description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php endif; ?>
</div>
