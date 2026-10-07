<?php
/**
 * Plugin settings stored in the `solo_estate_settings` option.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Settings {

	const OPTION = 'solo_estate_settings';

	/** Settings that make up the look of the selector (exported / imported as a style file). */
	const STYLE_KEYS = array( 'web_font', 'accent_color', 'highlight_color', 'fill_opacity', 'hover_opacity', 'tooltip_style', 'tooltip_image', 'sold_color', 'commercial_color', 'commercial_hover', 'font_family', 'font_size', 'radius', 'text_color', 'muted_color', 'line_color', 'soft_color', 'card_color', 'button_color', 'button_text', 'accent_text', 'card_shadow' );

	/** Google Fonts that can be loaded for the selector: name => CSS family. */
	const WEB_FONTS = array(
		'Noto Sans Georgian'  => "'Noto Sans Georgian', sans-serif",
		'Noto Serif Georgian' => "'Noto Serif Georgian', serif",
		'Inter'               => "Inter, 'Noto Sans Georgian', sans-serif",
		'Manrope'             => "Manrope, 'Noto Sans Georgian', sans-serif",
	);

	/**
	 * Current style settings.
	 *
	 * @return array
	 */
	public static function style() {
		return array_intersect_key( self::all(), array_flip( self::STYLE_KEYS ) );
	}

	/**
	 * Applies style settings from an imported file; other settings are kept. Values go through
	 * the same sanitizing as the settings screen, unknown keys are ignored.
	 *
	 * @param array $style Key => value.
	 * @return int Number of style settings applied.
	 */
	public static function apply_style( array $style ) {
		$style = array_intersect_key( $style, array_flip( self::STYLE_KEYS ) );
		$style = array_filter(
			$style,
			static function ( $value ) {
				return is_scalar( $value );
			}
		);
		if ( ! $style ) {
			return 0;
		}
		// save() reads checkboxes from the input, so start from everything stored now.
		$input = wp_slash( array_merge( self::all(), $style ) );
		self::save( $input );
		return count( $style );
	}

	/** @var array|null */
	private static $cache = null;

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Languages used when no multilingual plugin is active: code|Name|locale per line.
			'languages'          => "ka|ქართული|ka_GE\nen|English|en_US\nru|Русский|ru_RU",

			// Prices.
			'show_prices'        => 1,
			'base_currency'      => 'GEL',
			'base_symbol'        => '₾',
			'alt_enabled'        => 1,
			'alt_currency'       => 'USD',
			'alt_symbol'         => '$',
			// "nbg" = National Bank of Georgia, refreshed automatically; "manual" = alt_rate below.
			'rate_source'        => 'nbg',
			'alt_rate'           => '0.37',
			'default_currency'   => 'base',
			'area_unit'          => 'm²',

			// Leads.
			'leads_enabled'      => 1,
			'lead_recipients'    => get_option( 'admin_email' ),
			'lead_subject'       => '[{site}] {project} — {flat}',
			'lead_webhook'       => '',
			'lead_webhook_secret' => '',
			'lead_retention'     => 0,
			'ip_header'          => '',
			'lead_show_on'       => 'flat',
			'tracking_events'    => 1,

			// Appearance.
			'accent_color'       => '#164d47',
			'highlight_color'    => '#be9645',
			'hover_opacity'      => '0.75',
			'fill_opacity'       => '0.45',
			'show_lists'         => 1,
			'project_cards'      => 0,
			'show_filter'        => 1,
			'tooltip_style'      => 'dark',
			'tooltip_image'      => 1,
			'auto_sold_out'      => 1,
			'sold_out_nolink'    => 1,
			'sold_color'         => '#f4e9b0',
			'commercial_color'   => '#2f6fbd',
			'commercial_hover'   => '#1d4f8f',

			// Style: empty / 0 = take it from the theme.
			'web_font'           => '',
			'font_family'        => '',
			'font_size'          => 0,
			'radius'             => 10,
			'text_color'         => '#1f2a2e',
			'muted_color'        => '#6b7478',
			'line_color'         => '#e3e6e8',
			'soft_color'         => '#f5f6f4',
			'card_color'         => '#ffffff',
			'button_color'       => '',
			'button_text'        => '#ffffff',
			'accent_text'        => '#ffffff',
			'card_shadow'        => 1,

			// Maintenance.
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * Single setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitizes and stores settings from a request array.
	 *
	 * @param array $input Raw input.
	 */
	public static function save( array $input ) {
		$defaults = self::defaults();
		$out      = array();

		$checkboxes = array( 'show_prices', 'alt_enabled', 'leads_enabled', 'tracking_events', 'show_lists', 'project_cards', 'show_filter', 'tooltip_image', 'auto_sold_out', 'sold_out_nolink', 'card_shadow', 'delete_on_uninstall' );
		foreach ( $checkboxes as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$texts = array( 'base_currency', 'base_symbol', 'alt_currency', 'alt_symbol', 'area_unit', 'lead_subject' );
		foreach ( $texts as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : $defaults[ $key ];
		}

		$out['languages']        = isset( $input['languages'] ) ? sanitize_textarea_field( wp_unslash( $input['languages'] ) ) : $defaults['languages'];
		$out['alt_rate']         = isset( $input['alt_rate'] ) ? (string) max( 0, (float) str_replace( ',', '.', wp_unslash( $input['alt_rate'] ) ) ) : $defaults['alt_rate'];
		$out['default_currency'] = ( isset( $input['default_currency'] ) && 'alt' === $input['default_currency'] ) ? 'alt' : 'base';
		$out['rate_source']      = ( isset( $input['rate_source'] ) && 'manual' === $input['rate_source'] ) ? 'manual' : 'nbg';
		$out['tooltip_style']    = ( isset( $input['tooltip_style'] ) && 'light' === $input['tooltip_style'] ) ? 'light' : 'dark';
		$out['lead_show_on']     = ( isset( $input['lead_show_on'] ) && in_array( $input['lead_show_on'], array( 'flat', 'all', 'none' ), true ) ) ? $input['lead_show_on'] : 'flat';
		$out['lead_webhook']     = isset( $input['lead_webhook'] ) ? esc_url_raw( wp_unslash( $input['lead_webhook'] ), array( 'https' ) ) : '';
		$out['lead_webhook_secret'] = isset( $input['lead_webhook_secret'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', wp_unslash( $input['lead_webhook_secret'] ) ) : '';
		$out['lead_retention']   = isset( $input['lead_retention'] ) ? min( 3650, absint( $input['lead_retention'] ) ) : 0;
		$out['ip_header']        = ( isset( $input['ip_header'] ) && in_array( $input['ip_header'], array( '', 'cf', 'xff' ), true ) ) ? $input['ip_header'] : '';

		$emails = array();
		foreach ( explode( ',', isset( $input['lead_recipients'] ) ? wp_unslash( $input['lead_recipients'] ) : '' ) as $email ) {
			$email = sanitize_email( trim( $email ) );
			if ( is_email( $email ) ) {
				$emails[] = $email;
			}
		}
		$out['lead_recipients'] = implode( ', ', $emails );

		foreach ( array( 'accent_color', 'highlight_color', 'sold_color', 'commercial_color', 'commercial_hover', 'text_color', 'muted_color', 'line_color', 'soft_color', 'card_color', 'button_color', 'button_text', 'accent_text' ) as $key ) {
			$color       = isset( $input[ $key ] ) ? sanitize_hex_color( wp_unslash( $input[ $key ] ) ) : '';
			$out[ $key ] = $color ? $color : $defaults[ $key ];
		}
		// A font stack like: Manrope, "Noto Sans Georgian", sans-serif. Nothing that could end the CSS rule.
		$font                = isset( $input['font_family'] ) ? preg_replace( '/[^A-Za-z0-9 ,\'"\-]/', '', wp_unslash( $input['font_family'] ) ) : '';
		$out['font_family']  = mb_substr( trim( $font ), 0, 120 );
		$out['web_font']     = ( isset( $input['web_font'] ) && isset( self::WEB_FONTS[ wp_unslash( $input['web_font'] ) ] ) ) ? wp_unslash( $input['web_font'] ) : '';
		$size                = isset( $input['font_size'] ) ? absint( $input['font_size'] ) : 0;
		$out['font_size']    = $size ? min( 24, max( 12, $size ) ) : 0;
		$out['radius']       = isset( $input['radius'] ) ? min( 30, absint( $input['radius'] ) ) : $defaults['radius'];
		foreach ( array( 'hover_opacity', 'fill_opacity' ) as $key ) {
			$value       = isset( $input[ $key ] ) ? (float) wp_unslash( $input[ $key ] ) : (float) $defaults[ $key ];
			$out[ $key ] = (string) min( 1, max( 0, $value ) );
		}

		update_option( self::OPTION, $out );
		self::$cache = null;
		I18n::reset();
	}
}
