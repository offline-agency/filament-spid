<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Italia\SPIDAuth\Exceptions\SPIDLogoutException;
use Italia\SPIDAuth\SPIDAuth;
use OfflineAgency\FilamentSpid\Listeners\ResolvesPanel;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

class SpidController extends Controller
{
    use ResolvesPanel;

    protected SPIDAuth $spid;

    public function __construct(SPIDAuth $spid)
    {
        $this->spid = $spid;
    }

    /**
     * Show SPID providers list
     */
    public function providers(): JsonResponse
    {
        // No cache: the list comes from config, which is already in memory.
        $providers = [];

        foreach (TypedConfig::array('spid-idps') as $key => $idp) {
            if ($key !== 'empty' && is_array($idp) && ($idp['isActive'] ?? false)) {
                $providers[] = [
                    'provider' => $idp['provider'] ?? $key,
                    'title' => $idp['title'] ?? $key,
                    'entityName' => $idp['entityName'] ?? null,
                    'logo' => $idp['logo'] ?? null,
                ];
            }
        }

        return response()->json(['providers' => $providers]);
    }

    /**
     * Initiate SPID login
     *
     * Redirects to the Filament login page where the user can select a SPID provider.
     * The actual SAML handshake is triggered by the provider button form posting to
     * the `spid-auth_do-login` route provided by italia/spid-laravel.
     */
    public function login(): RedirectResponse
    {
        return redirect()->to($this->loginUrl());
    }

    /**
     * Log the citizen out.
     *
     * A SPID session is handed to italia/spid-laravel, which starts the IdP
     * single logout (or, with only_sp_logout, ends it right away); either way
     * it fires LogoutEvent and HandleSpidLogout tears the panel session down.
     * Without a SPID session there is nothing to tell the IdP, so the panel
     * guard is logged out here.
     */
    public function logout(Request $request): RedirectResponse
    {
        if ($this->spid->isAuthenticated()) {
            // End the panel session before the round trip: the citizen may
            // never come back from the IdP. The library saves the session
            // before redirecting, so this sticks.
            Auth::guard($this->guard())->logout();

            try {
                return $this->spid->logout();
            } catch (SPIDLogoutException $e) {
                // The IdP could not be reached: end the local session anyway.
                Log::error('SPID logout failed: '.$e::class.' (code '.$e->getCode().')');
            }
        }

        Auth::guard($this->guard())->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to($this->loginUrl());
    }

    /**
     * Return the signed SP metadata.
     *
     * The library answers 404 when spid-auth.expose_sp_metadata is off and
     * throws SPIDMetadataException on a broken SP configuration, which should
     * surface rather than be masked.
     */
    public function metadata(): Response
    {
        return $this->spid->metadata();
    }
}
