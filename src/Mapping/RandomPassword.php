<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Mapping;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OfflineAgency\FilamentSpid\Mapping\Contracts\CreateOnly;

/**
 * A hash of a random password nobody knows, for users tables whose password
 * column is NOT NULL (Laravel's stock one is).
 *
 * The citizen signs in with SPID only. The value is already hashed, which a
 * 'hashed' cast detects and stores as given. Create-only: later logins keep
 * the hash, so a password set afterwards (a reset) survives.
 */
final class RandomPassword implements CreateOnly
{
    /**
     * @param  array<string, mixed>  $spidUser
     */
    public function __invoke(array $spidUser): string
    {
        return Hash::make(Str::random(64));
    }
}
