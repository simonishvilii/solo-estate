<?php
/**
 * Admin labels: the plugin's own interface strings (field names, buttons, descriptions)
 * renamed from the admin, per language. Stored as {original: {lang code: text}} and applied
 * through the text domain's gettext filter, on top of the bundled translation.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Admin label overrides.
 */
class Labels {

	const OPTION = 'solo_estate_labels';

	/** @var array<string,string>|null Overrides for the current language: original => text. */
	private static $map = null;

	/**
	 * Hooks the filter when there is anything to apply.
	 */
	public static function register() {
		if ( get_option( self::OPTION ) ) {
			add_filter( 'gettext_solo-estate', array( __CLASS__, 'filter' ), 10, 2 );
		}
	}

	/**
	 * All overrides.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function all() {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}

	/**
	 * Gettext filter.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @return string
	 */
	public static function filter( $translation, $text ) {
		$map = self::map();
		return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
	}

	/**
	 * Overrides for the language of the current locale (the user's admin language).
	 *
	 * @return array<string,string>
	 */
	private static function map() {
		if ( null !== self::$map ) {
			return self::$map;
		}
		// The language list (Polylang, WPML) is ready on `init`; strings before it stay as they are.
		if ( ! did_action( 'init' ) ) {
			return array();
		}
		$code      = self::code_for_locale( determine_locale() );
		self::$map = array();
		foreach ( self::all() as $text => $values ) {
			if ( isset( $values[ $code ] ) && '' !== $values[ $code ] ) {
				self::$map[ $text ] = $values[ $code ];
			}
		}
		return self::$map;
	}

	/**
	 * Content language code for a WordPress locale (ka_GE → ka): exact locale first, then
	 * the language part.
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function code_for_locale( $locale ) {
		$short = strtolower( substr( (string) $locale, 0, 2 ) );
		foreach ( I18n::languages() as $code => $lang ) {
			if ( $lang['locale'] === $locale ) {
				return $code;
			}
		}
		foreach ( I18n::languages() as $code => $lang ) {
			if ( strtolower( substr( $lang['locale'], 0, 2 ) ) === $short || strtolower( substr( $code, 0, 2 ) ) === $short ) {
				return $code;
			}
		}
		return $short;
	}

	/**
	 * Placeholders (%s, %1$d…) in a string, sorted, to check that a renamed string keeps them.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function placeholders( $text ) {
		preg_match_all( '/%(?:\d+\$)?[sdf]/', (string) $text, $m );
		$list = $m[0];
		sort( $list );
		return $list;
	}

	/**
	 * Updates the overrides of the given originals; other originals keep theirs.
	 *
	 * @param array<string,array<string,string>> $rows Original => [code => raw text]; empty text removes.
	 * @return string[] Originals whose new text was refused (placeholders changed).
	 */
	public static function update( array $rows ) {
		$all     = self::all();
		$refused = array();
		$codes   = I18n::codes();
		foreach ( $rows as $text => $values ) {
			$text = (string) $text;
			foreach ( $codes as $code ) {
				$raw = isset( $values[ $code ] ) ? trim( (string) $values[ $code ] ) : '';
				// Strings with markup (links) keep safe HTML; the rest is plain text.
				$new = false !== strpos( $text, '<' ) ? wp_kses_post( $raw ) : sanitize_text_field( $raw );
				if ( '' === $new ) {
					unset( $all[ $text ][ $code ] );
					continue;
				}
				// %s / %1$d are filled in by the code: a text without them would break the output.
				if ( self::placeholders( $new ) !== self::placeholders( $text ) ) {
					$refused[] = $text;
					continue;
				}
				$all[ $text ][ $code ] = $new;
			}
			if ( empty( $all[ $text ] ) ) {
				unset( $all[ $text ] );
			}
		}
		update_option( self::OPTION, $all, true );
		self::$map = null;
		return array_values( array_unique( $refused ) );
	}
}
