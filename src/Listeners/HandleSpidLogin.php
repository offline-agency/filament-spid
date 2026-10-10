<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Listeners;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Italia\SPIDAuth\Events\LoginEvent;
use OfflineAgency\FilamentSpid\Constants\SpidLevel;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationSucceeded;
use OfflineAgency\FilamentSpid\Exceptions\SpidAccessDeniedException;
use OfflineAgency\FilamentSpid\Exceptions\SpidPanelNotFoundException;
use OfflineAgency\FilamentSpid\Services\SpidUserService;

/**
 * Provisions and authenticates the citizen once italia/spid-laravel has
 * validated the SAML response and fired its LoginEvent.
 */
class HandleSpidLogin
{
    use ResolvesPanel;

    public function __construct(protected SpidUserService $userService) {}

    public function handle(LoginEvent $event): void
    {
        // Resolved first: a misconfigured panel must fail before anyone is
        // provisioned, and loudly rather than as a generic SPID error.
        try {
            $guard = $this->guard();
        } catch (SpidPanelNotFoundException $e) {
            $this->forgetSpidSession();

            throw $e;
        }

        try {
            if (! SpidLevel::requestedMeetsMinimum()) {
                $this->fail('requested SPID level below filament-spid.minimum_level', $event, 'insufficient_level');
            }

            $spidData = SpidUserData::fromSpidAuth($event->getSPIDUser());

            // Filament's own gate, checked before logging in: otherwise the
            // citizen lands on a 403 with a live SPID session, and outside
            // production Filament does not check at all. It runs inside the
            // provisioning transaction, so a refused citizen gets no account
            // and an existing one keeps its data.
            $panel = $this->panel();

            try {
                $user = $this->userService->findOrCreateUser(
                    $spidData,
                    fn ($user): bool => ! ($user instanceof FilamentUser && $panel && ! $user->canAccessPanel($panel)),
                );
            } catch (SpidAccessDeniedException) {
                $this->fail('the user may not access the panel', $event, 'access_denied');
            }

            if (! $user) {
                // auto_create_users is off and no account matches this fiscal code.
                $this->fail('no user matches the SPID fiscal code', $event, 'authentication_failed');
            }

            // No remember token: a SPID session must not outlive the browser one.
            Auth::guard($guard)->login($user);

            Session::regenerate();

            event(new SpidAuthenticationSucceeded($user, $spidData));
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Only the exception class reaches the log: messages routinely carry
            // personal data (a failed insert quotes the values it tried to write).
            $this->fail($e::class.' (code '.$e->getCode().')', $event, 'acs_error');
        }
    }

    /**
     * Abort the SPID login, sending the citizen back to the panel login page.
     *
     * The message is logged without any SPID attribute: those are personal data.
     */
    protected function fail(string $reason, LoginEvent $event, string $translationKey): never
    {
        Log::error('SPID login failed ('.$event->getIdp().'): '.$reason);

        event(new SpidAuthenticationFailed($reason));

        $this->forgetSpidSession();

        throw new HttpResponseException(
            redirect()->to($this->loginUrl())
                ->with('spid_error', __("filament-spid::spid.{$translationKey}"))
        );
    }

    /**
     * Forget the SPID session italia/spid-laravel stores before firing
     * LoginEvent. Left behind, isAuthenticated() stays true and doLogin()
     * short-circuits every retry.
     */
    protected function forgetSpidSession(): void
    {
        Session::forget(['spid_sessionId', 'spid_nameId', 'spid_user', 'spid_idp', 'spid_idpEntityName']);
    }
}
