<?php
/**
 * Plugin Name: Alias Manager
 * Description: Verwalte Aliase für WordPress-Seiten – jeder Alias leitet per 301-Redirect auf eine hinterlegte Zielseite weiter.
 * Version:     1.0.0
 * Author:      Johannes Rösch
 * Text Domain: alias-manager
 * License:     GPL-2.0+
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

if ( is_admin() ) {
    add_action( 'plugins_loaded', array( 'Alias_Manager_Admin', 'init' ) );
}
