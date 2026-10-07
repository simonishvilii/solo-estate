<?php
/**
 * Lead ("request a call") form.
 *
 * Override by copying to yourtheme/solo-estate/lead-form.php. Keep the field names.
 *
 * @package SoloEstate
 *
 * @var object|null $node Node the request is about.
 */

use SoloEstate\Nodes;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

$solo_estate_uid = wp_unique_id( 'solo-estate-lead-' );
?>
<form class="solo-estate-lead" data-solo-estate-lead novalidate>
	<div class="solo-estate-lead__intro">
		<div class="solo-estate-lead__title"><?php echo esc_html( Texts::get( 'lead_title' ) ); ?></div>
		<?php if ( $node ) : ?>
			<div class="solo-estate-lead__subject"><?php echo esc_html( Nodes::display_title( $node ) ); ?></div>
		<?php endif; ?>
		<p class="solo-estate-lead__note"><?php echo esc_html( Texts::get( 'lead_note' ) ); ?></p>
	</div>
	<div class="solo-estate-lead__fields">
		<label for="<?php echo esc_attr( $solo_estate_uid ); ?>-name">
			<span><?php echo esc_html( Texts::get( 'lead_name' ) ); ?></span>
			<input type="text" id="<?php echo esc_attr( $solo_estate_uid ); ?>-name" name="name" autocomplete="name" required maxlength="100">
		</label>
		<label for="<?php echo esc_attr( $solo_estate_uid ); ?>-phone">
			<span><?php echo esc_html( Texts::get( 'lead_phone' ) ); ?></span>
			<input type="tel" id="<?php echo esc_attr( $solo_estate_uid ); ?>-phone" name="phone" autocomplete="tel" required maxlength="30" inputmode="tel">
		</label>
		<?php // Honeypot: real visitors never see or fill this. ?>
		<label class="solo-estate-hp" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
		<input type="hidden" name="node_id" value="<?php echo esc_attr( $node ? $node->id : 0 ); ?>">
		<input type="hidden" name="ts" value="<?php echo esc_attr( time() ); ?>">
		<button type="submit" class="solo-estate-button"><?php echo esc_html( Texts::get( 'lead_submit' ) ); ?></button>
	</div>
	<div class="solo-estate-lead__message" role="status" aria-live="polite" hidden></div>
</form>
