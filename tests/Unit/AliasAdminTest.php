<?php
/**
 * Unit tests for Alias Manager.
 *
 * @package   Alias_Manager
 * @copyright 2025 Johannes Rösch
 * @license   GPL-2.0+
 */
declare(strict_types=1);

namespace WPAlias\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Alias_Manager_Admin;

/**
 * Tests für Alias_Manager_Admin.
 */
final class AliasAdminTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var \Mockery\MockInterface */
    private $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->wpdb         = \Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride
        $GLOBALS['wpdb'] = $this->wpdb;

        $_GET  = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET  = [];
        $_POST = [];
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Hilfsmethoden
    // -------------------------------------------------------------------------

    /**
     * Mockt alle WP-Ausgabefunktionen, die render_page() für das HTML-Rendering
     * benötigt. Ermöglicht Tests, die sich auf das Verhalten konzentrieren.
     */
    private function setupRenderMocks(array $aliases = []): void
    {
        Functions\when('esc_html__')->returnArg();
        Functions\when('__')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html_e')->alias(static function (string $t): void { echo $t; });
        Functions\when('esc_attr_e')->alias(static function (string $t): void { echo $t; });
        Functions\when('home_url')->justReturn('https://example.com/');
        Functions\when('get_pages')->justReturn([]);
        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('submit_button')->justReturn('');
        Functions\when('admin_url')->justReturn('options-general.php');
        Functions\when('add_query_arg')->justReturn('options-general.php?page=alias-manager');
        Functions\when('wp_nonce_url')->justReturn('options-general.php?_wpnonce=abc');
        Functions\when('get_option')->justReturn('Y-m-d');
        Functions\when('date_i18n')->justReturn('2025-01-01');
        Functions\when('get_permalink')->justReturn('https://example.com/page');
        Functions\when('wp_unslash')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();

        $this->wpdb->shouldReceive('get_results')->andReturn($aliases)->byDefault();
    }

    // -------------------------------------------------------------------------
    // init() / Hook-Registrierung
    // -------------------------------------------------------------------------

    public function test_init_registers_admin_menu_hook(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('admin_menu', [\Alias_Manager_Admin::class, 'register_menu']);

        Alias_Manager_Admin::init();
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // register_menu()
    // -------------------------------------------------------------------------

    public function test_register_menu_adds_options_page(): void
    {
        Functions\expect('__')
            ->zeroOrMoreTimes()
            ->andReturnFirstArg();

        Functions\expect('add_options_page')
            ->once()
            ->with(
                'Alias Manager',
                'Alias Manager',
                'manage_options',
                'alias-manager',
                [\Alias_Manager_Admin::class, 'render_page']
            );

        Alias_Manager_Admin::register_menu();
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // render_page() – Capability-Check
    // -------------------------------------------------------------------------

    public function test_render_page_exits_silently_without_manage_options(): void
    {
        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        Functions\expect('wp_nonce_field')->never();

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    // -------------------------------------------------------------------------
    // render_page() – Delete-Aktion
    // -------------------------------------------------------------------------

    public function test_render_page_delete_with_valid_nonce_calls_delete(): void
    {
        $_GET = ['action' => 'delete', 'id' => '5', '_wpnonce' => 'valid_nonce'];

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('wp_verify_nonce')->once()->andReturn(true);

        $this->wpdb
            ->shouldReceive('delete')
            ->once()
            ->with('wp_aliases', ['id' => 5], ['%d'])
            ->andReturn(1);

        $this->setupRenderMocks();

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('Alias deleted.', $output);
    }

    public function test_render_page_delete_with_invalid_nonce_skips_delete(): void
    {
        $_GET = ['action' => 'delete', 'id' => '5', '_wpnonce' => 'bad_nonce'];

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('wp_verify_nonce')->once()->andReturn(false);

        $this->wpdb->shouldReceive('delete')->never();

        $this->setupRenderMocks();

        ob_start();
        Alias_Manager_Admin::render_page();
        ob_get_clean();

        // Kein Fehler = Test bestanden
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // render_page() – Edit-Aktion
    // -------------------------------------------------------------------------

    public function test_render_page_edit_action_loads_row(): void
    {
        $_GET = ['action' => 'edit', 'id' => '3'];

        $row = (object) ['id' => 3, 'alias' => 'my-alias', 'target_url' => 'https://example.com/page'];

        Functions\expect('current_user_can')->once()->andReturn(true);

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->andReturn('SELECT * FROM wp_aliases WHERE id = 3');

        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->andReturn($row);

        $this->setupRenderMocks();

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('Edit Alias', $output);
    }

    // -------------------------------------------------------------------------
    // render_page() – Save: neuer Alias
    // -------------------------------------------------------------------------

    public function test_render_page_trims_slashes_from_alias_before_save(): void
    {
        $_POST = [
            'alias_manager_nonce' => 'valid',
            'alias'               => '/summer-sale/',
            'target_url'          => 'https://example.com/shop',
            'edit_id'             => '0',
        ];

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('wp_verify_nonce')->once()->andReturn(true);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('absint')->alias('intval');

        // Slashes müssen vor dem Speichern entfernt werden: '/summer-sale/' → 'summer-sale'
        $this->wpdb
            ->shouldReceive('insert')
            ->once()
            ->with(
                'wp_aliases',
                ['alias' => 'summer-sale', 'target_url' => 'https://example.com/shop'],
                ['%s', '%s']
            )
            ->andReturn(1);

        $this->setupRenderMocks();

        ob_start();
        Alias_Manager_Admin::render_page();
        ob_get_clean();
    }

    public function test_render_page_save_new_alias_success(): void
    {
        $_POST = [
            'alias_manager_nonce' => 'valid',
            'alias'               => 'summer-sale',
            'target_url'          => 'https://example.com/shop/summer',
            'edit_id'             => '0',
        ];

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('wp_verify_nonce')->once()->andReturn(true);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('absint')->alias('intval');

        $this->wpdb
            ->shouldReceive('insert')
            ->once()
            ->andReturn(1);

        $this->setupRenderMocks();

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('Alias added.', $output);
    }

    public function test_render_page_save_new_alias_duplicate_shows_error(): void
    {
        $_POST = [
            'alias_manager_nonce' => 'valid',
            'alias'               => 'duplicate',
            'target_url'          => 'https://example.com/page',
            'edit_id'             => '0',
        ];

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('wp_verify_nonce')->once()->andReturn(true);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('absint')->alias('intval');

        $this->wpdb
            ->shouldReceive('insert')
            ->once()
            ->andReturn(false);

        $this->setupRenderMocks();

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('Error: Alias slug already in use', $output);
    }

    public function test_render_page_save_empty_alias_shows_error(): void
    {
        $_POST = [
            'alias_manager_nonce' => 'valid',
            'alias'               => '',
            'target_url'          => 'https://example.com/page',
            'edit_id'             => '0',
        ];

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('wp_verify_nonce')->once()->andReturn(true);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('absint')->alias('intval');

        $this->wpdb->shouldReceive('insert')->never();

        $this->setupRenderMocks();

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('Alias and target URL must not be empty.', $output);
    }

    public function test_render_page_save_empty_target_shows_error(): void
    {
        $_POST = [
            'alias_manager_nonce' => 'valid',
            'alias'               => 'some-alias',
            'target_url'          => '',
            'edit_id'             => '0',
        ];

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('wp_verify_nonce')->once()->andReturn(true);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('absint')->alias('intval');

        $this->wpdb->shouldReceive('insert')->never();

        $this->setupRenderMocks();

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('Alias and target URL must not be empty.', $output);
    }

    // -------------------------------------------------------------------------
    // render_page() – Save: Update bestehender Alias
    // -------------------------------------------------------------------------

    public function test_render_page_save_update_success(): void
    {
        $_POST = [
            'alias_manager_nonce' => 'valid',
            'alias'               => 'updated-alias',
            'target_url'          => 'https://example.com/new-page',
            'edit_id'             => '7',
        ];

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('wp_verify_nonce')->once()->andReturn(true);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('absint')->alias('intval');

        $this->wpdb
            ->shouldReceive('update')
            ->once()
            ->andReturn(1);

        $this->setupRenderMocks();

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('Alias updated.', $output);
    }

    public function test_render_page_save_update_failure_shows_error(): void
    {
        $_POST = [
            'alias_manager_nonce' => 'valid',
            'alias'               => 'taken-alias',
            'target_url'          => 'https://example.com/page',
            'edit_id'             => '7',
        ];

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('wp_verify_nonce')->once()->andReturn(true);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('absint')->alias('intval');

        $this->wpdb
            ->shouldReceive('update')
            ->once()
            ->andReturn(false);

        $this->setupRenderMocks();

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('Update failed.', $output);
    }

    // -------------------------------------------------------------------------
    // render_page() – Tabelle mit und ohne Aliases
    // -------------------------------------------------------------------------

    public function test_render_page_renders_page_options_when_pages_exist(): void
    {
        $page             = new \stdClass();
        $page->ID         = 42;
        $page->post_title = 'Sample Page';

        Functions\expect('current_user_can')->once()->andReturn(true);

        // Override get_pages to return a page object
        Functions\when('esc_html__')->returnArg();
        Functions\when('__')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html_e')->alias(static function (string $t): void { echo $t; });
        Functions\when('esc_attr_e')->alias(static function (string $t): void { echo $t; });
        Functions\when('home_url')->justReturn('https://example.com/');
        Functions\when('get_pages')->justReturn([$page]);
        Functions\when('get_permalink')->justReturn('https://example.com/sample-page');
        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('submit_button')->justReturn('');
        Functions\when('admin_url')->justReturn('options-general.php');
        Functions\when('add_query_arg')->justReturn('options-general.php?page=alias-manager');
        Functions\when('wp_nonce_url')->justReturn('options-general.php?_wpnonce=abc');
        Functions\when('get_option')->justReturn('Y-m-d');
        Functions\when('date_i18n')->justReturn('2025-01-01');
        Functions\when('wp_unslash')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();

        $this->wpdb->shouldReceive('get_results')->andReturn([]);

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('Sample Page', $output);
        $this->assertStringContainsString('https://example.com/sample-page', $output);
    }

    public function test_render_page_shows_empty_message_when_no_aliases(): void
    {
        Functions\expect('current_user_can')->once()->andReturn(true);
        $this->setupRenderMocks([]);

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('No aliases have been added yet.', $output);
    }

    public function test_render_page_shows_alias_table_when_aliases_exist(): void
    {
        $row            = new \stdClass();
        $row->id        = 1;
        $row->alias     = 'summer-sale';
        $row->target_url = 'https://example.com/shop/summer';
        $row->created_at = '2025-01-01 00:00:00';

        Functions\expect('current_user_can')->once()->andReturn(true);
        $this->setupRenderMocks([$row]);

        ob_start();
        Alias_Manager_Admin::render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('summer-sale', $output);
        $this->assertStringContainsString('https://example.com/shop/summer', $output);
    }
}
