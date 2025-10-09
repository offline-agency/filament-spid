<?php

namespace OfflineAgency\FilamentSpid\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;

class SpidUserUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly SpidUserData $spidData,
    ) {
    }
}


