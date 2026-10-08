<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Mapping;

/**
 * Resolves one filament-spid.field_mapping entry against the SPID attributes.
 *
 * An entry is the class name of an invokable mapper (cacheable with
 * `php artisan config:cache`), the name of a SPID attribute, or a closure
 * (which keeps the config from being cached).
 */
final class FieldMapper
{
    /**
     * @param  array<string, mixed>  $spidUser  SpidUserData::toArray()
     */
    public static function value(mixed $mapper, array $spidUser): mixed
    {
        if (is_callable($mapper)) {
            return $mapper($spidUser);
        }

        if (is_string($mapper) && class_exists($mapper)) {
            return app($mapper)($spidUser);
        }

        return is_string($mapper) ? ($spidUser[$mapper] ?? null) : null;
    }
}
