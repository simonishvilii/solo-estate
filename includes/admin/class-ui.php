<?php
/**
 * Small form helpers shared by admin screens.
 *
 * @package SoloEstate\Admin
 */

namespace SoloEstate\Admin;

use SoloEstate\I18n;

defined( 'ABSPATH' ) || exit;

class Ui {

	/**
	 * Translatable text input: one input per language, shown as tabs.
	 *
	 * @param string $name   Base input name, e.g. i18n[title].
	 * @param array  $values lang => value.
	 * @param string $type   text|textarea|editor|url.
	 * @param array  $attrs  Extra attributes (placeholder, rows).
	 */
	public static function i18n_field( $name, $values, $type = 'text', array $attrs = array() ) {
		$languages = I18n::languages();
		$values    = is_array( $values ) ? $values : array();
		$id_base   = 'solo-estate-' . md5( $name );

		echo '<div class="solo-estate-i18n" data-solo-estate-i18n>';
		if ( count( $languages ) > 1 ) {
			echo '<div class="solo-estate-i18n__tabs" role="tablist">';
			$first = true;
			foreach ( $languages as $code => $lang ) {
				$filled = isset( $values[ $code ] ) && '' !== trim( wp_strip_all_tags( (string) $values[ $code ] ) );
				printf(
					'<button type="button" role="tab" class="solo-estate-i18n__tab%1$s%2$s" data-lang="%3$s" aria-selected="%4$s">%5$s</button>',
					$first ? ' is-active' : '',
					$filled ? ' is-filled' : '',
					esc_attr( $code ),
					$first ? 'true' : 'false',
					esc_html( strtoupper( $code ) )
				);
				$first = false;
			}
			echo '</div>';
		}

		$first = true;
		foreach ( $languages as $code => $lang ) {
			$value = isset( $values[ $code ] ) ? (string) $values[ $code ] : '';
			$input = $name . '[' . $code . ']';
			$id    = $id_base . '-' . $code;

			printf( '<div class="solo-estate-i18n__pane%1$s" data-lang="%2$s" title="%3$s">', $first ? ' is-active' : '', esc_attr( $code ), esc_attr( $lang['name'] ) );

			if ( 'editor' === $type ) {
				wp_editor(
					$value,
					str_replace( '-', '_', $id ),
					array(
						'textarea_name' => $input,
						'textarea_rows' => isset( $attrs['rows'] ) ? (int) $attrs['rows'] : 8,
						'media_buttons' => true,
					)
				);
			} elseif ( 'textarea' === $type ) {
				printf(
					'<textarea class="large-text" id="%1$s" name="%2$s" rows="%3$d" placeholder="%4$s">%5$s</textarea>',
					esc_attr( $id ),
					esc_attr( $input ),
					isset( $attrs['rows'] ) ? (int) $attrs['rows'] : 3,
					esc_attr( isset( $attrs['placeholder'] ) ? $attrs['placeholder'] : '' ),
					esc_textarea( $value )
				);
			} else {
				printf(
					'<input type="%1$s" class="regular-text" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s" lang="%6$s">',
					'url' === $type ? 'url' : 'text',
					esc_attr( $id ),
					esc_attr( $input ),
					esc_attr( $value ),
					esc_attr( isset( $attrs['placeholder'] ) ? $attrs['placeholder'] : $lang['name'] ),
					esc_attr( $code )
				);
			}
			echo '</div>';
			$first = false;
		}
		echo '</div>';
	}

	/**
	 * Media picker storing an attachment id.
	 *
	 * @param string $name     Input name.
	 * @param int    $value    Attachment id.
	 * @param string $size     Preview size.
	 */
	public static function image_field( $name, $value, $size = 'medium' ) {
		$value = (int) $value;
		$src   = $value ? wp_get_attachment_image_url( $value, $size ) : '';
		echo '<div class="solo-estate-image-field" data-solo-estate-image>';
		printf( '<input type="hidden" name="%1$s" value="%2$s" data-solo-estate-image-id>', esc_attr( $name ), esc_attr( $value ? $value : '' ) );
		printf( '<div class="solo-estate-image-field__preview"%1$s><img src="%2$s" alt=""></div>', $src ? '' : ' hidden', esc_url( $src ? $src : '' ) );
		printf( '<button type="button" class="button" data-solo-estate-image-pick>%s</button> ', esc_html__( 'Choose image', 'solo-estate' ) );
		printf( '<button type="button" class="button-link button-link-delete" data-solo-estate-image-remove%1$s>%2$s</button>', $value ? '' : ' hidden', esc_html__( 'Remove', 'solo-estate' ) );
		echo '</div>';
	}

