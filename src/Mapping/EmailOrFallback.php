<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Mapping;

/**
 * The SPID email, or a per-citizen address that can never receive mail.
 *
 * SPID only sends an email when the IdP holds one. The fallback is unique per
 * citizen and stable across logins, and .invalid (RFC 2606) never resolves, so
 * it neither receives mail nor collides with a real address.
 */
final class EmailOrFallback
{
    /**
     * @param  array<string, string|null>  $spidUser
     */
    public function __invoke(array $spidUser): string
    {
        return $spidUser['email'] ?? strtolower($spidUser['fiscalNumber'] ?? '').'@spid.invalid';
    }
}
