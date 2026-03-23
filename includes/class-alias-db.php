<?php
/**
 * Database layer for Alias Manager.
 *
 * @package   Alias_Manager
 * @copyright 2025 Johannes Rösch
 * @license   GPL-2.0+
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // @codeCoverageIgnore
}

class Alias_Manager_DB {

    const TABLE_NAME = 'aliases';

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'aliases';
    }

    public static function create_table() {
        global $wpdb;
        $table           = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id         mediumint(9)  NOT NULL AUTO_INCREMENT,
            alias      varchar(255)  NOT NULL,
            target_url varchar(2000) NOT NULL,
            created_at datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY alias (alias)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function all() {
        global $wpdb;
        $table = esc_sql( self::table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; table name is escaped and generated internally.
        return $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY alias ASC" );
    }

    public static function get( $id ) {
        global $wpdb;
        $table = esc_sql( self::table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; table name is escaped and generated internally.
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) );
    }

    public static function find_by_alias( $alias ) {
        global $wpdb;
        $table = esc_sql( self::table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; table name is escaped and generated internally.
        return $wpdb->get_var( $wpdb->prepare( "SELECT target_url FROM `{$table}` WHERE alias = %s", $alias ) );
    }

    public static function insert( $alias, $target_url ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
        return $wpdb->insert(
            self::table(),
            self::sanitize_data( $alias, $target_url ),
            array( '%s', '%s' )
        );
    }

    public static function update( $id, $alias, $target_url ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
        return $wpdb->update(
            self::table(),
            self::sanitize_data( $alias, $target_url ),
            array( 'id' => (int) $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    private static function sanitize_data( string $alias, string $target_url ): array {
        return array(
            'alias'      => sanitize_text_field( $alias ),
            'target_url' => esc_url_raw( $target_url ),
        );
    }

    public static function delete( $id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
        return $wpdb->delete(
            self::table(),
            array( 'id' => (int) $id ),
            array( '%d' )
        );
    }
}
