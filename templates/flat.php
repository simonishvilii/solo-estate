<?php
/**
 * Unit page — apartment, commercial space or villa.
 *
 * Left: plans and photos in a card with tabs. Right: a sticky panel with the key numbers,
 * price, "request a call" / virtual tour buttons and the details. Below: rooms and sections,
 * description, villa floors, neighbours and the lead form.
 *
 * Override by copying to yourtheme/solo-estate/flat.php.
 *
 * @package SoloEstate
 *
 * @var object   $project Project.
 * @var object   $node    Unit.
 * @var object[] $chain   [project, …, unit].
 */

use SoloEstate\Frontend\Renderer;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Specs;
use SoloEstate\Statuses;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

$solo_estate_status = Statuses::get( $node->status_id );
$solo_estate_open   = Renderer::is_open( $node );
$solo_estate_total  = Nodes::total_price( $node );
$solo_estate_title  = Nodes::display_title( $node );
$solo_estate_tour   = Renderer::tour_url( $node );
$solo_estate_lead   = Renderer::show_lead_form( $node->level ) && $solo_estate_open;
$solo_estate_lead_id = 'solo-estate-request-' . $node->id;

// Where the unit is: building, floor (and the phase when there is one).
$solo_estate_by = array();
foreach ( $chain as $solo_estate_item ) {
	$solo_estate_by[ $solo_estate_item->level ] = $solo_estate_item;
}
$solo_estate_where = array();
foreach ( array( 'phase', 'building', 'floor' ) as $solo_estate_level ) {
	if ( isset( $solo_estate_by[ $solo_estate_level ] ) ) {
		$solo_estate_where[] = Nodes::display_title( $solo_estate_by[ $solo_estate_level ] );
	}
}

// Key numbers shown large at the top of the panel.
$solo_estate_keys = array();
if ( null !== $node->area && $node->area > 0 ) {
	$solo_estate_keys[] = array( 'area', Texts::get( 'total_area' ), Renderer::area( $node->area ) );
}
if ( null !== $node->rooms && in_array( $node->level, array( 'flat', 'villa' ), true ) ) {
	$solo_estate_keys[] = array( 'rooms', Texts::get( 'rooms' ), 0 === $node->rooms ? Texts::get( 'studio' ) : (string) $node->rooms );
}
if ( isset( $solo_estate_by['floor'] ) && '' !== $solo_estate_by['floor']->number ) {
	$solo_estate_keys[] = array( 'floor', Texts::get( 'floor' ), $solo_estate_by['floor']->number );
}
if ( '' !== $node->entrance ) {
	$solo_estate_keys[] = array( 'entrance', Texts::get( 'entrance' ), $node->entrance );
}

// Everything else goes to the details list (building and floor are already in the line above the title).
$solo_estate_details = array();
if ( isset( $solo_estate_by['building'] ) ) {
	$solo_estate_completion = Nodes::field( $solo_estate_by['building'], 'completion' );
	if ( '' !== $solo_estate_completion ) {
		$solo_estate_details[] = array( Texts::get( 'completion' ), $solo_estate_completion );
	}
}
foreach ( array( 'area_living' => 'living_area', 'area_summer' => 'summer_area' ) as $solo_estate_column => $solo_estate_key ) {
	$solo_estate_value = Renderer::area( $node->$solo_estate_column );
	if ( '' !== $solo_estate_value ) {
		$solo_estate_details[] = array( Texts::get( $solo_estate_key ), $solo_estate_value );
	}
}

// Media tabs: 2D plan, 3D plan, photos — only the ones that exist.
$solo_estate_media = array();
if ( $node->image_id ) {
	$solo_estate_media['plan2d'] = array( Texts::get( 'plan_2d' ), array( $node->image_id ) );
}
if ( $node->image2_id ) {
	$solo_estate_media['plan3d'] = array( Texts::get( 'plan_3d' ), array( $node->image2_id ) );
}
if ( Nodes::gallery( $node ) ) {
	$solo_estate_media['photos'] = array( Texts::get( 'gallery' ), Nodes::gallery( $node ) );
}