	/**
	 * Sortable multi-image picker storing a comma-separated list of attachment ids.
	 *
	 * @param string $name Input name.
	 * @param int[]  $ids  Attachment ids.
	 */
	public static function gallery_field( $name, array $ids ) {
		echo '<div class="solo-estate-gallery-field" data-solo-estate-gallery>';
		printf( '<input type="hidden" name="%1$s" value="%2$s" data-solo-estate-gallery-ids>', esc_attr( $name ), esc_attr( implode( ',', $ids ) ) );
		echo '<ul class="solo-estate-gallery-field__list">';
		foreach ( $ids as $id ) {
			$src = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( ! $src ) {
				continue;
			}
			// Drag to reorder, or the arrows (keyboard).
			printf(
				'<li data-id="%1$d"><img src="%2$s" alt="%3$s"><button type="button" class="solo-estate-gallery-field__remove" aria-label="%4$s">×</button><button type="button" class="solo-estate-gallery-field__move solo-estate-gallery-field__move--prev" data-solo-estate-move="-1" aria-label="%5$s">‹</button><button type="button" class="solo-estate-gallery-field__move solo-estate-gallery-field__move--next" data-solo-estate-move="1" aria-label="%6$s">›</button></li>',
				(int) $id,
				esc_url( $src ),
				esc_attr( get_the_title( $id ) ),
				esc_attr__( 'Remove', 'solo-estate' ),
				esc_attr__( 'Move earlier', 'solo-estate' ),
				esc_attr__( 'Move later', 'solo-estate' )
			);
		}
		echo '</ul>';
		printf( '<button type="button" class="button" data-solo-estate-gallery-add>%s</button>', esc_html__( 'Add images', 'solo-estate' ) );
		echo '</div>';
	}

	/**
	 * Attachment ids from a comma-separated request value; only real attachments are kept.
	 *
	 * @param mixed $raw Raw value.
	 * @param int   $max Maximum number of images.
	 * @return int[]
	 */
	public static function read_gallery( $raw, $max = 60 ) {
		$ids = array_unique( array_filter( array_map( 'absint', explode( ',', (string) wp_unslash( $raw ) ) ) ) );
		$ids = array_filter(
			$ids,
			static function ( $id ) {
				return 'attachment' === get_post_type( $id );
			}
		);
		return array_slice( array_values( $ids ), 0, $max );
	}

	/**
	 * Reads a translatable field from a request, sanitizing each language.
	 *
	 * @param mixed  $raw  Raw value (lang => value).
	 * @param string $type text|textarea|html|url.
	 * @return array<string,string>
	 */
	public static function read_i18n( $raw, $type = 'text' ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( I18n::codes() as $code ) {
			if ( ! isset( $raw[ $code ] ) ) {
				continue;
			}
			$value = wp_unslash( $raw[ $code ] );
			switch ( $type ) {
				case 'html':
					$value = current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
					break;
				case 'url':
					$value = esc_url_raw( $value );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $value );
					break;
				default:
					$value = sanitize_text_field( $value );
			}
			if ( '' !== trim( $value ) ) {
				$out[ $code ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Decimal from a request value, or null when empty.
	 *
	 * @param mixed $raw Raw.
	 * @return float|null
	 */
	public static function read_decimal( $raw ) {
		$raw = trim( str_replace( array( ' ', ',' ), array( '', '.' ), (string) wp_unslash( $raw ) ) );
		return ( '' === $raw || ! is_numeric( $raw ) ) ? null : (float) $raw;
	}

	/**
	 * Colored status pill.
	 *
	 * @param object|null $status Status.
	 * @return string HTML.
	 */
	public static function status_pill( $status ) {
		if ( ! $status ) {
			return '<span class="solo-estate-pill solo-estate-pill--none">—</span>';
		}
		return sprintf(
			'<span class="solo-estate-pill"><span class="solo-estate-pill__dot" style="background:%1$s"></span>%2$s</span>',
			esc_attr( $status->color ),
			esc_html( \SoloEstate\Statuses::title( $status, I18n::default_code() ) )
		);
	}
}
