<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Support;

/**
 * Config values read with the type the package expects.
 *
 * Unlike Repository::string() and ::array(), a value of the wrong type is
 * treated as missing instead of throwing, so a typo in a published config
 * file degrades the same way an absent key does.
 */
final class TypedConfig
{
    public static function string(string $key, ?string $default = null): ?string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<mixed>
     */
    public static function array(string $key): array
    {
        $value = config($key, []);

        return is_array($value) ? $value : [];
    }
}
