<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Tests\Fixtures;

/**
 * Field mappers given as array callables in field_mapping.
 */
class StaticMapper
{
    /**
     * @param  array<string, mixed>  $spidUser
     */
    public static function shout(array $spidUser): string
    {
        return strtoupper((string) $spidUser['name']);
    }
}
