<?php
/**
 * Admin UI for Alias Manager.
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

class Alias_Manager_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu() {
        add_options_page(
            __( 'Alias Manager', 'alias-manager' ),
            __( 'Alias Manager', 'alias-manager' ),
            'manage_options',
            'alias-manager',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $notice  = self::handle_request();
        $editing = self::get_editing_row();
        $data    = self::prepare_view_data( $editing );

        self::render_form( $notice, $editing, $data );
        self::render_alias_table( $data['aliases'], $data['home'] );
    }

    private static function handle_request(): string {
        if (
            isset( $_GET['action'], $_GET['id'], $_GET['_wpnonce'] )
            && 'delete' === $_GET['action']
            && self::verify_nonce( $_GET['_wpnonce'], 'alias_manager_delete_' . (int) $_GET['id'] )
        ) {
            Alias_Manager_DB::delete( (int) $_GET['id'] );
            return '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alias deleted.', 'alias-manager' ) . '</p></div>';
        }

        if ( isset( $_POST['alias_manager_nonce'] ) && self::verify_nonce( $_POST['alias_manager_nonce'], 'alias_manager_save' ) ) {
            $alias      = trim( sanitize_text_field( wp_unslash( $_POST['alias'] ?? '' ) ), '/' );
            $target_url = esc_url_raw( wp_unslash( $_POST['target_url'] ?? '' ) );
            $edit_id    = absint( wp_unslash( $_POST['edit_id'] ?? 0 ) );
            return self::handle_save( $alias, $target_url, $edit_id );
        }

        return '';
    }

    private static function handle_save( string $alias, string $target_url, int $edit_id ): string {
        if ( $alias === '' || $target_url === '' ) {
            return '<div class="notice notice-error"><p>' . esc_html__( 'Alias and target URL must not be empty.', 'alias-manager' ) . '</p></div>';
        }

        if ( $edit_id > 0 ) {
            $result = Alias_Manager_DB::update( $edit_id, $alias, $target_url );
            return $result !== false
                ? '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alias updated.', 'alias-manager' ) . '</p></div>'
                : '<div class="notice notice-error"><p>' . esc_html__( 'Update failed. The alias slug may already be in use.', 'alias-manager' ) . '</p></div>';
        }

        $result = Alias_Manager_DB::insert( $alias, $target_url );
        return $result
            ? '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alias added.', 'alias-manager' ) . '</p></div>'
            : '<div class="notice notice-error"><p>' . esc_html__( 'Error: Alias slug already in use or invalid input.', 'alias-manager' ) . '</p></div>';
    }

    private static function get_editing_row(): ?object {
        if ( isset( $_GET['action'], $_GET['id'] ) && 'edit' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return Alias_Manager_DB::get( (int) $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        return null;
    }

    private static function verify_nonce( $value, string $action ): bool {
        return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $value ) ), $action );
    }

    private static function prepare_view_data( ?object $editing ): array {
        return array(
            'aliases'         => Alias_Manager_DB::all(),
            'pages'           => get_pages(
                array(
                    'post_status' => 'publish',
                    'sort_column' => 'menu_order,post_title',
                )
            ),
            'home'            => home_url( '/' ),
            'form_alias'      => $editing ? $editing->alias : '',
            'form_target_url' => $editing ? $editing->target_url : '',
            'form_edit_id'    => $editing ? (int) $editing->id : 0,
            'form_button'     => $editing ? __( 'Update Alias', 'alias-manager' ) : __( 'Add Alias', 'alias-manager' ),
        );
    }

    private static function render_form( string $notice, ?object $editing, array $data ): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Alias Manager', 'alias-manager' ); ?></h1>
            <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

            <div class="card" style="max-width:640px;padding:16px 20px;margin-bottom:24px;">
                <h2 style="margin-top:0;"><?php echo $editing ? esc_html__( 'Edit Alias', 'alias-manager' ) : esc_html__( 'Add New Alias', 'alias-manager' ); ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'alias_manager_save', 'alias_manager_nonce' ); ?>
                    <input type="hidden" name="edit_id" value="<?php echo (int) $data['form_edit_id']; ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="alias"><?php esc_html_e( 'Alias Path', 'alias-manager' ); ?></label></th>
                            <td>
                                <span><?php echo esc_html( $data['home'] ); ?></span>
                                <input type="text" id="alias" name="alias" value="<?php echo esc_attr( $data['form_alias'] ); ?>"
                                    placeholder="my-page" class="regular-text" required>
                                <p class="description"><?php esc_html_e( 'Enter the slug only, e.g. "my-page" for example.com/my-page', 'alias-manager' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="page_select"><?php esc_html_e( 'Select Page', 'alias-manager' ); ?></label></th>
                            <td>
                                <select id="page_select">
                                    <option value=""><?php esc_html_e( '&mdash; Select a page &mdash;', 'alias-manager' ); ?></option>
                                    <?php foreach ( $data['pages'] as $page ) : ?>
                                        <option value="<?php echo esc_attr( get_permalink( $page->ID ) ); ?>">
                                            <?php echo esc_html( $page->post_title ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Automatically fills in the target URL field.', 'alias-manager' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="target_url"><?php esc_html_e( 'Target URL', 'alias-manager' ); ?></label></th>
                            <td>
                                <input type="url" id="target_url" name="target_url" value="<?php echo esc_attr( $data['form_target_url'] ); ?>"
                                    placeholder="https://example.com/my-page" class="large-text" required>
                                <p class="description"><?php esc_html_e( 'Full URL of the target. Visitors will be redirected via 301.', 'alias-manager' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( $data['form_button'], 'primary', 'submit', false ); ?>
                    <?php if ( $editing ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=alias-manager' ) ); ?>" class="button" style="margin-left:8px;">
                            <?php esc_html_e( 'Cancel', 'alias-manager' ); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        <?php
    }

    private static function render_alias_table( array $aliases, string $home ): void {
        ?>
            <h2><?php esc_html_e( 'Existing Aliases', 'alias-manager' ); ?></h2>

            <?php if ( empty( $aliases ) ) : ?>
                <p><?php esc_html_e( 'No aliases have been added yet.', 'alias-manager' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Alias Path', 'alias-manager' ); ?></th>
                            <th><?php esc_html_e( 'Target URL', 'alias-manager' ); ?></th>
                            <th><?php esc_html_e( 'Created', 'alias-manager' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'alias-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $aliases as $row ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $row->alias ); ?></strong><br>
                                    <small><?php echo esc_html( $home . $row->alias ); ?></small>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( $row->target_url ); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html( $row->target_url ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->created_at ) ) ); ?></td>
                                <td>
                                    <a href="
                                    <?php
                                    echo esc_url(
                                        add_query_arg(
                                            array(
                                                'page'   => 'alias-manager',
                                                'action' => 'edit',
                                                'id'     => $row->id,
                                            ),
                                            admin_url( 'options-general.php' )
                                        )
                                    );
                                    ?>
                                                ">
                                        <?php esc_html_e( 'Edit', 'alias-manager' ); ?>
                                    </a>
                                    &nbsp;|&nbsp;
                                    <a href="
                                    <?php
                                    echo esc_url(
                                        wp_nonce_url(
                                            add_query_arg(
                                                array(
                                                    'page' => 'alias-manager',
                                                    'action' => 'delete',
                                                    'id'   => $row->id,
                                                ),
                                                admin_url( 'options-general.php' )
                                            ),
                                            'alias_manager_delete_' . $row->id
                                        )
                                    );
                                    ?>
                                                "
                                        onclick="return confirm('<?php esc_attr_e( 'Really delete this alias?', 'alias-manager' ); ?>');"
                                        style="color:#b32d2e;">
                                        <?php esc_html_e( 'Delete', 'alias-manager' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
        document.getElementById('page_select').addEventListener('change', function () {
            var url = this.value;
            if (url) {
                document.getElementById('target_url').value = url;
            }
        });
        </script>
        <?php
    }
}
