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

use SoloEstate\I18n;
use SoloEstate\Nodes;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

$solo_estate_uid = wp_unique_id( 'solo-estate-lead-' );
// Result of a submission without JavaScript (the visitor came back from admin-post.php).
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$solo_estate_result = isset( $_GET['solo_estate_lead'] ) ? sanitize_key( wp_unslash( $_GET['solo_estate_lead'] ) ) : '';
// This page without tracking args (the form may be served from a page cache to everyone).
// What analytics events say about the request (frontend.js): the item, its project and, where
// prices are shown, its price.
$solo_estate_item = array();
if ( $node ) {
	$solo_estate_project = Nodes::get( 'project' === $node->level ? $node->id : $node->project_id );
	$solo_estate_item    = array(
		'data-item-id'       => $node->id,
		'data-item-name'     => Nodes::display_title( $node ),
		'data-item-category' => $solo_estate_project ? Nodes::display_title( $solo_estate_project ) : '',
	);
	$solo_estate_price = Nodes::is_unit( $node->level ) && \SoloEstate\Settings::get( 'show_prices' ) && \SoloEstate\Statuses::is_clickable( \SoloEstate\Statuses::of( $node->status_id ) ) ? Nodes::total_price( $node ) : null;
	if ( $solo_estate_price ) {
		$solo_estate_item['data-value']    = round( (float) $solo_estate_price, 2 );
		$solo_estate_item['data-currency'] = strtoupper( (string) \SoloEstate\Settings::get( 'base_currency' ) );
	}
}
$solo_estate_here   = isset( $_SERVER['HTTP_HOST'] ) ? set_url_scheme( 'http://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . ( $node && 'project' !== $node->level ? add_query_arg( \SoloEstate\Frontend\Renderer::QUERY_ARG, (int) $node->id, \SoloEstate\Frontend\Renderer::base_url() ) : \SoloEstate\Frontend\Renderer::base_url() ) ) : home_url( '/' );
?>
<?php // JavaScript sends the form in the background; without it, it is posted normally (never GET: the name and phone must not end up in URLs). ?>
<form class="solo-estate-lead" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-solo-estate-lead<?php foreach ( $solo_estate_item as $solo_estate_attr => $solo_estate_value ) { echo ' ' . esc_attr( $solo_estate_attr ) . '="' . esc_attr( $solo_estate_value ) . '"'; } ?>>
	<div class="solo-estate-lead__intro">
		<div class="solo-estate-lead__title"><?php echo esc_html( Texts::get( 'lead_title' ) ); ?></div>
		<?php if ( $node ) : ?>
			<div class="solo-estate-lead__subject"><?php echo esc_html( Nodes::display_title( $node ) ); ?></div>
		<?php endif; ?>
		<p class="solo-estate-lead__note"><?php echo esc_html( Texts::get( 'lead_note' ) ); ?></p>
	</div>
	<div class="solo-estate-lead__fields">
		<?php // Each field names its own error (aria-describedby), shown in words, not only in red. ?>
		<label for="<?php echo esc_attr( $solo_estate_uid ); ?>-name">
			<span><?php echo esc_html( Texts::get( 'lead_name' ) ); ?></span>
			<input type="text" id="<?php echo esc_attr( $solo_estate_uid ); ?>-name" name="name" autocomplete="name" required maxlength="100" aria-describedby="<?php echo esc_attr( $solo_estate_uid ); ?>-name-error">
			<em class="solo-estate-lead__error" id="<?php echo esc_attr( $solo_estate_uid ); ?>-name-error" hidden><?php echo esc_html( Texts::get( 'lead_name_error' ) ); ?></em>
		</label>
		<label for="<?php echo esc_attr( $solo_estate_uid ); ?>-phone">
			<span><?php echo esc_html( Texts::get( 'lead_phone' ) ); ?></span>
			<input type="tel" id="<?php echo esc_attr( $solo_estate_uid ); ?>-phone" name="phone" autocomplete="tel" required maxlength="30" inputmode="tel" aria-describedby="<?php echo esc_attr( $solo_estate_uid ); ?>-phone-error">
			<em class="solo-estate-lead__error" id="<?php echo esc_attr( $solo_estate_uid ); ?>-phone-error" hidden><?php echo esc_html( Texts::get( 'lead_phone_error' ) ); ?></em>
		</label>
		<?php // Honeypot: real visitors never see or fill this. ?>
		<label class="solo-estate-hp" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
		<input type="hidden" name="node_id" value="<?php echo esc_attr( $node ? $node->id : 0 ); ?>">
		<input type="hidden" name="ts" value="<?php echo esc_attr( time() ); ?>">
		<input type="hidden" name="action" value="solo_estate_lead_post">
		<input type="hidden" name="lang" value="<?php echo esc_attr( I18n::current() ); ?>">
		<input type="hidden" name="page_url" value="<?php echo esc_url( $solo_estate_here ); ?>">
		<button type="submit" class="solo-estate-button"><?php echo esc_html( Texts::get( 'lead_submit' ) ); ?></button>
	</div>
	<?php if ( 'sent' === $solo_estate_result || 'error' === $solo_estate_result ) : ?>
		<div class="solo-estate-lead__message<?php echo 'error' === $solo_estate_result ? ' is-error' : ''; ?>" id="solo-estate-lead-result" role="status" aria-live="polite"><?php echo esc_html( Texts::get( 'sent' === $solo_estate_result ? 'lead_success' : 'lead_error' ) ); ?></div>
	<?php else : ?>
		<?php // Always in the page (empty), so screen readers announce what is written into it. ?>
		<div class="solo-estate-lead__message" role="status" aria-live="polite" tabindex="-1"></div>
	<?php endif; ?>
</form>
