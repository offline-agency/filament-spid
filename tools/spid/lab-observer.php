<?php

// Loaded only by the throwaway lab application. No SAML values, identities,
// cookies or keys leave this observer. It never changes authentication.
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Italia\SPIDAuth\Exceptions\SPIDLoginException;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed;

Event::listen(SpidAuthenticationFailed::class, function ($event): void {
    app()->instance('spid-lab.failure', $event->reason);
});
Event::listen(RequestHandled::class, function ($event): void {
    if ($event->request->is('admin', 'admin/login')) {
        $guard = Filament::getPanel('admin')->getAuthGuard();
        $event->response->headers->set('X-Spid-Lab-Authenticated', Auth::guard($guard)->check() ? '1' : '0');
        $event->response->headers->set('X-Spid-Lab-Spid-Session', session()->has('spid_user') ? '1' : '0');
    }
    if (! $event->request->is('spid/acs')) {
        return;
    }
    $exception = $event->response->exception ?? null;
    $reason = app()->bound('spid-lab.failure') ? app('spid-lab.failure') : null;
    if ($exception instanceof SPIDLoginException) {
        // Validation messages can contain XML/schema dumps: retain the first
        // line only, without markup, and cap its size.
        $reason = substr(strip_tags(explode("\n", $exception->getMessage())[0]), 0, 240);
    }
    if ($reason !== null) {
        $event->response->headers->set('X-Spid-Lab-Refusal', base64_encode($reason));
    }
});
