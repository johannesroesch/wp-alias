<?php
/**
 * Base test case for WordPress unit tests requiring a wpdb mock.
 *
 * @package   Alias_Manager
 * @copyright 2025 Johannes Rösch
 * @license   GPL-2.0+
 */
declare(strict_types=1);

namespace WPAlias\Tests\Unit;

abstract class WpDbTestCase extends WpTestCase
{
    /** @var \Mockery\MockInterface */
    protected $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb         = \Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }
}
