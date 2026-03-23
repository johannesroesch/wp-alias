<?php
/**
 * PHPUnit bootstrap for Alias Manager tests.
 *
 * @package   Alias_Manager
 * @copyright 2025 Johannes Rösch
 * @license   GPL-2.0+
 */
declare(strict_types=1);

// WordPress-Konstanten simulieren (kein WP-Core nötig)
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Globale WordPress-Funktionen stubben, die von den Klassen direkt aufgerufen
// werden (außerhalb von Brain\Monkey-Testmethoden).
if ( ! function_exists( 'esc_sql' ) ) {
    function esc_sql( $data ) { return $data; }
}

// dbDelta-Stub: verhindert require_once auf wp-admin/includes/upgrade.php in Tests.
// Speichert das SQL in $GLOBALS für Assertions in Tests.
if ( ! function_exists( 'dbDelta' ) ) {
    function dbDelta( $sql ) {
        $GLOBALS['_last_db_delta_sql'] = $sql;
        return [];
    }
}

// Fake upgrade.php, damit create_table() den require_once durchläuft ohne Fehler.
$_upgradeDir = ABSPATH . 'wp-admin/includes';
if ( ! is_dir( $_upgradeDir ) ) {
    mkdir( $_upgradeDir, 0755, true );
}
$_upgradeFile = $_upgradeDir . '/upgrade.php';
if ( ! file_exists( $_upgradeFile ) ) {
    file_put_contents( $_upgradeFile, '<?php // stub' );
}
unset( $_upgradeDir, $_upgradeFile );

// Plugin-Klassen laden (ohne WordPress-Bootstrap)
require_once dirname(__DIR__) . '/includes/class-alias-db.php';
require_once dirname(__DIR__) . '/includes/class-alias-redirector.php';
require_once dirname(__DIR__) . '/admin/class-alias-admin.php';
