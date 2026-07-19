<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Listeners;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Italia\SPIDAuth\Events\LogoutEvent;

/**
 * Tears down the Laravel session when the citizen logs out of SPID.
 *
 * Without this the local session outlives the SPID one.
 */
class HandleSpidLogout
{
    use ResolvesPanel;

    public function handle(LogoutEvent $event): void
    {
        Auth::guard($this->guard())->logout();

        Session::invalidate();
        Session::regenerateToken();
    }
}
