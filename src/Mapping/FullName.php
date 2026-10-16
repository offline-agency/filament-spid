<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Mapping;

/**
 * "Name FamilyName" from the SPID attributes.
 */
final class FullName
{
    /**
     * @param  array<string, string|null>  $spidUser
     */
    public function __invoke(array $spidUser): string
    {
        return trim(($spidUser['name'] ?? '').' '.($spidUser['familyName'] ?? ''));
    }
}
