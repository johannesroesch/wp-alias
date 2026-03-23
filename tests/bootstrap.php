<?php
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

// Plugin-Klassen laden (ohne WordPress-Bootstrap)
require_once dirname(__DIR__) . '/includes/class-alias-db.php';
require_once dirname(__DIR__) . '/includes/class-alias-redirector.php';
require_once dirname(__DIR__) . '/admin/class-alias-admin.php';
