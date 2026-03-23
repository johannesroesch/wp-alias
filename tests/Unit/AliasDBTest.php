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
use Alias_Manager_DB;

/**
 * Tests für Alias_Manager_DB.
 *
 * Da die Klasse vollständig auf $wpdb aufbaut, wird das globale $wpdb-Objekt
 * durch einen Mockery-Mock ersetzt. Datenbankoperationen finden nicht statt.
 */
final class AliasDBTest extends WpDbTestCase
{

    // -------------------------------------------------------------------------
    // create_table()
    // -------------------------------------------------------------------------

    public function test_create_table_calls_get_charset_collate_and_db_delta(): void
    {
        $this->wpdb
            ->shouldReceive('get_charset_collate')
            ->once()
            ->andReturn('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        Alias_Manager_DB::create_table();

        $sql = $GLOBALS['_last_db_delta_sql'] ?? '';
        $this->assertStringContainsString('id',         $sql);
        $this->assertStringContainsString('alias',      $sql);
        $this->assertStringContainsString('target_url', $sql);
        $this->assertStringContainsString('created_at', $sql);
        $this->assertStringContainsString('UNIQUE KEY', $sql);
        $this->assertStringContainsString('utf8mb4',    $sql);
    }

    // -------------------------------------------------------------------------
    // table()
    // -------------------------------------------------------------------------

    public function test_table_returns_prefixed_table_name(): void
    {
        $this->assertSame('wp_aliases', Alias_Manager_DB::table());
    }

    public function test_table_respects_custom_prefix(): void
    {
        $this->wpdb->prefix = 'mysite_';
        $this->assertSame('mysite_aliases', Alias_Manager_DB::table());
    }

    // -------------------------------------------------------------------------
    // find_by_alias()
    // -------------------------------------------------------------------------

    public function test_find_by_alias_returns_target_url_when_alias_exists(): void
    {
        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(
                \Mockery::type('string'),
                'aliasA'
            )
            ->andReturn("SELECT target_url FROM wp_aliases WHERE alias = 'aliasA'");

        $this->wpdb
            ->shouldReceive('get_var')
            ->once()
            ->andReturn('https://example.com/pageA');

        $result = Alias_Manager_DB::find_by_alias('aliasA');

        $this->assertSame('https://example.com/pageA', $result);
    }

    public function test_find_by_alias_returns_null_when_alias_missing(): void
    {
        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->andReturn("SELECT target_url FROM wp_aliases WHERE alias = 'unknown'");

        $this->wpdb
            ->shouldReceive('get_var')
            ->once()
            ->andReturn(null);

        $result = Alias_Manager_DB::find_by_alias('unknown');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // insert()
    // -------------------------------------------------------------------------

    public function test_insert_passes_sanitized_values_to_wpdb(): void
    {
        Functions\expect('sanitize_text_field')
            ->once()
            ->with('my-alias')
            ->andReturn('my-alias');

        Functions\expect('esc_url_raw')
            ->once()
            ->with('https://example.com/target')
            ->andReturn('https://example.com/target');

        $this->wpdb
            ->shouldReceive('insert')
            ->once()
            ->with(
                'wp_aliases',
                [
                    'alias'      => 'my-alias',
                    'target_url' => 'https://example.com/target',
                ],
                ['%s', '%s']
            )
            ->andReturn(1);

        $result = Alias_Manager_DB::insert('my-alias', 'https://example.com/target');

        $this->assertSame(1, $result);
    }

    public function test_insert_returns_false_on_duplicate_alias(): void
    {
        Functions\expect('sanitize_text_field')->once()->andReturn('duplicate');
        Functions\expect('esc_url_raw')->once()->andReturn('https://example.com/page');

        $this->wpdb
            ->shouldReceive('insert')
            ->once()
            ->andReturn(false);

        $result = Alias_Manager_DB::insert('duplicate', 'https://example.com/page');

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // update()
    // -------------------------------------------------------------------------

    public function test_update_passes_correct_arguments_to_wpdb(): void
    {
        Functions\expect('sanitize_text_field')->once()->andReturn('new-alias');
        Functions\expect('esc_url_raw')->once()->andReturn('https://example.com/new-target');

        $this->wpdb
            ->shouldReceive('update')
            ->once()
            ->with(
                'wp_aliases',
                ['alias' => 'new-alias', 'target_url' => 'https://example.com/new-target'],
                ['id' => 42],
                ['%s', '%s'],
                ['%d']
            )
            ->andReturn(1);

        $result = Alias_Manager_DB::update(42, 'new-alias', 'https://example.com/new-target');

        $this->assertSame(1, $result);
    }

    public function test_update_casts_id_to_int(): void
    {
        Functions\expect('sanitize_text_field')->once()->andReturn('alias');
        Functions\expect('esc_url_raw')->once()->andReturn('https://example.com/page');

        $this->wpdb
            ->shouldReceive('update')
            ->once()
            ->with(
                \Mockery::any(),
                \Mockery::any(),
                ['id' => 7],   // "7" (string) soll zu 7 (int) werden
                \Mockery::any(),
                \Mockery::any()
            )
            ->andReturn(1);

        // Übergabe als String – soll trotzdem als int ankommen
        Alias_Manager_DB::update('7', 'alias', 'https://example.com/page');
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function test_delete_passes_id_as_int_to_wpdb(): void
    {
        $this->wpdb
            ->shouldReceive('delete')
            ->once()
            ->with('wp_aliases', ['id' => 5], ['%d'])
            ->andReturn(1);

        $result = Alias_Manager_DB::delete(5);

        $this->assertSame(1, $result);
    }

    public function test_delete_casts_string_id_to_int(): void
    {
        $this->wpdb
            ->shouldReceive('delete')
            ->once()
            ->with(\Mockery::any(), ['id' => 3], \Mockery::any())
            ->andReturn(1);

        Alias_Manager_DB::delete('3');
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function test_get_returns_row_for_existing_id(): void
    {
        $expected = (object) ['id' => 1, 'alias' => 'foo', 'target_url' => 'https://example.com'];

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->andReturn('SELECT * FROM wp_aliases WHERE id = 1');

        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->andReturn($expected);

        $result = Alias_Manager_DB::get(1);

        $this->assertSame($expected, $result);
    }

    public function test_get_returns_null_for_missing_id(): void
    {
        $this->wpdb->shouldReceive('prepare')->once()->andReturn('...');
        $this->wpdb->shouldReceive('get_row')->once()->andReturn(null);

        $this->assertNull(Alias_Manager_DB::get(999));
    }

    // -------------------------------------------------------------------------
    // all()
    // -------------------------------------------------------------------------

    public function test_all_returns_empty_array_when_no_rows(): void
    {
        $this->wpdb
            ->shouldReceive('get_results')
            ->once()
            ->andReturn([]);

        $result = Alias_Manager_DB::all();

        $this->assertSame([], $result);
    }

    public function test_all_returns_all_rows_ordered_by_alias(): void
    {
        $rows = [
            (object) ['id' => 2, 'alias' => 'alpha'],
            (object) ['id' => 1, 'alias' => 'beta'],
        ];

        $this->wpdb
            ->shouldReceive('get_results')
            ->once()
            ->andReturn($rows);

        $result = Alias_Manager_DB::all();

        $this->assertCount(2, $result);
        $this->assertSame('alpha', $result[0]->alias);
    }
}