// Previous / next unit of the same kind in the same place.
$solo_estate_siblings = $node->parent_id ? Nodes::sorted_children( $node->parent_id, $node->level ) : array();
$solo_estate_prev     = null;
$solo_estate_next     = null;
foreach ( $solo_estate_siblings as $solo_estate_i => $solo_estate_sibling ) {
	if ( $solo_estate_sibling->id === $node->id ) {
		$solo_estate_prev = $solo_estate_i > 0 ? $solo_estate_siblings[ $solo_estate_i - 1 ] : null;
		$solo_estate_next = isset( $solo_estate_siblings[ $solo_estate_i + 1 ] ) ? $solo_estate_siblings[ $solo_estate_i + 1 ] : null;
		break;
	}
}
$solo_estate_villa_floors = 'villa' === $node->level ? Nodes::sorted_children( $node->id, 'villa_floor' ) : array();

/**
 * Rooms and sections of a node as tiles.
 *
 * @param object $item Node.
 * @return string
 */
$solo_estate_rooms = static function ( $item ) {
	$values = Specs::values( $item->id );
	if ( ! $values ) {
		return '';
	}
	$out = '';
	foreach ( Specs::fields() as $field ) {
		if ( ! isset( $values[ $field->id ] ) ) {
			continue;
		}
		$value = str_replace( ',', '.', $values[ $field->id ] );
		$value = is_numeric( $value ) ? Renderer::number( (float) $value ) . ' ' . Specs::unit( $field ) : $values[ $field->id ];
		$out  .= sprintf( '<li class="%1$s"><span>%2$s</span><strong>%3$s</strong></li>', $field->highlight ? 'is-highlight' : '', esc_html( Specs::title( $field ) ), esc_html( $value ) );
	}
	return '<ul class="solo-estate-rooms">' . $out . '</ul>';
};

/**
 * Image link that opens the lightbox (images of one group can be browsed with arrows).
 *
 * @param int    $id    Attachment id.
 * @param string $group Lightbox group.
 * @param string $size  Image size.
 * @param string $alt   Alt text.
 * @param string $class Extra class.
 * @return string
 */
