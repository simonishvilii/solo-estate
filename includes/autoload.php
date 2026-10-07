<?php
/**
 * PSR-4-like autoloader following WordPress file naming.
 *
 * SoloEstate\Node_Repo        → includes/class-node-repo.php
 * SoloEstate\Admin\Node_Page  → includes/admin/class-node-page.php
 *
 * @package SoloEstate
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'SoloEstate\\' ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class, strlen( 'SoloEstate\\' ) ) );
		$name  = array_pop( $parts );
		$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$file  = SOLO_ESTATE_DIR . 'includes/' . $dir . 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
