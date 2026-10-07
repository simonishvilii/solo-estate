<?php
/**
 * Apartment filter form (GET).
 *
 * Override by copying to yourtheme/solo-estate/search-form.php. Keep the se_* field names.
 *
 * @package SoloEstate
 *
 * @var object|null $project Fixed project, or null in the catalog.
 * @var array       $params  Current values.
 * @var bool        $open    Expanded.
 * @var array       $options projects, buildings, rooms, area [min,max], price [min,max], symbol, floor [min,max]|null.
 */

use SoloEstate\Frontend\Renderer;
use SoloEstate\Frontend\Search;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

$solo_estate_value = static function ( $v ) {
	return null === $v ? '' : Renderer::number( $v );
};
$solo_estate_ph    = static function ( $v ) {
	return null === $v ? '' : number_format_i18n( $v );
};
?>
<details class="solo-estate-filter"<?php echo $open ? ' open' : ''; ?>>
	<summary>
		<span class="solo-estate-filter__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></span>
		<span class="solo-estate-filter__heading"><?php echo esc_html( Texts::get( 'filter_title' ) ); ?></span>
		<span class="solo-estate-filter__chevron" aria-hidden="true"></span>
	</summary>
	<form method="get" action="<?php echo esc_url( Renderer::base_url() ); ?>#<?php echo esc_attr( wp_parse_url( Renderer::root_url(), PHP_URL_FRAGMENT ) ); ?>" class="solo-estate-filter__form" data-solo-estate-filter>
		<?php echo Search::hidden_inputs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input type="hidden" name="se_q" value="1">
		<?php if ( $project ) : ?>
			<input type="hidden" name="se_project" value="<?php echo esc_attr( $project->id ); ?>">
		<?php endif; ?>

		<?php if ( $options['projects'] ) : ?>
			<label class="solo-estate-filter__field">
				<span><?php echo esc_html( Texts::get( 'project' ) ); ?></span>
				<select name="se_project" data-solo-estate-project-select>
					<option value=""><?php echo esc_html( Texts::get( 'any' ) ); ?></option>
					<?php foreach ( $options['projects'] as $solo_estate_p ) : ?>
						<option value="<?php echo esc_attr( $solo_estate_p->id ); ?>"<?php selected( $params['project'], $solo_estate_p->id ); ?>><?php echo esc_html( Nodes::display_title( $solo_estate_p ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>

		<?php if ( count( $options['buildings'] ) > 1 ) : ?>
			<label class="solo-estate-filter__field">
				<span><?php echo esc_html( Texts::get( 'building' ) ); ?></span>
				<select name="se_building" data-solo-estate-building-select>
					<option value=""><?php echo esc_html( Texts::get( 'any' ) ); ?></option>
					<?php foreach ( $options['buildings'] as $solo_estate_b ) : ?>
						<option value="<?php echo esc_attr( $solo_estate_b['id'] ); ?>" data-project="<?php echo esc_attr( $solo_estate_b['project'] ); ?>"<?php selected( $params['building'], $solo_estate_b['id'] ); ?>><?php echo esc_html( $solo_estate_b['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>

		<?php if ( $options['rooms'] ) : ?>
			<fieldset class="solo-estate-filter__field solo-estate-filter__rooms">
				<legend><?php echo esc_html( Texts::get( 'rooms' ) ); ?></legend>
				<label><input type="radio" name="se_rooms" value=""<?php checked( '', $params['rooms'] ); ?>><span><?php echo esc_html( Texts::get( 'any' ) ); ?></span></label>
				<?php
				$solo_estate_max = max( $options['rooms'] );
				foreach ( $options['rooms'] as $solo_estate_r ) :
					if ( $solo_estate_r > 4 ) {
						break;
					}
					$solo_estate_key = ( 4 === $solo_estate_r && $solo_estate_max > 4 ) ? '4+' : (string) $solo_estate_r;
					?>
					<label><input type="radio" name="se_rooms" value="<?php echo esc_attr( $solo_estate_key ); ?>"<?php checked( $solo_estate_key, $params['rooms'] ); ?>><span><?php echo esc_html( 0 === $solo_estate_r ? Texts::get( 'studio' ) : $solo_estate_key ); ?></span></label>
				<?php endforeach; ?>
				<?php if ( $solo_estate_max > 4 && ! in_array( 4, $options['rooms'], true ) ) : ?>
					<label><input type="radio" name="se_rooms" value="5+"<?php checked( '5+', $params['rooms'] ); ?>><span>5+</span></label>
				<?php endif; ?>
			</fieldset>
		<?php endif; ?>

		<div class="solo-estate-filter__field solo-estate-filter__range">
			<span><?php echo esc_html( Texts::get( 'area' ) . ', ' . Settings::get( 'area_unit' ) ); ?></span>
			<div>
				<input type="text" inputmode="decimal" name="se_area_min" value="<?php echo esc_attr( $solo_estate_value( $params['area_min'] ) ); ?>" placeholder="<?php echo esc_attr( null === $options['area'][0] ? '' : sprintf( Texts::get( 'from' ), $solo_estate_ph( $options['area'][0] ) ) ); ?>" aria-label="<?php echo esc_attr( Texts::get( 'area' ) . ' — min' ); ?>">
				<input type="text" inputmode="decimal" name="se_area_max" value="<?php echo esc_attr( $solo_estate_value( $params['area_max'] ) ); ?>" placeholder="<?php echo esc_attr( null === $options['area'][1] ? '' : sprintf( Texts::get( 'to' ), $solo_estate_ph( $options['area'][1] ) ) ); ?>" aria-label="<?php echo esc_attr( Texts::get( 'area' ) . ' — max' ); ?>">
			</div>
		</div>

		<?php if ( $options['floor'] ) : ?>
			<div class="solo-estate-filter__field solo-estate-filter__range">
				<span><?php echo esc_html( Texts::get( 'floor' ) ); ?></span>
				<div>
					<input type="text" inputmode="numeric" name="se_floor_min" value="<?php echo esc_attr( null === $params['floor_min'] ? '' : $params['floor_min'] ); ?>" placeholder="<?php echo esc_attr( sprintf( Texts::get( 'from' ), $options['floor'][0] ) ); ?>" aria-label="<?php echo esc_attr( Texts::get( 'floor' ) . ' — min' ); ?>">
					<input type="text" inputmode="numeric" name="se_floor_max" value="<?php echo esc_attr( null === $params['floor_max'] ? '' : $params['floor_max'] ); ?>" placeholder="<?php echo esc_attr( sprintf( Texts::get( 'to' ), $options['floor'][1] ) ); ?>" aria-label="<?php echo esc_attr( Texts::get( 'floor' ) . ' — max' ); ?>">
				</div>
			</div>
		<?php endif; ?>

		<?php if ( Settings::get( 'show_prices' ) && null !== $options['price'][1] ) : ?>
			<div class="solo-estate-filter__field solo-estate-filter__range">
				<span><?php echo esc_html( Texts::get( 'price' ) . ', ' . $options['symbol'] ); ?></span>
				<div>
					<input type="text" inputmode="decimal" name="se_price_min" value="<?php echo esc_attr( $solo_estate_value( $params['price_min'] ) ); ?>" placeholder="<?php echo esc_attr( null === $options['price'][0] ? '' : sprintf( Texts::get( 'from' ), $solo_estate_ph( $options['price'][0] ) ) ); ?>" aria-label="<?php echo esc_attr( Texts::get( 'price' ) . ' — min' ); ?>">
					<input type="text" inputmode="decimal" name="se_price_max" value="<?php echo esc_attr( $solo_estate_value( $params['price_max'] ) ); ?>" placeholder="<?php echo esc_attr( null === $options['price'][1] ? '' : sprintf( Texts::get( 'to' ), $solo_estate_ph( $options['price'][1] ) ) ); ?>" aria-label="<?php echo esc_attr( Texts::get( 'price' ) . ' — max' ); ?>">
				</div>
			</div>
		<?php endif; ?>

		<?php // Results keep the building and floor order (se_sort still works in links). ?>
		<?php if ( 'default' !== $params['sort'] ) : ?>
			<input type="hidden" name="se_sort" value="<?php echo esc_attr( $params['sort'] ); ?>">
		<?php endif; ?>

		<div class="solo-estate-filter__actions">
			<label class="solo-estate-filter__check"><input type="checkbox" name="se_available" value="1"<?php checked( $params['available'] ); ?>><span class="solo-estate-switch-toggle" aria-hidden="true"></span> <?php echo esc_html( Texts::get( 'only_available' ) ); ?></label>
			<button type="submit" class="solo-estate-button"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg> <?php echo esc_html( Texts::get( 'search' ) ); ?></button>
			<?php if ( Search::is_active() ) : ?>
				<a class="solo-estate-filter__reset" href="<?php echo esc_url( Renderer::root_url() ); ?>"><?php echo esc_html( Texts::get( 'reset' ) ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</details>
