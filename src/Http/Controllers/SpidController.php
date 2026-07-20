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
use Illuminate\Support\Facades\Cache;
use Italia\SPIDAuth\Exceptions\SPIDLoginAnomalyException;
use Italia\SPIDAuth\Exceptions\SPIDLoginException;
use Italia\SPIDAuth\SPIDAuth;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationSucceeded;
use OfflineAgency\FilamentSpid\Services\SpidUserService;

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
     *
     * Redirects to the Filament login page where the user can select a SPID provider.
     * The actual SAML handshake is triggered by the provider button form posting to
     * the `spid-auth_do-login` route provided by italia/spid-laravel.
     */
    public function login(): RedirectResponse
    {
        return redirect()->route($this->loginRoute());
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
                    ->route($this->loginRoute())
                    ->with('spid_error', __('filament-spid::spid.authentication_failed'));
            }

            $spidUser = $this->spid->getSPIDUser();
            $spidData = SpidUserData::fromSpidAuth($spidUser);

            $user = $this->userService->findOrCreateUser($spidData);

            $guard = optional(Filament::getCurrentPanel())->getAuthGuard() ?? config('auth.defaults.guard');
            Auth::guard($guard)->login($user);
            $request->session()->regenerate();

            event(new SpidAuthenticationSucceeded($user, $spidData));

            return redirect()->intended(config('filament-spid.redirect_after_login', '/admin'));
        } catch (SPIDLoginAnomalyException $e) {
            \Log::warning('SPID anomaly code '.$e->getErrorCode().': '.$e->getMessage());
            event(new SpidAuthenticationFailed($e->getMessage()));

            return redirect()
                ->route($this->loginRoute())
                ->with('spid_error', $this->resolveAnomalyMessage($e));
        } catch (SPIDLoginException $e) {
            \Log::error('SPID login exception code '.$e->getCode().': '.$e->getMessage());
            event(new SpidAuthenticationFailed($e->getMessage()));

            return redirect()
                ->route($this->loginRoute())
                ->with('spid_error', $this->resolveSamlMessage($e));
        } catch (\Exception $e) {
            \Log::error('SPID ACS Error: '.$e->getMessage());
            event(new SpidAuthenticationFailed($e->getMessage()));

            return redirect()
                ->route($this->loginRoute())
                ->with('spid_error', __('filament-spid::spid.acs_error'));
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

            return redirect()->route($this->loginRoute());
        } catch (\Exception $e) {
            \Log::error('SPID Logout Error: '.$e->getMessage());

            return redirect()->route($this->loginRoute());
        }
    }

    private function loginRoute(): string
    {
        $panelId = optional(Filament::getCurrentPanel())->getId() ?? 'admin';

        return "filament.{$panelId}.auth.login";
    }

    /**
     * Post-SAML login handler.
     *
     * Called after italia/spid-laravel finishes ACS processing and redirects here
     * via spid-auth.after_login_url. The SPID user is already in session — no need
     * to call $this->spid->acs() again.
     */
    public function afterLogin(Request $request): RedirectResponse
    {
        try {
            if (! $this->spid->isAuthenticated()) {
                event(new SpidAuthenticationFailed('Not authenticated after ACS'));

                return redirect()
                    ->route($this->loginRoute())
                    ->with('spid_error', __('filament-spid::spid.authentication_failed'));
            }

            $spidUser = $this->spid->getSPIDUser();
            $spidData = SpidUserData::fromSpidAuth($spidUser);

            $user = $this->userService->findOrCreateUser($spidData);

            $guard = config('filament-spid.auth_guard', config('auth.defaults.guard'));
            Auth::guard($guard)->login($user);
            $request->session()->regenerate();

            event(new SpidAuthenticationSucceeded($user, $spidData));

            return redirect()->intended(config('filament-spid.redirect_after_login', '/admin'));
        } catch (SPIDLoginAnomalyException $e) {
            \Log::warning('SPID anomaly code '.$e->getErrorCode().': '.$e->getMessage());
            event(new SpidAuthenticationFailed($e->getMessage()));

            return redirect()
                ->route($this->loginRoute())
                ->with('spid_error', $this->resolveAnomalyMessage($e));
        } catch (SPIDLoginException $e) {
            \Log::error('SPID login exception code '.$e->getCode().': '.$e->getMessage());
            event(new SpidAuthenticationFailed($e->getMessage()));

            return redirect()
                ->route($this->loginRoute())
                ->with('spid_error', $this->resolveSamlMessage($e));
        } catch (\Exception $e) {
            \Log::error('SPID After-Login Error: '.$e->getMessage());
            event(new SpidAuthenticationFailed($e->getMessage()));

            return redirect()
                ->route($this->loginRoute())
                ->with('spid_error', __('filament-spid::spid.acs_error'));
        }
    }

    private function resolveAnomalyMessage(SPIDLoginAnomalyException $e): string
    {
        $key = 'filament-spid::spid.error_'.$e->getErrorCode();
        $translated = __($key);

        // Fall back to the library's own user message when no translation exists
        return $translated !== $key ? $translated : $e->getUserMessage();
    }

    private function resolveSamlMessage(SPIDLoginException $e): string
    {
        $keyMap = [
            0 => 'saml_validation_error',
            1 => 'saml_response_already_processed',
            2 => 'saml_authentication_error',
            3 => 'saml_request_id_missing',
            4 => 'saml_malformed_idp',
            5 => 'saml_nonexistent_idp',
        ];
        $key = $keyMap[$e->getCode()] ?? 'acs_error';

        return __("filament-spid::spid.{$key}");
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
