<?php
/**
 * wp-admin menu, assets and shared request handling.
 *
 * @package SoloEstate
 */

namespace SoloEstate\Admin;

use SoloEstate\Install;

defined( 'ABSPATH' ) || exit;

class Admin {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );

		Nodes_Page::register();
		Statuses_Page::register();
		Specs_Page::register();
		Leads_Page::register();
		Settings_Page::register();
		Transfer_Page::register();
		Localization::register();
	}

	/**
	 * Menu entries.
	 */
	public static function menu() {
		$cap  = Install::CAPABILITY;
		$sell = Install::CAP_SELL;

		// Sales and marketing see Projects and Leads; the rest is for managers.
		add_menu_page( __( 'Solo Estate', 'solo-estate' ), __( 'Solo Estate', 'solo-estate' ), $sell, 'solo-estate', array( Nodes_Page::class, 'render' ), 'dashicons-building', 26 );
		add_submenu_page( 'solo-estate', __( 'Projects', 'solo-estate' ), __( 'Projects', 'solo-estate' ), $sell, 'solo-estate', array( Nodes_Page::class, 'render' ) );
		add_submenu_page( 'solo-estate', __( 'Statuses', 'solo-estate' ), __( 'Statuses', 'solo-estate' ), $cap, 'solo-estate-statuses', array( Statuses_Page::class, 'render' ) );
		add_submenu_page( 'solo-estate', __( 'Specification fields', 'solo-estate' ), __( 'Specification', 'solo-estate' ), $cap, 'solo-estate-specs', array( Specs_Page::class, 'render' ) );
		add_submenu_page( 'solo-estate', __( 'Leads', 'solo-estate' ), __( 'Leads', 'solo-estate' ) . Leads_Page::menu_badge(), $sell, 'solo-estate-leads', array( Leads_Page::class, 'render' ) );
		add_submenu_page( 'solo-estate', __( 'Import / Export', 'solo-estate' ), __( 'Import / Export', 'solo-estate' ), $cap, 'solo-estate-transfer', array( Transfer_Page::class, 'render' ) );
		add_submenu_page( 'solo-estate', __( 'Settings', 'solo-estate' ), __( 'Settings', 'solo-estate' ), $cap, 'solo-estate-settings', array( Settings_Page::class, 'render' ) );
	}

	/**
	 * Admin assets on Solo Estate screens only.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'solo-estate' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'solo-estate-admin', SOLO_ESTATE_URL . 'assets/css/admin.css', array(), \SoloEstate\Plugin::asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'solo-estate-polygon-editor', SOLO_ESTATE_URL . 'assets/js/polygon-editor.js', array( 'wp-a11y' ), \SoloEstate\Plugin::asset_version( 'assets/js/polygon-editor.js' ), true );
		wp_enqueue_script( 'solo-estate-admin', SOLO_ESTATE_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable', 'wp-a11y', 'solo-estate-polygon-editor' ), \SoloEstate\Plugin::asset_version( 'assets/js/admin.js' ), true );
		wp_localize_script(
			'solo-estate-admin',
			'soloEstateAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'currency' => (string) \SoloEstate\Settings::get( 'base_currency' ),
				'i18n'     => array(
					'chooseImage'   => __( 'Choose image', 'solo-estate' ),
					'useImage'      => __( 'Use this image', 'solo-estate' ),
					'chooseImages'  => __( 'Choose images', 'solo-estate' ),
					'addImages'     => __( 'Add to gallery', 'solo-estate' ),
					'remove'        => __( 'Remove', 'solo-estate' ),
					'moveEarlier'   => __( 'Move earlier', 'solo-estate' ),
					'moveLater'     => __( 'Move later', 'solo-estate' ),
					/* translators: 1: position, 2: number of items */
					'moved'         => __( 'Moved to position %1$d of %2$d.', 'solo-estate' ),
					/* translators: 1: point number, 2: number of points, 3: x, 4: y */
					'polyPoint'     => __( 'Point %1$d of %2$d: %3$d, %4$d.', 'solo-estate' ),
					/* translators: %1$d: number of points */
					'polyPoints'    => __( 'Outline with %1$d points.', 'solo-estate' ),
					'polyEditor'    => __( 'Outline editor', 'solo-estate' ),
					'confirmDelete' => __( 'Delete permanently? Everything inside will be deleted too.', 'solo-estate' ),
					'confirmTrash'  => __( 'Delete? Everything inside goes too. It stays in the trash for 30 days and can be restored.', 'solo-estate' ),
					'confirmClear'  => __( 'Remove all points of this polygon?', 'solo-estate' ),
				),
			)
		);
	}

	/**
	 * Result notices passed through the `solo_estate_msg` query arg after redirects.
	 */
	public static function notices() {
		$install = get_transient( \SoloEstate\Install::ERROR_TRANSIENT );
		if ( is_array( $install ) && self::can_manage() ) {
			/* translators: %s: database error */
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( sprintf( __( 'Solo Estate could not update its database tables: %s. It tries again in an hour; ask your host if the database user may create and alter tables.', 'solo-estate' ), $install[0] ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = isset( $_GET['solo_estate_msg'] ) ? sanitize_key( wp_unslash( $_GET['solo_estate_msg'] ) ) : '';
		if ( '' === $msg ) {
			return;
		}
		$messages = array(
			'saved'      => array( 'success', __( 'Saved.', 'solo-estate' ) ),
			'deleted'    => array( 'success', __( 'Deleted.', 'solo-estate' ) ),
			'duplicated' => array( 'success', __( 'Duplicated. You are now editing the copy.', 'solo-estate' ) ),
			'duplicated_partial' => array( 'warning', __( 'Duplicated, but some items inside could not be copied. Check the copy.', 'solo-estate' ) ),
			'updated'    => array( 'success', __( 'Updated.', 'solo-estate' ) ),
			'error'      => array( 'error', __( 'Something went wrong. Nothing was changed.', 'solo-estate' ) ),
			'style_imported' => array( 'success', __( 'Style imported.', 'solo-estate' ) ),
			'style_invalid'  => array( 'error', __( 'This is not a Solo Estate style file. Nothing was changed.', 'solo-estate' ) ),
			'trashed'        => array( 'success', __( 'Moved to the trash. It can be restored from Projects → Trash for 30 days.', 'solo-estate' ) ),
			'restored'       => array( 'success', __( 'Restored.', 'solo-estate' ) ),
			'restore_parent' => array( 'error', __( 'Its parent no longer exists. Restore the parent first.', 'solo-estate' ) ),
			'status_in_use'  => array( 'error', __( 'Choose the status its items get; nothing was deleted.', 'solo-estate' ) ),
		);
		if ( 'layout' === $msg ) {
			$count = isset( $_GET['solo_estate_count'] ) ? absint( $_GET['solo_estate_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			/* translators: %d: number of apartments */
			$messages['layout'] = array( $count ? 'success' : 'warning', sprintf( _n( 'Layout applied to %d apartment.', 'Layout applied to %d apartments.', $count, 'solo-estate' ), $count ) );
		}
		if ( 'saved_warnings' === $msg ) {
			$list = get_transient( 'solo_estate_settings_warnings_' . get_current_user_id() );
			delete_transient( 'solo_estate_settings_warnings_' . get_current_user_id() );
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Saved, but please check:', 'solo-estate' ) . '</p><ul class="ul-disc">';
			foreach ( (array) $list as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul></div>';
			return;
		}
		if ( isset( $messages[ $msg ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $msg ][0] ), esc_html( $messages[ $msg ][1] ) );
		}
	}

	/**
	 * Verifies capability and nonce for an admin-post action; dies otherwise.
	 *
	 * @param string $action Nonce action.
	 * @param string $cap    Required capability (manager by default).
	 */
	public static function check( $action, $cap = Install::CAPABILITY ) {
		if ( ! current_user_can( $cap ) ) {
			self::deny();
		}
		check_admin_referer( $action );
	}

	/**
	 * Stops with "not allowed".
	 */
	public static function deny() {
		wp_die( esc_html__( 'You are not allowed to do this.', 'solo-estate' ), 403 );
	}

	/**
	 * Manager: everything, including deleting, statuses, settings and import.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( Install::CAPABILITY );
	}

	/**
	 * Site administrators (who can change user roles anyway), not Solo Estate managers: only
	 * they see Settings → Access (role levels) and Advanced (delete data on uninstall).
	 *
	 * @return bool
	 */
	public static function is_administrator() {
		return current_user_can( 'manage_options' ) && current_user_can( 'promote_users' );
	}

	/**
	 * Marketing and managers: add and change content (never delete).
	 *
	 * @return bool
	 */
	public static function can_edit() {
		return current_user_can( Install::CAP_EDIT ) || self::can_manage();
	}

	/**
	 * Sales and up: change statuses.
	 *
	 * @return bool
	 */
	public static function can_sell() {
		return current_user_can( Install::CAP_SELL ) || self::can_edit();
	}

	/**
	 * Redirects back with a notice and exits.
	 *
	 * @param string $url URL.
	 * @param string $msg Message key.
	 */
	public static function redirect( $url, $msg = 'saved' ) {
		wp_safe_redirect( add_query_arg( 'solo_estate_msg', $msg, $url ) );
		exit;
	}

	/**
	 * URL of an Solo Estate admin page.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Query args.
	 * @return string
	 */
	public static function url( $page = 'solo-estate', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}
}
