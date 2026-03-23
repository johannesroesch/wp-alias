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

use Brain\Monkey\Functions;
use Alias_Manager_Redirector;

/**
 * Tests für Alias_Manager_Redirector.
 *
 * Alle WordPress-Funktionen (is_admin, wp_doing_ajax, wp_doing_cron,
 * wp_unslash, wp_parse_url, home_url, wp_redirect) werden per Brain\Monkey gemockt.
 * Der `exit`-Aufruf nach dem Redirect wird durch eine Exception im wp_redirect-Mock
 * abgefangen, damit der eigentliche PHPUnit-Prozess weiterläuft.
 */
final class AliasRedirectorTest extends WpDbTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/';

        Functions\when('wp_unslash')->returnArg();
        Functions\when('wp_parse_url')->alias('parse_url');
    }

    /**
     * Mockt alle WP-Funktionen für einen erfolgreichen Redirect-Pfad,
     * führt maybe_redirect() aus und erwartet die redirect_called-Exception.
     *
     * @param string $request_uri  Wert für $_SERVER['REQUEST_URI']
     * @param string $home_url     Rückgabe von home_url()
     * @param string $alias        Alias-Slug, mit dem prepare() aufgerufen wird
     * @param string $target_url   Rückgabe von get_var() (Redirect-Ziel)
     */
    private function assert_redirect_called(
        string $request_uri,
        string $home_url,
        string $alias,
        string $target_url
    ): void {
        $_SERVER['REQUEST_URI'] = $request_uri;

        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(false);
        Functions\expect('home_url')->once()->andReturn($home_url);

        $this->wpdb->shouldReceive('prepare')
            ->once()
            ->with(\Mockery::type('string'), $alias)
            ->andReturn('...');
        $this->wpdb->shouldReceive('get_var')->once()->andReturn($target_url);

        Functions\expect('wp_redirect')
            ->once()
            ->andReturnUsing(function () {
                throw new \RuntimeException('redirect_called');
            });

        $this->expectException(\RuntimeException::class);

        Alias_Manager_Redirector::maybe_redirect();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_URI']);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Frühe Rückgaben – kein Redirect
    // -------------------------------------------------------------------------

    public function test_no_redirect_when_is_admin(): void
    {
        Functions\expect('is_admin')->once()->andReturn(true);
        Functions\expect('wp_redirect')->never();

        Alias_Manager_Redirector::maybe_redirect();
        $this->addToAssertionCount(1);
    }

    public function test_no_redirect_when_doing_ajax(): void
    {
        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(true);
        Functions\expect('wp_redirect')->never();

        Alias_Manager_Redirector::maybe_redirect();
        $this->addToAssertionCount(1);
    }

    public function test_no_redirect_when_doing_cron(): void
    {
        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(true);
        Functions\expect('wp_redirect')->never();

        Alias_Manager_Redirector::maybe_redirect();
        $this->addToAssertionCount(1);
    }

    public function test_no_redirect_when_request_uri_not_set(): void
    {
        unset( $_SERVER['REQUEST_URI'] );

        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(false);
        Functions\expect('home_url')->once()->andReturn('https://example.com');
        Functions\expect('wp_redirect')->never();

        Alias_Manager_Redirector::maybe_redirect();
        $this->addToAssertionCount(1);
    }

    public function test_no_redirect_when_request_path_is_empty(): void
    {
        $_SERVER['REQUEST_URI'] = '/';

        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(false);
        Functions\expect('home_url')->once()->andReturn('https://example.com');
        Functions\expect('wp_redirect')->never();

        Alias_Manager_Redirector::maybe_redirect();
        $this->addToAssertionCount(1);
    }

    public function test_no_redirect_when_alias_not_found(): void
    {
        $_SERVER['REQUEST_URI'] = '/unknown-path';

        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(false);
        Functions\expect('home_url')->once()->andReturn('https://example.com');

        $this->wpdb->shouldReceive('prepare')->once()->andReturn('...');
        $this->wpdb->shouldReceive('get_var')->once()->andReturn(null);

        Functions\expect('wp_redirect')->never();

        Alias_Manager_Redirector::maybe_redirect();
    }

    // -------------------------------------------------------------------------
    // Alias-Auflösung
    // -------------------------------------------------------------------------

    /**
     * Prüft, dass beim Fund eines Alias wp_redirect mit korrekter URL
     * und 301-Status aufgerufen wird.
     *
     * wp_redirect wird per Brain\Monkey als Stub gesetzt und wirft eine Exception,
     * die den exit-Aufruf verhindert und die Assertion ermöglicht.
     */
    public function test_redirect_called_with_correct_url_and_status(): void
    {
        $_SERVER['REQUEST_URI'] = '/aliasA';

        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(false);
        Functions\expect('home_url')->once()->andReturn('https://example.com');

        $this->wpdb->shouldReceive('prepare')->once()->andReturn('...');
        $this->wpdb->shouldReceive('get_var')->once()->andReturn('https://example.com/pageA');

        Functions\expect('wp_redirect')
            ->once()
            ->with('https://example.com/pageA', 301)
            ->andReturnUsing(function () {
                throw new \RuntimeException('redirect_called');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('redirect_called');

        Alias_Manager_Redirector::maybe_redirect();
    }

    public function test_redirect_ignores_query_string_in_request_uri(): void
    {
        $this->assert_redirect_called(
            '/my-alias?utm_source=email&utm_medium=cpc',
            'https://example.com',
            'my-alias',
            'https://example.com/page'
        );
    }

    public function test_redirect_strips_subdirectory_prefix(): void
    {
        $this->assert_redirect_called(
            '/subdir/aliasB',
            'https://example.com/subdir',
            'aliasB',
            'https://example.com/subdir/pageB'
        );
    }

    public function test_redirect_handles_trailing_slash_in_request(): void
    {
        $this->assert_redirect_called(
            '/aliasA/',
            'https://example.com',
            'aliasA',
            'https://example.com/pageA'
        );
    }
}
