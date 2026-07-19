<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use OfflineAgency\FilamentSpid\Http\Middleware\VerifyCsrfToken;

/**
 * The SAML assertion is posted by the Identity Provider, which cannot carry a
 * Laravel CSRF token, so the ACS route has to be exempted by the consumer app.
 * These tests pin what the package expects that app to do.
 */
it('leaves CSRF verification to the application', function () {
    expect(class_exists(VerifyCsrfToken::class))->toBeFalse();
});

it('does not bind a CSRF middleware of its own', function () {
    // Laravel 11+ ships ValidateCsrfToken in the web group; the deprecated
    // VerifyCsrfToken the package used to override is not part of it.
    expect($this->app->bound(Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class))->toBeFalse();
});

it('is the ValidateCsrfToken middleware that guards the web group', function () {
    $webGroup = $this->app['router']->getMiddlewareGroups()['web'] ?? [];

    expect($webGroup)->toContain(ValidateCsrfToken::class);
});

it('documents the ACS path the identity provider actually posts to', function () {
    // A 419 can never be asserted here: ValidateCsrfToken short-circuits under
    // runningUnitTests(). What can be pinned is that the path the README tells
    // applications to exclude is the one the library route resolves to.
    $acsPath = ltrim(parse_url(route('spid-auth_acs'), PHP_URL_PATH), '/');

    expect($acsPath)->toBe(trim(config('spid-auth.routes_prefix'), '/').'/acs')
        ->and(file_get_contents(__DIR__.'/../README.md'))->toContain("except: ['{$acsPath}']");
});
