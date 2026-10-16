<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install\Editors;

use Italia\SPIDAuth\ServiceProvider as SpidAuthServiceProvider;

/**
 * config/spid-auth.php, the protocol configuration italia/spid-laravel reads.
 *
 * The installer writes it whole: the library's defaults with its values on
 * top, and a marker holding the hash of what it wrote. It rewrites the file
 * only while it is the library's untouched copy or still exactly what the
 * installer wrote; a file edited by hand is left alone.
 */
final class SpidAuthConfig
{
    public const MARKER = '// Written by php artisan filament-spid:install; edit freely: the installer then stops rewriting this file. sha256:';

    public static function libraryDefaultsPath(): string
    {
        return dirname((string) (new \ReflectionClass(SpidAuthServiceProvider::class))->getFileName(), 2).'/config/spid-auth.php';
    }

    /**
     * @return array<mixed>
     */
    public static function libraryDefaults(): array
    {
        $defaults = require self::libraryDefaultsPath();

        return is_array($defaults) ? $defaults : [];
    }

    /**
     * Whether the installer may rewrite this content (null: no file yet).
     */
    public function isManaged(?string $current): bool
    {
        if ($current === null || $current === (string) file_get_contents(self::libraryDefaultsPath())) {
            return true;
        }

        $lines = explode("\n", $current, 3);
        if (! str_starts_with($lines[1] ?? '', self::MARKER)) {
            return false;
        }

        return substr($lines[1], strlen(self::MARKER)) === hash('sha256', $lines[2] ?? '');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function render(array $values): string
    {
        $body = "\nreturn ".self::export(array_replace(self::libraryDefaults(), $values)).";\n";

        return "<?php\n".self::MARKER.hash('sha256', $body)."\n".$body;
    }

    /**
     * The lines to put in a hand-edited file.
     *
     * @param  array<string, mixed>  $values
     */
    public static function snippet(array $values): string
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $lines[] = '    '.var_export($key, true).' => '.self::export($value, 1).',';
        }

        return implode("\n", $lines);
    }

    protected static function export(mixed $value, int $depth = 0): string
    {
        if (! is_array($value)) {
            return var_export($value, true);
        }
        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth + 1);
        $lines = [];
        foreach ($value as $key => $item) {
            $prefix = array_is_list($value) ? '' : var_export($key, true).' => ';
            $lines[] = $indent.$prefix.self::export($item, $depth + 1).',';
        }

        return "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth).']';
    }
}
