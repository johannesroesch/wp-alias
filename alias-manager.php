<?php
/**
 * Plugin Name: Alias Manager
 * Description: Manage URL aliases for WordPress pages with automatic 301 redirects.
 * Version:     1.0.0
 * Author:      Johannes Rösch
 * Author URI:  https://github.com/johannesroesch
 * Plugin URI:  https://github.com/johannesroesch/alias-manager
 * Text Domain: alias-manager
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ALIAS_MANAGER_VERSION', '1.0.0' );
define( 'ALIAS_MANAGER_DIR', plugin_dir_path( __FILE__ ) );

require_once ALIAS_MANAGER_DIR . 'includes/class-alias-db.php';
require_once ALIAS_MANAGER_DIR . 'includes/class-alias-redirector.php';
require_once ALIAS_MANAGER_DIR . 'admin/class-alias-admin.php';

register_activation_hook( __FILE__, array( 'Alias_Manager_DB', 'create_table' ) );

add_action( 'init', array( 'Alias_Manager_Redirector', 'maybe_redirect' ) );
add_action( 'init', function () {
    load_plugin_textdomain( 'alias-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

if ( is_admin() ) {
    add_action( 'plugins_loaded', array( 'Alias_Manager_Admin', 'init' ) );
}
