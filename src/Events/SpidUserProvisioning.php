<?php

namespace OfflineAgency\FilamentSpid\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;

/**
 * Fired inside the provisioning transaction, before the panel gate: the place
 * to give a new account its roles. A refused login rolls the changes back.
 * SpidUserCreated and SpidUserUpdated follow once the transaction commits.
 */
class SpidUserProvisioning
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly SpidUserData $spidData,
        public readonly bool $created,
    ) {}
}
