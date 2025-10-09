<?php
declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationSucceeded;
use OfflineAgency\FilamentSpid\Services\SpidUserService;
use OfflineAgency\FilamentSpid\Http\Requests\SpidLoginRequest;
use Italia\SPIDAuth\SPIDAuth;
use Illuminate\Support\Facades\Cache;

class SpidController extends Controller
{
    protected SPIDAuth $spid;
    protected SpidUserService $userService;

    public function __construct(SPIDAuth $spid, SpidUserService $userService)
    {
        $this->spid = $spid;
        $this->userService = $userService;
    }

    /**
     * Show SPID providers list
     */
    public function providers(): JsonResponse
    {
        $ttl = (int) config('filament-spid.cache.providers_ttl', 3600);
        $providers = Cache::remember('filament_spid_providers', $ttl, function () {
            $idps = config('spid-idps', []);
            $list = [];
            foreach ($idps as $key => $idp) {
                if ($key !== 'empty' && isset($idp['isActive']) && $idp['isActive']) {
                    $list[] = [
                        'provider' => $idp['provider'] ?? $key,
                        'title' => $idp['title'] ?? $key,
                        'entityName' => $idp['entityName'] ?? null,
                        'logo' => $idp['logo'] ?? null,
                    ];
                }
            }
            return $list;
        });

        return response()->json(['providers' => $providers]);
    }

    /**
     * Initiate SPID login
     */
    public function login(SpidLoginRequest $request): RedirectResponse
    {
        $provider = $request->validated('provider');
        $level = $request->input('level', config('filament-spid.spid_level', 'https://www.spid.gov.it/SpidL2'));

        if (! $provider) {
            return redirect()->back()->with('error', __('filament-spid::spid.provider_required'));
        }

        try {
            return $this->spid->login(
                $provider,
                $level,
                route('spid.acs')
            );
        } catch (\Exception $e) {
            \Log::error('SPID Login Error: '.$e->getMessage());

            return redirect()->back()->with('error', __('filament-spid::spid.login_error'));
        }
    }

    /**
     * Handle SPID ACS (Assertion Consumer Service) callback
     */
    public function acs(Request $request): RedirectResponse
    {
        try {
            $this->spid->acs();

            if (! $this->spid->isAuthenticated()) {
                event(new SpidAuthenticationFailed('Not authenticated after ACS'));
                return redirect()
                    ->route('filament.admin.auth.login')
                    ->with('error', __('filament-spid::spid.authentication_failed'));
            }

            $spidUser = $this->spid->getSPIDUser();
            $spidData = SpidUserData::fromSpidAuth($spidUser);

            $user = $this->userService->findOrCreateUser($spidData);

            Auth::login($user);

            event(new SpidAuthenticationSucceeded($user, $spidData));

            return redirect()->intended(config('filament-spid.redirect_after_login', '/admin'));
        } catch (\Exception $e) {
            \Log::error('SPID ACS Error: '.$e->getMessage());
            event(new SpidAuthenticationFailed($e->getMessage()));

            return redirect()
                ->route('filament.admin.auth.login')
                ->with('error', __('filament-spid::spid.acs_error'));
        }
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

            return redirect()->route('filament.admin.auth.login');
        } catch (\Exception $e) {
            \Log::error('SPID Logout Error: '.$e->getMessage());

            return redirect()->route('filament.admin.auth.login');
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