$solo_estate_image = static function ( $id, $group, $size, $alt, $class = '' ) {
	$full = wp_get_attachment_image_url( $id, 'full' );
	if ( ! $full ) {
		return '';
	}
	return sprintf(
		'<a class="solo-estate-flat__image %4$s" href="%1$s" data-solo-estate-lightbox="%2$s">%3$s</a>',
		esc_url( $full ),
		esc_attr( $group ),
		wp_get_attachment_image( $id, $size, false, array( 'alt' => $alt, 'loading' => 'lazy' ) ),
		esc_attr( $class )
	);
};
?>
<?php echo Renderer::breadcrumbs( $chain ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<div class="solo-estate-flat solo-estate-flat--<?php echo esc_attr( $node->level ); ?>">
	<div class="solo-estate-flat__media">
		<div class="solo-estate-flat__stage solo-estate-switch" data-solo-estate-switch>
			<?php if ( count( $solo_estate_media ) > 1 ) : ?>
				<div class="solo-estate-switch__tabs" role="tablist">
					<?php $solo_estate_first = true; ?>
					<?php foreach ( $solo_estate_media as $solo_estate_key => $solo_estate_tab ) : ?>
						<button type="button" role="tab" class="<?php echo $solo_estate_first ? 'is-active' : ''; ?>" aria-selected="<?php echo $solo_estate_first ? 'true' : 'false'; ?>" data-switch-to="<?php echo esc_attr( $solo_estate_key ); ?>"><?php echo esc_html( $solo_estate_tab[0] ); ?></button>
						<?php $solo_estate_first = false; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! $solo_estate_media ) : ?>
				<div class="solo-estate-flat__noimage"><?php echo Renderer::icon( 'building' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>
			<?php $solo_estate_first = true; ?>
			<?php foreach ( $solo_estate_media as $solo_estate_key => $solo_estate_tab ) : ?>
				<div class="solo-estate-switch__pane solo-estate-media--<?php echo esc_attr( $solo_estate_key ); ?>" data-switch-pane="<?php echo esc_attr( $solo_estate_key ); ?>"<?php echo $solo_estate_first ? '' : ' hidden'; ?>>
					<?php if ( 'photos' === $solo_estate_key ) : ?>
						<div class="solo-estate-gallery">
							<?php foreach ( $solo_estate_tab[1] as $solo_estate_i => $solo_estate_id ) : ?>
								<?php echo $solo_estate_image( $solo_estate_id, 'unit-' . $node->id, 0 === $solo_estate_i ? 'large' : 'medium_large', $solo_estate_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<?php echo $solo_estate_image( $solo_estate_tab[1][0], 'plans-' . $node->id, 'large', $solo_estate_title . ' — ' . $solo_estate_tab[0], 'is-plan' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
				</div>
				<?php $solo_estate_first = false; ?>
			<?php endforeach; ?>
			<?php if ( $solo_estate_media ) : ?>
				<span class="solo-estate-flat__zoom" aria-hidden="true"><?php echo Renderer::icon( 'zoom' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php endif; ?>
		</div>
	</div>

	<aside class="solo-estate-flat__panel">
		<?php if ( $solo_estate_where ) : ?>
			<div class="solo-estate-flat__where"><?php echo esc_html( implode( ' · ', $solo_estate_where ) ); ?></div>
		<?php endif; ?>
		<header class="solo-estate-flat__head">
			<h2 class="solo-estate-title"><?php echo esc_html( $solo_estate_title ); ?></h2>
			<?php echo Renderer::badge( $solo_estate_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</header>

		<?php if ( $solo_estate_keys ) : ?>
			<ul class="solo-estate-keys">
				<?php foreach ( $solo_estate_keys as $solo_estate_key ) : ?>
					<li>
						<?php echo Renderer::icon( $solo_estate_key[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<strong><?php echo esc_html( $solo_estate_key[2] ); ?></strong>
						<span><?php echo esc_html( $solo_estate_key[1] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( null !== $solo_estate_total && Settings::get( 'show_prices' ) && $solo_estate_open ) : ?>
			<div class="solo-estate-price-box">
				<div class="solo-estate-price-box__label"><?php echo esc_html( Texts::get( 'price' ) ); ?> <?php echo Renderer::currency_switch(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="solo-estate-price-box__total"><?php echo Renderer::price( $solo_estate_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php if ( $node->price_sqm ) : ?>
					<div class="solo-estate-price-box__sqm"><?php echo esc_html( Texts::get( 'price_sqm' ) ); ?>: <?php echo Renderer::price( $node->price_sqm ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $solo_estate_lead || '' !== $solo_estate_tour ) : ?>
			<div class="solo-estate-flat__actions">
				<?php if ( $solo_estate_lead ) : ?>
					<a class="solo-estate-button" href="#<?php echo esc_attr( $solo_estate_lead_id ); ?>" data-solo-estate-scroll><?php echo Renderer::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( Texts::get( 'lead_title' ) ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $solo_estate_tour ) : ?>
					<button type="button" class="solo-estate-button solo-estate-button--ghost" data-solo-estate-tour="<?php echo esc_url( $solo_estate_tour ); ?>" data-title="<?php echo esc_attr( $solo_estate_title . ' — ' . Texts::get( 'virtual_tour' ) ); ?>"><?php echo Renderer::icon( 'tour' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( Texts::get( 'virtual_tour' ) ); ?></button>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $solo_estate_details ) : ?>
			<dl class="solo-estate-details">
				<?php foreach ( $solo_estate_details as $solo_estate_fact ) : ?>
					<div><dt><?php echo esc_html( $solo_estate_fact[0] ); ?></dt><dd><?php echo esc_html( $solo_estate_fact[1] ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
	</aside>
</div>

<?php $solo_estate_list = $solo_estate_rooms( $node ); ?>
<?php if ( '' !== $solo_estate_list ) : ?>
	<section class="solo-estate-section">
		<h3 class="solo-estate-section__title"><?php echo esc_html( Texts::get( 'specs' ) ); ?></h3>
		<?php echo $solo_estate_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built. ?>
	</section>
<?php endif; ?>

<?php $solo_estate_description = Nodes::field( $node, 'description' ); ?>
<?php if ( '' !== $solo_estate_description ) : ?>
	<section class="solo-estate-section solo-estate-description"><?php echo do_shortcode( wpautop( $solo_estate_description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
<?php endif; ?>

<?php if ( $solo_estate_villa_floors ) : ?>
	<section class="solo-estate-section solo-estate-vfloors solo-estate-switch" data-solo-estate-switch>
		<h3 class="solo-estate-section__title"><?php echo esc_html( Texts::get( 'floors' ) ); ?></h3>
		<?php if ( count( $solo_estate_villa_floors ) > 1 ) : ?>
			<div class="solo-estate-switch__tabs" role="tablist">
				<?php foreach ( $solo_estate_villa_floors as $solo_estate_i => $solo_estate_vfloor ) : ?>
					<button type="button" role="tab" class="<?php echo 0 === $solo_estate_i ? 'is-active' : ''; ?>" aria-selected="<?php echo 0 === $solo_estate_i ? 'true' : 'false'; ?>" data-switch-to="vf<?php echo (int) $solo_estate_vfloor->id; ?>"><?php echo esc_html( Nodes::display_title( $solo_estate_vfloor ) ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php foreach ( $solo_estate_villa_floors as $solo_estate_i => $solo_estate_vfloor ) : ?>
			<div class="solo-estate-switch__pane solo-estate-vfloor" data-switch-pane="vf<?php echo (int) $solo_estate_vfloor->id; ?>"<?php echo 0 === $solo_estate_i ? '' : ' hidden'; ?>>
				<div class="solo-estate-vfloor__media">
					<?php if ( $solo_estate_vfloor->image_id ) : ?>
						<?php echo $solo_estate_image( $solo_estate_vfloor->image_id, 'vf-' . $solo_estate_vfloor->id, 'large', Nodes::display_title( $solo_estate_vfloor ), 'is-plan' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
					<?php if ( Nodes::gallery( $solo_estate_vfloor ) ) : ?>
						<div class="solo-estate-gallery solo-estate-gallery--thumbs">
							<?php foreach ( Nodes::gallery( $solo_estate_vfloor ) as $solo_estate_id ) : ?>
								<?php echo $solo_estate_image( $solo_estate_id, 'vf-' . $solo_estate_vfloor->id, 'medium', Nodes::display_title( $solo_estate_vfloor ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="solo-estate-vfloor__info">
					<?php if ( count( $solo_estate_villa_floors ) < 2 ) : ?>
						<div class="solo-estate-side-title"><?php echo esc_html( Nodes::display_title( $solo_estate_vfloor ) ); ?></div>
					<?php endif; ?>
					<?php $solo_estate_areas = Renderer::areas( $solo_estate_vfloor ); ?>
					<?php if ( $solo_estate_areas ) : ?>
						<dl class="solo-estate-details">
							<?php foreach ( $solo_estate_areas as $solo_estate_fact ) : ?>
								<div><dt><?php echo esc_html( $solo_estate_fact[0] ); ?></dt><dd><?php echo esc_html( $solo_estate_fact[1] ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
					<?php echo $solo_estate_rooms( $solo_estate_vfloor ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built. ?>
				</div>
			</div>
		<?php endforeach; ?>
	</section>
<?php endif; ?>

<?php if ( $solo_estate_prev || $solo_estate_next ) : ?>
	<nav class="solo-estate-pager">
		<?php foreach ( array( 'prev' => $solo_estate_prev, 'next' => $solo_estate_next ) as $solo_estate_dir => $solo_estate_item ) : ?>
			<?php $solo_estate_target = $solo_estate_item ? Renderer::target( $solo_estate_item ) : array( 'href' => '' ); ?>
			<?php if ( $solo_estate_target['href'] ) : ?>
				<a class="solo-estate-pager__<?php echo esc_attr( $solo_estate_dir ); ?>" href="<?php echo esc_url( $solo_estate_target['href'] ); ?>">
					<span class="solo-estate-pager__arrow" aria-hidden="true"><?php echo 'prev' === $solo_estate_dir ? '←' : '→'; ?></span>
					<span class="solo-estate-pager__text">
						<strong><?php echo esc_html( Nodes::display_title( $solo_estate_item ) ); ?></strong>
						<?php $solo_estate_area = Renderer::area( $solo_estate_item->area ); ?>
						<?php if ( '' !== $solo_estate_area ) : ?>
							<small><?php echo esc_html( $solo_estate_area ); ?></small>
						<?php endif; ?>
					</span>
				</a>
			<?php else : ?>
				<span></span>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>

<?php if ( $solo_estate_lead ) : ?>
	<div id="<?php echo esc_attr( $solo_estate_lead_id ); ?>" class="solo-estate-lead-anchor">
		<?php echo Renderer::template( 'lead-form', array( 'node' => $node ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
<?php endif; ?>
