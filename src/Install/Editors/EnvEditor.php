<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install\Editors;

/**
 * Sets variables in a .env file: an existing KEY= line is updated in place,
 * a missing one is appended once under a "# filament-spid" comment.
 */
final class EnvEditor
{
    public const HEADER = '# filament-spid (php artisan filament-spid:install)';

    /**
     * @param  array<string, string>  $variables
     */
    public function edit(string $source, array $variables): string
    {
        $lines = $source === '' ? [] : explode("\n", rtrim($source, "\n"));
        $missing = [];

        foreach ($variables as $key => $value) {
            $found = false;
            foreach ($lines as $i => $line) {
                if (str_starts_with($line, $key.'=')) {
                    $lines[$i] = "{$key}={$value}";
                    $found = true;
                }
            }
            if (! $found) {
                $missing[] = "{$key}={$value}";
            }
        }

        if ($missing !== []) {
            if (! in_array(self::HEADER, $lines, true)) {
                $lines[] = '';
                $lines[] = self::HEADER;
            }
            $lines = array_merge($lines, $missing);
        }

        return implode("\n", $lines)."\n";
    }
}
