<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Alias_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu() {
        add_options_page(
            __( 'WP Alias', 'wp-alias' ),
            __( 'WP Alias', 'wp-alias' ),
            'manage_options',
            'wp-alias',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $notice  = '';
        $editing = null;

        // Alias löschen
        if (
            isset( $_GET['action'], $_GET['id'], $_GET['_wpnonce'] )
            && 'delete' === $_GET['action']
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_alias_delete_' . (int) $_GET['id'] )
        ) {
            WP_Alias_DB::delete( (int) $_GET['id'] );
            $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alias gelöscht.', 'wp-alias' ) . '</p></div>';
        }

        // Bearbeiten vorbereiten
        if ( isset( $_GET['action'], $_GET['id'] ) && $_GET['action'] === 'edit' ) {
            $editing = WP_Alias_DB::get( (int) $_GET['id'] );
        }

        // Formular speichern (Neu oder Aktualisieren)
        if ( isset( $_POST['wp_alias_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_alias_nonce'] ) ), 'wp_alias_save' ) ) {
            $alias      = trim( sanitize_text_field( wp_unslash( $_POST['alias'] ?? '' ) ), '/' );
            $target_url = esc_url_raw( wp_unslash( $_POST['target_url'] ?? '' ) );
            $edit_id    = (int) wp_unslash( $_POST['edit_id'] ?? 0 );

            if ( $alias === '' || $target_url === '' ) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Alias und Ziel-URL dürfen nicht leer sein.', 'wp-alias' ) . '</p></div>';
            } else {
                if ( $edit_id > 0 ) {
                    $result = WP_Alias_DB::update( $edit_id, $alias, $target_url );
                    $notice = $result !== false
                        ? '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alias aktualisiert.', 'wp-alias' ) . '</p></div>'
                        : '<div class="notice notice-error"><p>' . esc_html__( 'Fehler beim Aktualisieren. Alias-Slug möglicherweise bereits vergeben.', 'wp-alias' ) . '</p></div>';
                } else {
                    $result = WP_Alias_DB::insert( $alias, $target_url );
                    $notice = $result
                        ? '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alias angelegt.', 'wp-alias' ) . '</p></div>'
                        : '<div class="notice notice-error"><p>' . esc_html__( 'Fehler: Alias-Slug bereits vergeben oder ungültige Eingabe.', 'wp-alias' ) . '</p></div>';
                }
                $editing = null;
            }
        }

        $aliases = WP_Alias_DB::all();
        $pages   = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'menu_order,post_title' ) );
        $home    = home_url( '/' );

        $form_alias      = $editing ? $editing->alias : '';
        $form_target_url = $editing ? $editing->target_url : '';
        $form_edit_id    = $editing ? (int) $editing->id : 0;
        $form_button     = $editing ? __( 'Alias aktualisieren', 'wp-alias' ) : __( 'Alias anlegen', 'wp-alias' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WP Alias', 'wp-alias' ); ?></h1>
            <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

            <div class="card" style="max-width:640px;padding:16px 20px;margin-bottom:24px;">
                <h2 style="margin-top:0;"><?php echo $editing ? esc_html__( 'Alias bearbeiten', 'wp-alias' ) : esc_html__( 'Neuen Alias anlegen', 'wp-alias' ); ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'wp_alias_save', 'wp_alias_nonce' ); ?>
                    <input type="hidden" name="edit_id" value="<?php echo (int) $form_edit_id; ?>">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="alias"><?php esc_html_e( 'Alias-Pfad', 'wp-alias' ); ?></label></th>
                            <td>
                                <span><?php echo esc_html( $home ); ?></span>
                                <input type="text" id="alias" name="alias" value="<?php echo esc_attr( $form_alias ); ?>"
                                    placeholder="aliasA" class="regular-text" required>
                                <p class="description"><?php esc_html_e( 'Nur den Slug eingeben, z. B. "aliasA" für example.com/aliasA', 'wp-alias' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="page_select"><?php esc_html_e( 'Seite auswählen', 'wp-alias' ); ?></label></th>
                            <td>
                                <select id="page_select">
                                    <option value=""><?php esc_html_e( '— Seite wählen —', 'wp-alias' ); ?></option>
                                    <?php foreach ( $pages as $page ) : ?>
                                        <option value="<?php echo esc_attr( get_permalink( $page->ID ) ); ?>">
                                            <?php echo esc_html( $page->post_title ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Füllt das Ziel-URL-Feld automatisch aus.', 'wp-alias' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="target_url"><?php esc_html_e( 'Ziel-URL', 'wp-alias' ); ?></label></th>
                            <td>
                                <input type="url" id="target_url" name="target_url" value="<?php echo esc_attr( $form_target_url ); ?>"
                                    placeholder="https://example.com/pageA" class="large-text" required>
                                <p class="description"><?php esc_html_e( 'Vollständige URL des Ziels. Wird per 301-Redirect weitergeleitet.', 'wp-alias' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( $form_button, 'primary', 'submit', false ); ?>
                    <?php if ( $editing ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-alias' ) ); ?>" class="button" style="margin-left:8px;">
                            <?php esc_html_e( 'Abbrechen', 'wp-alias' ); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <h2><?php esc_html_e( 'Vorhandene Aliase', 'wp-alias' ); ?></h2>

            <?php if ( empty( $aliases ) ) : ?>
                <p><?php esc_html_e( 'Noch keine Aliase angelegt.', 'wp-alias' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Alias-Pfad', 'wp-alias' ); ?></th>
                            <th><?php esc_html_e( 'Ziel-URL', 'wp-alias' ); ?></th>
                            <th><?php esc_html_e( 'Erstellt am', 'wp-alias' ); ?></th>
                            <th><?php esc_html_e( 'Aktionen', 'wp-alias' ); ?></th>
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
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-alias', 'action' => 'edit', 'id' => $row->id ), admin_url( 'options-general.php' ) ) ); ?>">
                                        <?php esc_html_e( 'Bearbeiten', 'wp-alias' ); ?>
                                    </a>
                                    &nbsp;|&nbsp;
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'wp-alias', 'action' => 'delete', 'id' => $row->id ), admin_url( 'options-general.php' ) ), 'wp_alias_delete_' . $row->id ) ); ?>"
                                        onclick="return confirm('<?php esc_attr_e( 'Alias wirklich löschen?', 'wp-alias' ); ?>');"
                                        style="color:#b32d2e;">
                                        <?php esc_html_e( 'Löschen', 'wp-alias' ); ?>
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
