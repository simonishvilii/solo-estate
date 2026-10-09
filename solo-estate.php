<?php
/**
 * Plugin Name:       Solo Estate
 * Plugin URI:        https://solostudio.ge
 * Author URI:        https://solostudio.ge
 * Description:       Interactive project → building → floor → apartment selector with polygon maps, prices, specifications and lead capture. Everything is managed from wp-admin.
 * Version:           3.8.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Solo Studio
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       solo-estate
 * Domain Path:       /languages
 *
 * @package SoloEstate
 */

defined( 'ABSPATH' ) || exit;

define( 'SOLO_ESTATE_VERSION', '3.8.0' );
define( 'SOLO_ESTATE_DB_VERSION', '11' );
define( 'SOLO_ESTATE_FILE', __FILE__ );
define( 'SOLO_ESTATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOLO_ESTATE_URL', plugin_dir_url( __FILE__ ) );

require_once SOLO_ESTATE_DIR . 'includes/autoload.php';

register_activation_hook( __FILE__, array( 'SoloEstate\\Install', 'activate' ) );
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'solo_estate_daily' );
		wp_clear_scheduled_hook( 'solo_estate_refresh_rate' );
	}
);

add_action( 'plugins_loaded', array( 'SoloEstate\\Plugin', 'instance' ) );
