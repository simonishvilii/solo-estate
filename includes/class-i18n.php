<?php
/**
 * Content languages, independent of any specific multilingual plugin.
 *
 * Translatable values are stored as JSON: {"title":{"ka":"…","en":"…"}, …}.
 * The list of languages and the current language come from an adapter:
 * Polylang, WPML, TranslatePress, or the plugin's own setting when none is active.
 * Other plugins can plug in through the `solo_estate_languages` and `solo_estate_current_language` filters.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class I18n {

	/** @var array<string,array{code:string,name:string,locale:string}>|null */
	private static $languages = null;

	/**
	 * Detected multilingual plugin.
	 *
	 * @return string polylang|wpml|translatepress|none
	 */
	public static function provider() {
		if ( function_exists( 'pll_languages_list' ) ) {
			return 'polylang';
		}
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return 'wpml';
		}
		if ( class_exists( 'TRP_Translate_Press' ) ) {
			return 'translatepress';
		}
		return 'none';
	}

	/**
	 * Content languages keyed by code, default language first.
	 *
	 * @return array<string,array{code:string,name:string,locale:string}>
	 */
	public static function languages() {
		if ( null !== self::$languages ) {
			return self::$languages;
		}

		$list = array();

		switch ( self::provider() ) {
			case 'polylang':
				foreach ( (array) pll_languages_list( array( 'fields' => '' ) ) as $lang ) {
					if ( is_object( $lang ) && ! empty( $lang->slug ) ) {
						$list[ $lang->slug ] = self::lang( $lang->slug, $lang->name, $lang->locale );
					}
				}
				break;

			case 'wpml':
				$active = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
				foreach ( (array) $active as $code => $lang ) {
					$list[ $code ] = self::lang(
						$code,
						isset( $lang['native_name'] ) ? $lang['native_name'] : $code,
						isset( $lang['default_locale'] ) ? $lang['default_locale'] : ''
					);
				}
				break;

			case 'translatepress':
				$trp = get_option( 'trp_settings' );
				if ( ! empty( $trp['translation-languages'] ) ) {
					foreach ( (array) $trp['translation-languages'] as $locale ) {
						$list[ $locale ] = self::lang( $locale, self::locale_name( $locale ), $locale );
					}
				}
				break;
		}

		if ( ! $list ) {
			$list = self::manual_languages();
		}

		/**
		 * Filters the content languages.
		 *
		 * @param array  $list     code => [code, name, locale].
		 * @param string $provider Detected multilingual plugin.
		 */
		$list = (array) apply_filters( 'solo_estate_languages', $list, self::provider() );

		if ( ! $list ) {
			$code          = self::code_from_locale( get_locale() );
			$list[ $code ] = self::lang( $code, $code, get_locale() );
		}

		// Default language first.
		$default = self::detect_default( $list );
		if ( isset( $list[ $default ] ) ) {
			$list = array( $default => $list[ $default ] ) + $list;
		}

		// Multilingual plugins finish loading their languages on `init`; don't cache an early answer.
		if ( did_action( 'init' ) ) {
			self::$languages = $list;
		}
		return $list;
	}

	/**
	 * Language codes.
	 *
	 * @return string[]
	 */
	public static function codes() {
		return array_keys( self::languages() );
	}

	/**
	 * Default content language code.
	 *
	 * @return string
	 */
	public static function default_code() {
		$codes = self::codes();
		return reset( $codes );
	}

	/**
	 * Current front-end language code.
	 *
	 * @return string
	 */
	public static function current() {
		$code = '';

		switch ( self::provider() ) {
			case 'polylang':
				$code = function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'slug' ) : '';
				break;
			case 'wpml':
				$code = (string) apply_filters( 'wpml_current_language', null );
				break;
			case 'translatepress':
				global $TRP_LANGUAGE; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
				$code = is_string( $TRP_LANGUAGE ) ? $TRP_LANGUAGE : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
				break;
		}

		if ( '' === $code ) {
			$code = self::match_locale( determine_locale() );
		}

		/**
		 * Filters the current content language code.
		 *
		 * @param string $code Detected code.
		 */
		$code = (string) apply_filters( 'solo_estate_current_language', $code );

		return isset( self::languages()[ $code ] ) ? $code : self::default_code();
	}

	/**
	 * Validates a code coming from a request.
	 *
	 * @param mixed $code Raw value.
	 * @return string Known code or the default.
	 */
	public static function sanitize_code( $code ) {
		$code = sanitize_key( (string) $code );
		// Locale-style codes (TranslatePress) contain an underscore and uppercase letters.
		foreach ( self::codes() as $known ) {
			if ( strtolower( $known ) === $code ) {
				return $known;
			}
		}
		return self::default_code();
	}

	/**
	 * Picks a value for a language with fallback to the default language, then to any non-empty value.
	 *
	 * @param array<string,string>|string|null $values Values keyed by language.
	 * @param string|null                      $lang   Language code; current when null.
	 * @return string
	 */
	public static function pick( $values, $lang = null ) {
		if ( ! is_array( $values ) ) {
			return (string) $values;
		}
		$lang = $lang ? $lang : self::current();

		if ( isset( $values[ $lang ] ) && '' !== $values[ $lang ] ) {
			return (string) $values[ $lang ];
		}
		$default = self::default_code();
		if ( isset( $values[ $default ] ) && '' !== $values[ $default ] ) {
			return (string) $values[ $default ];
		}
		foreach ( $values as $value ) {
			if ( '' !== (string) $value ) {
				return (string) $value;
			}
		}
		return '';
	}

	/**
	 * Decodes a stored i18n JSON blob.
	 *
	 * @param string|null $json Stored value.
	 * @return array<string,array<string,string>>
	 */
	public static function decode( $json ) {
		if ( ! is_string( $json ) || '' === $json ) {
			return array();
		}
		// Invalid UTF-8 (e.g. after a dump restored with the wrong charset) would otherwise make
		// the whole blob unreadable, and the next save would overwrite it with empty fields.
		$data = json_decode( $json, true, 512, JSON_INVALID_UTF8_SUBSTITUTE );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Adds the stored values of languages that are not active right now to newly entered
	 * i18n data, so saving a form (which only has fields for the active languages) does not
	 * delete them: a language removed from Polylang/WPML for a while, or a switch between
	 * multilingual plugins, keeps its texts.
	 *
	 * @param array $new Field => [lang => value] being saved.
	 * @param array $old Field => [lang => value] stored.
	 * @return array
	 */
	public static function keep_inactive( array $new, array $old ) {
		$active = self::codes();
		foreach ( $old as $field => $values ) {
			if ( ! is_array( $values ) || ! array_key_exists( $field, $new ) ) {
				continue;
			}
			foreach ( $values as $code => $value ) {
				if ( ! in_array( (string) $code, $active, true ) && ! isset( $new[ $field ][ $code ] ) && '' !== (string) $value ) {
					$new[ $field ]           = is_array( $new[ $field ] ) ? $new[ $field ] : array();
					$new[ $field ][ $code ] = $value;
				}
			}
		}
		return $new;
	}

	/**
	 * Encodes i18n data for storage.
	 *
	 * @param array $data Field => [lang => value].
	 * @return string
	 */
	public static function encode( array $data ) {
		return (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Languages configured on the settings screen, used when no multilingual plugin is active.
	 * Format per line: code|Name|locale.
	 *
	 * @return array<string,array{code:string,name:string,locale:string}>
	 */
	private static function manual_languages() {
		$raw  = (string) Settings::get( 'languages' );
		$list = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			$code  = sanitize_key( $parts[0] );
			if ( '' === $code ) {
				continue;
			}
			$list[ $code ] = self::lang( $code, isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : $code, isset( $parts[2] ) ? $parts[2] : '' );
		}
		return $list;
	}

	/**
	 * Default language code for a list.
	 *
	 * @param array $list Languages.
	 * @return string
	 */
	private static function detect_default( array $list ) {
		$code = '';
		switch ( self::provider() ) {
			case 'polylang':
				$code = function_exists( 'pll_default_language' ) ? (string) pll_default_language( 'slug' ) : '';
				break;
			case 'wpml':
				$code = (string) apply_filters( 'wpml_default_language', null );
				break;
			case 'translatepress':
				$trp  = get_option( 'trp_settings' );
				$code = isset( $trp['default-language'] ) ? (string) $trp['default-language'] : '';
				break;
		}
		if ( '' === $code || ! isset( $list[ $code ] ) ) {
			$code = self::match_locale( get_locale(), $list );
		}
		return $code;
	}

	/**
	 * Finds the language whose locale (or code) matches a WordPress locale.
	 *
	 * @param string     $locale Locale such as ka_GE.
	 * @param array|null $list   Languages; defaults to all.
	 * @return string
	 */
	private static function match_locale( $locale, $list = null ) {
		$list  = null === $list ? self::languages() : $list;
		$short = self::code_from_locale( $locale );
		foreach ( $list as $code => $lang ) {
			if ( $lang['locale'] === $locale || $code === $locale ) {
				return $code;
			}
		}
		foreach ( $list as $code => $lang ) {
			if ( $code === $short || self::code_from_locale( $lang['locale'] ) === $short ) {
				return $code;
			}
		}
		$codes = array_keys( $list );
		return (string) reset( $codes );
	}

	/**
	 * Native name of a locale ("ka_GE" → "ქართული") without any network request: WordPress's
	 * cached list of translations when present, else PHP intl, else the locale itself.
	 * (wp_get_available_translations() would call api.wordpress.org on page views.)
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	private static function locale_name( $locale ) {
		$cached = get_site_transient( 'available_translations' );
		if ( is_array( $cached ) && ! empty( $cached[ $locale ]['native_name'] ) ) {
			return (string) $cached[ $locale ]['native_name'];
		}
		if ( 'en_US' === $locale ) {
			return 'English (United States)';
		}
		if ( class_exists( 'Locale' ) ) {
			$name = \Locale::getDisplayName( $locale, $locale );
			if ( is_string( $name ) && '' !== $name && $name !== $locale ) {
				return $name;
			}
		}
		return (string) $locale;
	}

	private static function code_from_locale( $locale ) {
		$parts = explode( '_', (string) $locale );
		return strtolower( $parts[0] );
	}

	private static function lang( $code, $name, $locale ) {
		return array(
			'code'   => (string) $code,
			'name'   => (string) $name,
			'locale' => (string) $locale,
		);
	}

	/**
	 * Clears the cached language list (tests, settings save).
	 */
	public static function reset() {
		self::$languages = null;
	}
}
