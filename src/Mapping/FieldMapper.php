<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Mapping;

use OfflineAgency\FilamentSpid\DTOs\SpidUserData;

/**
 * Resolves one filament-spid.field_mapping entry against the SPID attributes.
 *
 * In order, an entry is:
 * 1. the name of a SPID attribute (present in the payload or one of
 *    SpidUserData::ATTRIBUTES), whose value it maps to;
 * 2. the class name of an invokable mapper, resolved through the container
 *    (both cacheable with `php artisan config:cache`);
 * 3. a closure or an array callable (which keep the config from being cached).
 *
 * Anything else, including a plain function name, maps to null: a config
 * value never runs an arbitrary function.
 */
final class FieldMapper
{
    /**
     * @param  array<string, string|null>  $spidUser  SpidUserData::toArray()
     */
    public static function value(mixed $mapper, array $spidUser): mixed
    {
        if (is_string($mapper)) {
            if (array_key_exists($mapper, $spidUser) || in_array($mapper, SpidUserData::ATTRIBUTES, true)) {
                return $spidUser[$mapper] ?? null;
            }

            $instance = class_exists($mapper) ? app($mapper) : null;

            return is_callable($instance) ? $instance($spidUser) : null;
        }

        if ($mapper instanceof \Closure || (is_array($mapper) && is_callable($mapper))) {
            return $mapper($spidUser);
        }

        return null;
    }
}
