<?php

namespace OfflineAgency\FilamentSpid\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SpidAuthenticationFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $reason,
    ) {
    }
}


