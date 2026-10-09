<?php
/**
 * Language switcher links keep the place in the selector.
 *
 * The selector's place is in the query string (?solo_estate_node=ID, filter se_* args) of the
 * page that holds the shortcode. Multilingual plugins link to the translated page without it,
 * so switching language on a building or apartment landed on that language's project list.
 * The node tree is shared by all languages, so the same arguments open the same place there.
 *
 * @package SoloEstate\Frontend
 */

namespace SoloEstate\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Carries the selector's query args into language switcher URLs.
 */
class Language_Links {

	/**
	 * Hooks (front end only).
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		// Polylang: the translation URL (hreflang links, and the base of the switcher links)
		// carries the item only; the switcher (widget, menu items, pll_the_languages()) also
		// keeps the filter.
		add_filter( 'pll_translation_url', array( __CLASS__, 'carry_item' ) );
		add_filter( 'pll_the_language_link', array( __CLASS__, 'carry' ) );
		// WPML: language switchers, and hreflang links with the item only.
		add_filter( 'icl_ls_languages', array( __CLASS__, 'carry_wpml' ) );
		add_filter( 'wpml_hreflangs', array( __CLASS__, 'hreflangs_wpml' ) );
	}

	/**
	 * Selector args of the current request, sanitized.
	 *
	 * @return array<string,string>
	 */
	public static function args() {
		static $args = null;
		if ( null !== $args ) {
			return $args;
		}
		$args = array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public read-only navigation state.
		if ( ! empty( $_GET[ Renderer::QUERY_ARG ] ) ) {
			$id = absint( $_GET[ Renderer::QUERY_ARG ] );
			if ( $id ) {
				$args[ Renderer::QUERY_ARG ] = (string) $id;
			}
		}
		foreach ( Search::ARGS as $key ) {
			if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) );
				if ( '' !== $value ) {
					$args[ $key ] = mb_substr( $value, 0, 50 );
				}
			}
		}
		// phpcs:enable
		return $args;
	}

	/**
	 * Adds the selector args to a translation URL.
	 *
	 * @param mixed $url URL (false/empty when there is no translation).
	 * @return mixed
	 */
	public static function carry( $url ) {
		$args = self::args();
		if ( ! $args || ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		return add_query_arg( array_map( 'rawurlencode', $args ), $url );
	}

	/**
	 * Adds only the item argument to a translation URL: filter combinations are not indexed
	 * (search results are noindex), so alternate-language links point at the item itself.
	 *
	 * @param mixed $url URL.
	 * @return mixed
	 */
	public static function carry_item( $url ) {
		$args = array_intersect_key( self::args(), array( Renderer::QUERY_ARG => true ) );
		if ( ! $args || ! is_string( $url ) || '' === $url || Seo::is_missing() ) {
			return $url;
		}
		return add_query_arg( $args, remove_query_arg( Search::ARGS, $url ) );
	}

	/**
	 * WPML hreflang links: the item argument only.
	 *
	 * @param mixed $links URLs keyed by language.
	 * @return mixed
	 */
	public static function hreflangs_wpml( $links ) {
		if ( ! is_array( $links ) ) {
			return $links;
		}
		foreach ( $links as $code => $url ) {
			$links[ $code ] = self::carry_item( $url );
		}
		return $links;
	}

	/**
	 * WPML language list.
	 *
	 * @param mixed $languages Languages keyed by code, each with a 'url'.
	 * @return mixed
	 */
	public static function carry_wpml( $languages ) {
		if ( ! is_array( $languages ) ) {
			return $languages;
		}
		foreach ( $languages as $code => $language ) {
			if ( is_array( $language ) && isset( $language['url'] ) ) {
				$languages[ $code ]['url'] = self::carry( $language['url'] );
			}
		}
		return $languages;
	}
}
