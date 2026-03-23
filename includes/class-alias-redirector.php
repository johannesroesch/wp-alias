<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Alias_Manager_Redirector {

    public static function maybe_redirect() {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        $request_uri  = isset( $_SERVER['REQUEST_URI'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            ? wp_unslash( $_SERVER['REQUEST_URI'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : '';
        $request_path = trim( wp_parse_url( $request_uri, PHP_URL_PATH ) ?? '', '/' );

        // WordPress in Unterverzeichnis: Basis-Pfad entfernen
        $home_path = trim( wp_parse_url( home_url(), PHP_URL_PATH ) ?? '', '/' );
        if ( $home_path && str_starts_with( $request_path, $home_path ) ) {
            $request_path = trim( substr( $request_path, strlen( $home_path ) ), '/' );
        }

        if ( $request_path === '' ) {
            return;
        }

        $target = Alias_Manager_DB::find_by_alias( $request_path );

        if ( $target ) {
            // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Target URL is admin-controlled and stored in the database; external redirects are intentional.
            wp_redirect( $target, 301 );
            exit;
        }
    }
}
