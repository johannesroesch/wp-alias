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

use PHPUnit\Framework\TestCase;

/**
 * Prüft, dass alle .po-Dateien vollständig übersetzt sind und
 * für jede .po-Datei eine kompilierte .mo-Datei vorhanden ist.
 */
final class LanguageFilesTest extends TestCase
{
    private static string $languagesDir;

    public static function setUpBeforeClass(): void
    {
        self::$languagesDir = dirname(__DIR__, 2) . '/languages';
    }

    private static function langDir(): string
    {
        return dirname(__DIR__, 2) . '/languages';
    }

    // -------------------------------------------------------------------------
    // .pot – Vorlage
    // -------------------------------------------------------------------------

    public function test_pot_file_exists(): void
    {
        $this->assertFileExists(self::langDir() . '/alias-manager.pot');
    }

    public function test_pot_contains_msgids(): void
    {
        $msgids = self::parseMsgids(self::langDir() . '/alias-manager.pot');
        $this->assertNotEmpty($msgids, 'alias-manager.pot enthält keine msgid-Einträge.');
    }

    // -------------------------------------------------------------------------
    // .po – Vollständigkeit aller Übersetzungen
    // -------------------------------------------------------------------------

    /**
     * @dataProvider poFileProvider
     */
    public function test_po_file_has_all_translations(string $poFile): void
    {
        $potMsgids    = self::parseMsgids(self::langDir() . '/alias-manager.pot');
        $translations = self::parseTranslations($poFile);
        $missing      = [];

        foreach ($potMsgids as $msgid) {
            if (! isset($translations[$msgid]) || $translations[$msgid] === '') {
                $missing[] = $msgid;
            }
        }

        $this->assertEmpty(
            $missing,
            sprintf(
                "%s: %d nicht übersetzte Strings:\n- %s",
                basename($poFile),
                count($missing),
                implode("\n- ", $missing)
            )
        );
    }

    // -------------------------------------------------------------------------
    // .mo – kompilierte Binärdatei vorhanden
    // -------------------------------------------------------------------------

    /**
     * @dataProvider poFileProvider
     */
    public function test_mo_file_exists_for_po_file(string $poFile): void
    {
        $moFile = substr($poFile, 0, -3) . '.mo';
        $this->assertFileExists(
            $moFile,
            basename($poFile) . ': Kompilierte .mo-Datei fehlt.'
        );
    }

    // -------------------------------------------------------------------------
    // Data Provider
    // -------------------------------------------------------------------------

    public static function poFileProvider(): array
    {
        $dir   = dirname(__DIR__, 2) . '/languages';
        $files = glob($dir . '/alias-manager-*.po') ?: [];

        return array_combine(
            array_map('basename', $files),
            array_map(static fn(string $f) => [$f], $files)
        );
    }

    // -------------------------------------------------------------------------
    // Hilfsmethoden
    // -------------------------------------------------------------------------

    private static function parseMsgids(string $file): array
    {
        $msgids = [];
        preg_match_all('/^msgid "(.+)"$/m', (string) file_get_contents($file), $matches);
        foreach ($matches[1] as $raw) {
            $msgids[] = stripslashes($raw);
        }
        return $msgids;
    }

    private static function parseTranslations(string $file): array
    {
        $translations = [];
        preg_match_all(
            '/^msgid "(.+)"\nmsgstr "(.*)"/m',
            (string) file_get_contents($file),
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $match) {
            $translations[stripslashes($match[1])] = $match[2];
        }
        return $translations;
    }
}
