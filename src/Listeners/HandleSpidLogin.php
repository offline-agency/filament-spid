<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Listeners;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Italia\SPIDAuth\Events\LoginEvent;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationSucceeded;
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
        try {
            $spidData = SpidUserData::fromSpidAuth($event->getSPIDUser());

            $user = $this->userService->findOrCreateUser($spidData);

            if (! $user) {
                // auto_create_users is off and no account matches this fiscal code.
                $this->fail('no user matches the SPID fiscal code', $event, 'authentication_failed');
            }

            // No remember token: a SPID session must not outlive the browser one.
            Auth::guard($this->guard())->login($user);

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

        throw new HttpResponseException(
            redirect()->to($this->loginUrl())
                ->with('spid_error', __("filament-spid::spid.{$translationKey}"))
        );
    }
}
