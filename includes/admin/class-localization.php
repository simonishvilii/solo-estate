<?php
/**
 * Settings → Localization: every translatable text in one list, in every content language.
 *
 * - Site texts: the front-end interface texts (`SoloEstate\Texts`), per content language.
 * - Admin texts: the plugin's own admin strings (field names, buttons, descriptions), renamed
 *   per language through `SoloEstate\Labels`. Their list comes from the bundled template
 *   (languages/solo-estate.pot), the current wording from the translation files.
 *
 * Searchable and paged, with its own form: a save posts one page only, far below
 * `max_input_vars`, and leaves the other pages as they are.
 *
 * @package SoloEstate\Admin
 */

namespace SoloEstate\Admin;

use SoloEstate\I18n;
use SoloEstate\Labels;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

/**
 * Localization tab.
 */
class Localization {

	const TAB      = 'localization';
	const PER_PAGE = 20;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_post_solo_estate_save_localization', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Whether the current request asks for this tab (search, page or a save redirect).
	 *
	 * @return bool
	 */
	public static function requested() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
		return isset( $_GET['tab'] ) && self::TAB === sanitize_key( wp_unslash( $_GET['tab'] ) );
	}

	/**
	 * The tab panel (outside the settings form: it has a form of its own).
	 *
	 * @param bool $active Whether the tab is open.
	 */
	public static function render_panel( $active ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view filters.
		$search  = isset( $_GET['ls'] ) ? sanitize_text_field( wp_unslash( $_GET['ls'] ) ) : '';
		$changed = ! empty( $_GET['lc'] );
		$paged   = isset( $_GET['lp'] ) ? max( 1, absint( $_GET['lp'] ) ) : 1;
		$refused = ! empty( $_GET['refused'] );
		// phpcs:enable

		$languages = I18n::languages();
		$rows      = self::filter( self::rows( $languages ), $search, $changed );
		$total     = count( $rows );
		$pages     = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$paged     = min( $paged, $pages );
		$rows      = array_slice( $rows, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );
		$state     = array_filter(
			array(
				'tab' => self::TAB,
				'ls'  => $search,
				'lc'  => $changed ? 1 : 0,
			)
		);

		printf( '<div class="solo-estate-tab-panel solo-estate-localization%s" data-panel="%s">', $active ? ' is-active' : '', esc_attr( self::TAB ) );
		echo '<p class="description">' . esc_html__( 'All texts of the plugin: the site (buttons, tooltips, filter, form) and the admin (field names, buttons, descriptions). Write your own wording in any language; an empty field keeps the standard text shown in grey. The admin shows each user the language of their profile.', 'solo-estate' ) . '</p>';
		if ( $refused ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Some texts were not saved: they must keep the placeholders of the original (such as %s or %d), which are filled in automatically.', 'solo-estate' ) . '</p></div>';
		}

		// Search.
		printf( '<form method="get" action="%s" class="solo-estate-loc-search">', esc_url( admin_url( 'admin.php' ) . '#' . self::TAB ) );
		echo '<input type="hidden" name="page" value="solo-estate-settings"><input type="hidden" name="tab" value="' . esc_attr( self::TAB ) . '">';
		printf( '<input type="search" name="ls" value="%1$s" placeholder="%2$s" class="regular-text" aria-label="%3$s"> ', esc_attr( $search ), esc_attr__( 'Search in any language, e.g. Building number', 'solo-estate' ), esc_attr__( 'Search', 'solo-estate' ) );
		printf( '<label><input type="checkbox" name="lc" value="1"%1$s> %2$s</label> ', checked( $changed, true, false ), esc_html__( 'Only changed', 'solo-estate' ) );
		submit_button( __( 'Search', 'solo-estate' ), 'secondary', '', false );
		if ( '' !== $search || $changed ) {
			printf( ' <a href="%1$s">%2$s</a>', esc_url( Admin::url( 'solo-estate-settings', array( 'tab' => self::TAB ) ) . '#' . self::TAB ), esc_html__( 'Show all', 'solo-estate' ) );
		}
		printf( ' <span class="description">%s</span>', esc_html( sprintf( /* translators: %d: number of texts */ _n( '%d text', '%d texts', $total, 'solo-estate' ), $total ) ) );
		echo '</form>';

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Nothing found.', 'solo-estate' ) . '</p></div>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'solo_estate_save_localization' );
		echo '<input type="hidden" name="action" value="solo_estate_save_localization">';
		printf( '<input type="hidden" name="back" value="%s">', esc_attr( Admin::url( 'solo-estate-settings', $state + ( $paged > 1 ? array( 'lp' => $paged ) : array() ) ) ) );

		echo '<table class="widefat striped solo-estate-loc-table"><thead><tr><th class="solo-estate-loc-table__original">' . esc_html__( 'Original', 'solo-estate' ) . '</th>';
		foreach ( $languages as $lang ) {
			echo '<th>' . esc_html( $lang['name'] ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $i => $row ) {
			echo '<tr><td class="solo-estate-loc-table__original">';
			printf( '<span class="solo-estate-loc-tag solo-estate-loc-tag--%1$s">%2$s</span> ', esc_attr( $row['type'] ), esc_html( 'site' === $row['type'] ? __( 'Site', 'solo-estate' ) : __( 'Admin', 'solo-estate' ) ) );
			echo esc_html( $row['original'] );
			printf( '<input type="hidden" name="loc[%1$d][type]" value="%2$s"><input type="hidden" name="loc[%1$d][id]" value="%3$s">', (int) $i, esc_attr( $row['type'] ), esc_attr( $row['id'] ) );
			echo '</td>';
			foreach ( array_keys( $languages ) as $code ) {
				$default = $row['defaults'][ $code ];
				$value   = isset( $row['values'][ $code ] ) ? $row['values'][ $code ] : '';
				if ( mb_strlen( $default ) > 60 ) {
					printf( '<td><textarea class="widefat" rows="3" name="loc[%1$d][%2$s]" placeholder="%4$s" lang="%2$s">%3$s</textarea></td>', (int) $i, esc_attr( $code ), esc_textarea( $value ), esc_attr( $default ) );
				} else {
					printf( '<td><input type="text" class="widefat" name="loc[%1$d][%2$s]" value="%3$s" placeholder="%4$s" lang="%2$s"></td>', (int) $i, esc_attr( $code ), esc_attr( $value ), esc_attr( $default ) );
				}
			}
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<div class="solo-estate-loc-footer">';
		submit_button( __( 'Save changes', 'solo-estate' ), 'primary', 'submit', false );
		if ( $pages > 1 ) {
			echo '<span class="description">' . esc_html__( 'Save before moving to another page.', 'solo-estate' ) . '</span>';
			$links = paginate_links(
				array(
					'base'      => str_replace( '99999', '%#%', Admin::url( 'solo-estate-settings', $state + array( 'lp' => 99999 ) ) ) . '#' . self::TAB,
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'mid_size'  => 2,
					'end_size'  => 1,
					'prev_text' => '‹',
					'next_text' => '›',
				)
			);
			echo '<nav class="solo-estate-loc-pages" aria-label="' . esc_attr__( 'Pages', 'solo-estate' ) . '">' . wp_kses_post( $links ) . '</nav>';
		}
		echo '</div></form></div>';
	}

	/**
	 * Saves the rows of the posted page.
	 */
	public static function handle_save() {
		Admin::check( 'solo_estate_save_localization' );

		$raw   = isset( $_POST['loc'] ) && is_array( $_POST['loc'] ) ? wp_unslash( $_POST['loc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Texts::update() / Labels::update().
		$texts = array();
		$admin = array();
		$known = array_flip( self::originals() );
		$keys  = Texts::registry();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['type'], $row['id'] ) || ! is_string( $row['id'] ) ) {
				continue;
			}
			$id     = $row['id'];
			$values = array_map( 'strval', array_filter( array_diff_key( $row, array_flip( array( 'type', 'id' ) ) ), 'is_string' ) );
			if ( 'site' === $row['type'] && isset( $keys[ $id ] ) ) {
				$texts[ $id ] = $values;
			} elseif ( 'admin' === $row['type'] && isset( $known[ $id ] ) ) {
				$admin[ $id ] = $values;
			}
		}
		Texts::update( $texts );
		$refused = Labels::update( $admin );

		$fallback = Admin::url( 'solo-estate-settings', array( 'tab' => self::TAB ) );
		$back     = isset( $_POST['back'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['back'] ) ), $fallback ) : $fallback;
		$args     = array( 'solo_estate_msg' => 'saved' );
		if ( $refused ) {
			$args['refused'] = 1;
		}
		wp_safe_redirect( add_query_arg( $args, $back ) . '#' . self::TAB );
		exit;
	}

	/**
	 * All rows: site texts first, then admin texts.
	 *
	 * @param array $languages Content languages.
	 * @return array[] Each: type, id, original, defaults [code => text], values [code => text].
	 */
	private static function rows( array $languages ) {
		$rows  = array();
		$saved = get_option( Texts::OPTION, array() );
		foreach ( Texts::registry() as $key => $builtin ) {
			$row = array(
				'type'     => 'site',
				'id'       => $key,
				'original' => $builtin[0],
				'defaults' => array(),
				'values'   => isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array(),
			);
			foreach ( array_keys( $languages ) as $code ) {
				$row['defaults'][ $code ] = Texts::builtin( $key, $code );
			}
			$rows[] = $row;
		}

		$current = array();
		foreach ( $languages as $code => $lang ) {
			$current[ $code ] = self::translations( $lang['locale'] );
		}
		$overrides = Labels::all();
		foreach ( self::originals() as $text ) {
			$row = array(
				'type'     => 'admin',
				'id'       => $text,
				'original' => $text,
				'defaults' => array(),
				'values'   => isset( $overrides[ $text ] ) ? $overrides[ $text ] : array(),
			);
			foreach ( array_keys( $languages ) as $code ) {
				$row['defaults'][ $code ] = isset( $current[ $code ][ $text ] ) && '' !== $current[ $code ][ $text ] ? $current[ $code ][ $text ] : $text;
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Rows matching the search (original, key, standard or own wording in any language).
	 *
	 * @param array[] $rows    Rows.
	 * @param string  $search  Search text.
	 * @param bool    $changed Only rows with own wording.
	 * @return array[]
	 */
	private static function filter( array $rows, $search, $changed ) {
		$out = array();
		foreach ( $rows as $row ) {
			$values = array_filter( $row['values'], 'strlen' );
			if ( $changed && ! $values ) {
				continue;
			}
			if ( '' !== $search ) {
				$haystack = $row['id'] . "\n" . $row['original'] . "\n" . implode( "\n", $row['defaults'] ) . "\n" . implode( "\n", $values );
				if ( false === mb_stripos( $haystack, $search ) ) {
					continue;
				}
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Original (English) admin strings of the plugin, in the template's order. Plural and
	 * context strings are left out.
	 *
	 * @return string[]
	 */
	public static function originals() {
		static $list = null;
		if ( null === $list ) {
			$list = array_keys( self::parse( SOLO_ESTATE_DIR . 'languages/solo-estate.pot' ) );
		}
		return $list;
	}

	/**
	 * Current translations for a locale: a site-wide file (Loco Translate, language packs)
	 * wins over the bundled one, as when WordPress loads them.
	 *
	 * @param string $locale Locale.
	 * @return array<string,string> Original => translation.
	 */
	private static function translations( $locale ) {
		if ( '' === $locale ) {
			return array();
		}
		foreach ( array( WP_LANG_DIR . '/plugins/solo-estate-' . $locale . '.po', SOLO_ESTATE_DIR . 'languages/solo-estate-' . $locale . '.po' ) as $file ) {
			if ( is_readable( $file ) ) {
				return self::parse( $file );
			}
		}
		return array();
	}

	/**
	 * Reads a .po / .pot file into original => translation (header, plural and context
	 * entries skipped).
	 *
	 * @param string $file Path.
	 * @return array<string,string>
	 */
	private static function parse( $file ) {
		$out = array();
		if ( ! is_readable( $file ) ) {
			return $out;
		}
		$lines = file( $file, FILE_IGNORE_NEW_LINES );
		$entry = array();
		$key   = '';
		$flush = static function () use ( &$entry, &$out ) {
			if ( isset( $entry['msgid'] ) && '' !== $entry['msgid'] && ! isset( $entry['msgid_plural'] ) && ! isset( $entry['msgctxt'] ) ) {
				$out[ $entry['msgid'] ] = isset( $entry['msgstr'] ) ? $entry['msgstr'] : '';
			}
			$entry = array();
		};
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				$flush();
				$key = '';
				continue;
			}
			if ( '#' === $line[0] ) {
				continue;
			}
			if ( preg_match( '/^(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?)\s+"(.*)"$/', $line, $m ) ) {
				if ( 'msgid' === $m[1] && isset( $entry['msgid'] ) ) {
					$flush();
				}
				$key           = 'msgstr[0]' === $m[1] ? 'msgstr' : $m[1];
				$entry[ $key ] = stripcslashes( $m[2] );
			} elseif ( '' !== $key && preg_match( '/^"(.*)"$/', $line, $m ) ) {
				$entry[ $key ] .= stripcslashes( $m[1] );
			}
		}
		$flush();
		return $out;
	}
}
