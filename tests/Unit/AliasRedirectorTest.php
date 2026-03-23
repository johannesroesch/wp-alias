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
use Alias_Manager_Redirector;

/**
 * Tests für Alias_Manager_Redirector.
 *
 * Alle WordPress-Funktionen (is_admin, wp_doing_ajax, wp_doing_cron,
 * wp_unslash, wp_parse_url, home_url, wp_redirect) werden per Brain\Monkey gemockt.
 * Der `exit`-Aufruf nach dem Redirect wird durch eine Exception im wp_redirect-Mock
 * abgefangen, damit der eigentliche PHPUnit-Prozess weiterläuft.
 */
final class AliasRedirectorTest extends TestCase
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
        $GLOBALS['wpdb']    = $this->wpdb;

        $_SERVER['REQUEST_URI'] = '/';

        // wp_unslash gibt den Wert unverändert zurück (kein Slashing nötig im Test)
        Functions\when('wp_unslash')->returnArg();
        // wp_parse_url delegiert an natives parse_url
        Functions\when('wp_parse_url')->alias('parse_url');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        unset($GLOBALS['wpdb'], $_SERVER['REQUEST_URI']);
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
        $_SERVER['REQUEST_URI'] = '/my-alias?utm_source=email&utm_medium=cpc';

        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(false);
        Functions\expect('home_url')->once()->andReturn('https://example.com');

        // find_by_alias muss mit 'my-alias' aufgerufen werden, nicht mit dem Query-String
        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(\Mockery::type('string'), 'my-alias')
            ->andReturn('...');

        $this->wpdb->shouldReceive('get_var')->once()->andReturn('https://example.com/page');

        Functions\expect('wp_redirect')
            ->once()
            ->with('https://example.com/page', 301)
            ->andReturnUsing(function () {
                throw new \RuntimeException('redirect_called');
            });

        $this->expectException(\RuntimeException::class);

        Alias_Manager_Redirector::maybe_redirect();
    }

    public function test_redirect_strips_subdirectory_prefix(): void
    {
        // WordPress liegt in /subdir/, Alias heißt "aliasB"
        $_SERVER['REQUEST_URI'] = '/subdir/aliasB';

        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(false);
        Functions\expect('home_url')->once()->andReturn('https://example.com/subdir');

        // Erwartet: find_by_alias wird mit "aliasB" aufgerufen (ohne Präfix)
        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(\Mockery::type('string'), 'aliasB')
            ->andReturn('...');

        $this->wpdb->shouldReceive('get_var')->once()->andReturn('https://example.com/subdir/pageB');

        Functions\expect('wp_redirect')
            ->once()
            ->with('https://example.com/subdir/pageB', 301)
            ->andReturnUsing(function () {
                throw new \RuntimeException('redirect_called');
            });

        $this->expectException(\RuntimeException::class);

        Alias_Manager_Redirector::maybe_redirect();
    }

    public function test_redirect_handles_trailing_slash_in_request(): void
    {
        $_SERVER['REQUEST_URI'] = '/aliasA/';

        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(false);
        Functions\expect('home_url')->once()->andReturn('https://example.com');

        // trim('/') muss "aliasA" ergeben, nicht "aliasA/"
        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(\Mockery::type('string'), 'aliasA')
            ->andReturn('...');

        $this->wpdb->shouldReceive('get_var')->once()->andReturn('https://example.com/pageA');

        Functions\expect('wp_redirect')
            ->once()
            ->andReturnUsing(function () {
                throw new \RuntimeException('redirect_called');
            });

        $this->expectException(\RuntimeException::class);

        Alias_Manager_Redirector::maybe_redirect();
    }
}
