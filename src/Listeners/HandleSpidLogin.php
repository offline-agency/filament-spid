<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Listeners;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Italia\SPIDAuth\Events\LoginEvent;
use OfflineAgency\FilamentSpid\Constants\SpidLevel;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationSucceeded;
use OfflineAgency\FilamentSpid\Exceptions\SpidAccessDeniedException;
use OfflineAgency\FilamentSpid\Exceptions\SpidLoginAborted;
use OfflineAgency\FilamentSpid\Exceptions\SpidPanelNotFoundException;
use OfflineAgency\FilamentSpid\Services\SpidUserService;

/**
 * Provisions and authenticates the citizen once italia/spid-laravel has
 * validated the SAML response and fired its LoginEvent.
 *
 * The login succeeds once every SpidAuthenticationSucceeded listener has
 * returned. Anything that throws before that, including a listener of
 * Laravel's own Login event, fails closed: the panel guard is logged out and
 * the SPID session cleared.
 */
class HandleSpidLogin
{
    use ResolvesPanel;

    /**
     * The guard login() was called on, so a failure can undo it. Set before
     * the call: the guard may hold the user even if login() throws.
     */
    protected ?string $attemptedGuard = null;

    /**
     * The redirect fail() threw: the only abort let through as-is. Any other
     * SpidLoginAborted (an observer can construct one) is a failure.
     */
    protected ?SpidLoginAborted $abort = null;

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
            // citizen lands on Filament's 403 with a live session on the
            // guard. As Filament does, a model without FilamentUser is
            // admitted only in the local environment, and no panel means no
            // gate, so no login. It runs inside the provisioning transaction:
            // a refused citizen gets no account, an existing one keeps its data.
            $panel = $this->panel();

            try {
                $user = $this->userService->findOrCreateUser(
                    $spidData,
                    fn ($user): bool => $panel !== null && ($user instanceof FilamentUser
                        ? $user->canAccessPanel($panel)
                        : config('app.env') === 'local'),
                );
            } catch (SpidAccessDeniedException) {
                $this->fail('the user may not access the panel', $event, 'access_denied');
            }

            if (! $user) {
                // auto_create_users is off and no account matches this fiscal code.
                $this->fail('no user matches the SPID fiscal code', $event, 'authentication_failed');
            }

            // No remember token: the session ends with the browser one, unless
            // the application configures its session to outlive it.
            $this->attemptedGuard = $guard;
            Auth::guard($guard)->login($user);

            Session::regenerate();

            event(new SpidAuthenticationSucceeded($user, $spidData));

            $this->attemptedGuard = null;
        } catch (\Throwable $e) {
            if ($e === $this->abort) {
                throw $e;
            }

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
        // Cleanup first: an observer below must not be able to prevent it.
        $this->revokeAuthentication();
        $this->forgetSpidSession();

        Log::error('SPID login failed ('.$event->getIdp().'): '.$reason);

        try {
            event(new SpidAuthenticationFailed($reason));
        } catch (\Throwable $e) {
            // Reported, but it neither replaces the original failure nor
            // re-enters this method.
            Log::error('SPID login failure listener threw '.$e::class.' (code '.$e->getCode().')');
        }

        throw $this->abort = new SpidLoginAborted(
            redirect()->to($this->loginUrl())
                ->with('spid_error', __("filament-spid::spid.{$translationKey}"))
        );
    }

    /**
     * Undo a login() that was attempted on the panel guard. Other guards are
     * left alone; panels sharing a guard share its session too.
     */
    protected function revokeAuthentication(): void
    {
        if ($this->attemptedGuard === null) {
            return;
        }

        $guard = Auth::guard($this->attemptedGuard);
        $this->attemptedGuard = null;

        try {
            if ($guard instanceof SessionGuard) {
                // Without Logout listeners, which could throw in turn.
                Session::forget($guard->getName());
                $guard->forgetUser();
            } else {
                $guard->logout();
            }
        } catch (\Throwable $e) {
            Log::error('SPID login could not log the guard out: '.$e::class);
        }
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
