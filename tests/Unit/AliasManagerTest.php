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

/**
 * Tests für alias-manager.php (Plugin-Einstiegspunkt).
 *
 * @runInSeparateProcess lädt die Datei in einem frischen Prozess,
 * damit define() und require_once ungestört ausgeführt werden können.
 */
final class AliasManagerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_bootstrap_registers_activation_hook_and_init_action(): void
    {
        Functions\when('plugin_dir_path')->justReturn(dirname(__DIR__, 2) . '/');
        Functions\when('add_action')->justReturn(null);
        Functions\when('is_admin')->justReturn(false);

        Functions\expect('register_activation_hook')
            ->once()
            ->with(\Mockery::type('string'), ['Alias_Manager_DB', 'create_table']);

        require_once dirname(__DIR__, 2) . '/alias-manager.php';
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_bootstrap_registers_plugins_loaded_hook_when_admin(): void
    {
        Functions\when('plugin_dir_path')->justReturn(dirname(__DIR__, 2) . '/');
        Functions\when('register_activation_hook')->justReturn(null);
        Functions\when('is_admin')->justReturn(true);

        Functions\expect('add_action')
            ->once()
            ->with('init', ['Alias_Manager_Redirector', 'maybe_redirect']);

        Functions\expect('add_action')
            ->once()
            ->with('plugins_loaded', ['Alias_Manager_Admin', 'init']);

        require_once dirname(__DIR__, 2) . '/alias-manager.php';
    }
}
