<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Italia\SPIDAuth\SPIDAuth;

class SpidController extends Controller
{
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

        foreach (config('spid-idps', []) as $key => $idp) {
            if ($key !== 'empty' && ($idp['isActive'] ?? false)) {
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
     * Handle SPID logout
     */
    public function logout(Request $request): RedirectResponse
    {
        try {
            $this->spid->logout();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to($this->loginUrl());
        } catch (\Exception $e) {
            \Log::error('SPID Logout Error: '.$e->getMessage());

            return redirect()->to($this->loginUrl());
        }
    }

    /**
     * URL of the panel login page.
     *
     * Resolved from the panel rather than built from a route name, so panels
     * with a custom id or a custom login page keep working.
     */
    private function loginUrl(): string
    {
        try {
            return (Filament::getCurrentPanel() ?? Filament::getDefaultPanel())
                ->getLoginUrl() ?? url('/');
        } catch (\Throwable) {
            return url('/');
        }
    }

    /**
     * Return SPID metadata
     */
    public function metadata(): Response
    {
        try {
            $metadata = $this->spid->getSPMetadata();

            return response($metadata)->header('Content-Type', 'application/xml');
        } catch (\Exception $e) {
            \Log::error('SPID Metadata Error: '.$e->getMessage());

            return response('Error generating metadata', 500);
        }
    }
}
