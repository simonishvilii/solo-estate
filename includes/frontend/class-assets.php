<?php
/**
 * Front-end CSS/JS, loaded only on pages that render Solo Estate output.
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

use SoloEstate\I18n;
use SoloEstate\Settings;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

class Assets {

	/** @var bool */
	private static $enqueued = false;

	/**
	 * Registers handles.
	 */
	public static function register() {
		wp_register_style( 'solo-estate', SOLO_ESTATE_URL . 'assets/css/frontend.css', array(), \SoloEstate\Plugin::asset_version( 'assets/css/frontend.css' ) );
		wp_register_script( 'solo-estate', SOLO_ESTATE_URL . 'assets/js/frontend.js', array(), \SoloEstate\Plugin::asset_version( 'assets/js/frontend.js' ), true );
	}

	/**
	 * CSS variables from Settings → Appearance. Values are sanitized on save (hex colours,
	 * numbers, a font stack of letters, digits, spaces, commas, quotes and hyphens).
	 *
	 * @return string
	 */
	public static function variables() {
		$vars = array(
			'accent'      => Settings::get( 'accent_color' ),
			'highlight'   => Settings::get( 'highlight_color' ),
			'fill'        => Settings::get( 'fill_opacity' ),
			'fill-hover'  => Settings::get( 'hover_opacity' ),
			'text'        => Settings::get( 'text_color' ),
			'muted'       => Settings::get( 'muted_color' ),
			'line'        => Settings::get( 'line_color' ),
			'soft'        => Settings::get( 'soft_color' ),
			'card'        => Settings::get( 'card_color' ),
			'radius'      => (int) Settings::get( 'radius' ) . 'px',
			'button-text' => Settings::get( 'button_text' ),
			'on-accent'   => Settings::get( 'accent_text' ),
		);
		if ( Settings::get( 'button_color' ) ) {
			$vars['button'] = Settings::get( 'button_color' );
		}
		$web_font = (string) Settings::get( 'web_font' );
		if ( Settings::get( 'font_family' ) ) {
			$vars['font'] = Settings::get( 'font_family' );
		} elseif ( isset( Settings::WEB_FONTS[ $web_font ] ) ) {
			$vars['font'] = Settings::WEB_FONTS[ $web_font ];
		}
		if ( Settings::get( 'font_size' ) ) {
			$vars['size'] = (int) Settings::get( 'font_size' ) . 'px';
		}
		if ( ! Settings::get( 'card_shadow' ) ) {
			$vars['shadow'] = 'none';
		}
		$css = '';
		foreach ( $vars as $name => $value ) {
			$value = str_replace( array( '<', '>', ';', '{', '}' ), '', (string) $value );
			if ( '' !== $value ) {
				$css .= '--solo-estate-' . $name . ':' . $value . ';';
			}
		}
		return '.solo-estate-app{' . $css . '}';
	}

	/**
	 * Enqueues once per request with settings-driven CSS variables.
	 */
	public static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}
		if ( ! wp_style_is( 'solo-estate', 'registered' ) ) {
			self::register();
		}
		self::$enqueued = true;

		wp_enqueue_style( 'solo-estate' );
		// Optional Google Font (Settings → Style). Inter and Manrope bring Noto Sans Georgian
		// along, because they have no Georgian letters.
		$web_font = (string) Settings::get( 'web_font' );
		if ( isset( Settings::WEB_FONTS[ $web_font ] ) ) {
			$families = array( $web_font );
			if ( false === strpos( $web_font, 'Georgian' ) ) {
				$families[] = 'Noto Sans Georgian';
			}
			$query = implode(
				'&',
				array_map(
					static function ( $family ) {
						return 'family=' . str_replace( ' ', '+', $family ) . ':wght@400;500;600;700';
					},
					$families
				)
			);
			wp_enqueue_style( 'solo-estate-font', 'https://fonts.googleapis.com/css2?' . $query . '&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URLs must not carry ?ver.
		}
		wp_add_inline_style( 'solo-estate', self::variables() );

		wp_enqueue_script( 'solo-estate' );
		wp_localize_script(
			'solo-estate',
			'soloEstateFront',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'lang'     => I18n::current(),
				'tracking' => (bool) Settings::get( 'tracking_events' ),
				'attribution' => \SoloEstate\Attribution::enabled(),
				'i18n'     => array(
					'error'    => Texts::get( 'lead_error' ),
					'success'  => Texts::get( 'lead_success' ),
					'close'    => Texts::get( 'close' ),
					'previous' => Texts::get( 'previous' ),
					'next'     => Texts::get( 'next' ),
					'photos'   => Texts::get( 'photos' ),
					'tour'     => Texts::get( 'virtual_tour' ),
				),
			)
		);
	}

	/**
	 * Styles that could not go into <head> any more (the selector was found only while the
	 * page body was rendered: page builder, widget, template part). Printed right before the
	 * selector so it never shows unstyled; WordPress would otherwise print them in the footer.
	 *
	 * @return string
	 */
	public static function late_styles() {
		if ( ! did_action( 'wp_head' ) || wp_style_is( 'solo-estate', 'done' ) ) {
			return '';
		}
		ob_start();
		wp_print_styles( array( 'solo-estate-font', 'solo-estate' ) );
		return (string) ob_get_clean();
	}
}
