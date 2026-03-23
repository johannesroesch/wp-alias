<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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

        $notice  = '';
        $editing = null;

        // Alias löschen
        if (
            isset( $_GET['action'], $_GET['id'], $_GET['_wpnonce'] )
            && 'delete' === $_GET['action']
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'alias_manager_delete_' . (int) $_GET['id'] )
        ) {
            Alias_Manager_DB::delete( (int) $_GET['id'] );
            $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alias gelöscht.', 'alias-manager' ) . '</p></div>';
        }

        // Bearbeiten vorbereiten
        if ( isset( $_GET['action'], $_GET['id'] ) && 'edit' === $_GET['action'] ) {
            $editing = Alias_Manager_DB::get( (int) $_GET['id'] );
        }

        // Formular speichern (Neu oder Aktualisieren)
        if ( isset( $_POST['alias_manager_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alias_manager_nonce'] ) ), 'alias_manager_save' ) ) {
            $alias      = trim( sanitize_text_field( wp_unslash( $_POST['alias'] ?? '' ) ), '/' );
            $target_url = esc_url_raw( wp_unslash( $_POST['target_url'] ?? '' ) );
            $edit_id    = absint( wp_unslash( $_POST['edit_id'] ?? 0 ) );

            if ( $alias === '' || $target_url === '' ) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Alias und Ziel-URL dürfen nicht leer sein.', 'alias-manager' ) . '</p></div>';
            } else {
                if ( $edit_id > 0 ) {
                    $result = Alias_Manager_DB::update( $edit_id, $alias, $target_url );
                    $notice = $result !== false
                        ? '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alias aktualisiert.', 'alias-manager' ) . '</p></div>'
                        : '<div class="notice notice-error"><p>' . esc_html__( 'Fehler beim Aktualisieren. Alias-Slug möglicherweise bereits vergeben.', 'alias-manager' ) . '</p></div>';
                } else {
                    $result = Alias_Manager_DB::insert( $alias, $target_url );
                    $notice = $result
                        ? '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alias angelegt.', 'alias-manager' ) . '</p></div>'
                        : '<div class="notice notice-error"><p>' . esc_html__( 'Fehler: Alias-Slug bereits vergeben oder ungültige Eingabe.', 'alias-manager' ) . '</p></div>';
                }
                $editing = null;
            }
        }

        $aliases = Alias_Manager_DB::all();
        $pages   = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'menu_order,post_title' ) );
        $home    = home_url( '/' );

        $form_alias      = $editing ? $editing->alias : '';
        $form_target_url = $editing ? $editing->target_url : '';
        $form_edit_id    = $editing ? (int) $editing->id : 0;
        $form_button     = $editing ? __( 'Alias aktualisieren', 'alias-manager' ) : __( 'Alias anlegen', 'alias-manager' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Alias Manager', 'alias-manager' ); ?></h1>
            <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

            <div class="card" style="max-width:640px;padding:16px 20px;margin-bottom:24px;">
                <h2 style="margin-top:0;"><?php echo $editing ? esc_html__( 'Alias bearbeiten', 'alias-manager' ) : esc_html__( 'Neuen Alias anlegen', 'alias-manager' ); ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'alias_manager_save', 'alias_manager_nonce' ); ?>
                    <input type="hidden" name="edit_id" value="<?php echo (int) $form_edit_id; ?>">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="alias"><?php esc_html_e( 'Alias-Pfad', 'alias-manager' ); ?></label></th>
                            <td>
                                <span><?php echo esc_html( $home ); ?></span>
                                <input type="text" id="alias" name="alias" value="<?php echo esc_attr( $form_alias ); ?>"
                                    placeholder="aliasA" class="regular-text" required>
                                <p class="description"><?php esc_html_e( 'Nur den Slug eingeben, z. B. "aliasA" für example.com/aliasA', 'alias-manager' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="page_select"><?php esc_html_e( 'Seite auswählen', 'alias-manager' ); ?></label></th>
                            <td>
                                <select id="page_select">
                                    <option value=""><?php esc_html_e( '— Seite wählen —', 'alias-manager' ); ?></option>
                                    <?php foreach ( $pages as $page ) : ?>
                                        <option value="<?php echo esc_attr( get_permalink( $page->ID ) ); ?>">
                                            <?php echo esc_html( $page->post_title ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Füllt das Ziel-URL-Feld automatisch aus.', 'alias-manager' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="target_url"><?php esc_html_e( 'Ziel-URL', 'alias-manager' ); ?></label></th>
                            <td>
                                <input type="url" id="target_url" name="target_url" value="<?php echo esc_attr( $form_target_url ); ?>"
                                    placeholder="https://example.com/pageA" class="large-text" required>
                                <p class="description"><?php esc_html_e( 'Vollständige URL des Ziels. Wird per 301-Redirect weitergeleitet.', 'alias-manager' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( $form_button, 'primary', 'submit', false ); ?>
                    <?php if ( $editing ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=alias-manager' ) ); ?>" class="button" style="margin-left:8px;">
                            <?php esc_html_e( 'Abbrechen', 'alias-manager' ); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <h2><?php esc_html_e( 'Vorhandene Aliase', 'alias-manager' ); ?></h2>

            <?php if ( empty( $aliases ) ) : ?>
                <p><?php esc_html_e( 'Noch keine Aliase angelegt.', 'alias-manager' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Alias-Pfad', 'alias-manager' ); ?></th>
                            <th><?php esc_html_e( 'Ziel-URL', 'alias-manager' ); ?></th>
                            <th><?php esc_html_e( 'Erstellt am', 'alias-manager' ); ?></th>
                            <th><?php esc_html_e( 'Aktionen', 'alias-manager' ); ?></th>
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
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'alias-manager', 'action' => 'edit', 'id' => $row->id ), admin_url( 'options-general.php' ) ) ); ?>">
                                        <?php esc_html_e( 'Bearbeiten', 'alias-manager' ); ?>
                                    </a>
                                    &nbsp;|&nbsp;
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'alias-manager', 'action' => 'delete', 'id' => $row->id ), admin_url( 'options-general.php' ) ), 'alias_manager_delete_' . $row->id ) ); ?>"
                                        onclick="return confirm('<?php esc_attr_e( 'Alias wirklich löschen?', 'alias-manager' ); ?>');"
                                        style="color:#b32d2e;">
                                        <?php esc_html_e( 'Löschen', 'alias-manager' ); ?>
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
