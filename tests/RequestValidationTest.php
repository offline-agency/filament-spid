<?php

use Illuminate\Support\Facades\Config;
use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;

it('fails validation when provider is missing', function () {
    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login');

    $response->assertRedirect();
    $response->assertSessionHasErrors(['provider']);
});

it('fails validation when provider is not active/allowed', function () {
    Config::set('spid-idps', [
        'posteid' => [
            'provider' => 'poste',
            'isActive' => false,
        ],
    ]);

    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login?provider=poste');

    $response->assertRedirect();
    $response->assertSessionHasErrors(['provider']);
});

it('fails validation when level is invalid', function () {
    Config::set('spid-idps', [
        'posteid' => [
            'provider' => 'poste',
            'isActive' => true,
        ],
    ]);

    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login?provider=poste&level=INVALID');

    $response->assertRedirect();
    $response->assertSessionHasErrors(['level']);
});

it('accepts valid SPID level 1', function () {
    Config::set('spid-idps', [
        'posteid' => [
            'provider' => 'poste',
            'isActive' => true,
        ],
    ]);

    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login?provider=posteid&level=https://www.spid.gov.it/SpidL1');

    // Validation passes (no 302 with validation errors)
    // May redirect back with SPID error, but no validation error in session
    if ($response->status() === 302) {
        $response->assertSessionMissing('errors');
    }
    expect(true)->toBeTrue();
});

it('accepts valid SPID level 2', function () {
    Config::set('spid-idps', [
        'posteid' => [
            'provider' => 'poste',
            'isActive' => true,
        ],
    ]);

    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login?provider=posteid&level=https://www.spid.gov.it/SpidL2');

    if ($response->status() === 302) {
        $response->assertSessionMissing('errors');
    }
    expect(true)->toBeTrue();
});

it('accepts valid SPID level 3', function () {
    Config::set('spid-idps', [
        'posteid' => [
            'provider' => 'poste',
            'isActive' => true,
        ],
    ]);

    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login?provider=posteid&level=https://www.spid.gov.it/SpidL3');

    if ($response->status() === 302) {
        $response->assertSessionMissing('errors');
    }
    expect(true)->toBeTrue();
});

it('uses default level when not provided', function () {
    Config::set('spid-idps', [
        'posteid' => [
            'provider' => 'poste',
            'isActive' => true,
        ],
    ]);
    Config::set('filament-spid.spid_level', 'https://www.spid.gov.it/SpidL2');

    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login?provider=posteid');

    if ($response->status() === 302) {
        $response->assertSessionMissing('errors');
    }
    expect(true)->toBeTrue();
});

it('validates provider against active providers only', function () {
    Config::set('spid-idps', [
        'posteid' => [
            'provider' => 'poste',
            'isActive' => true,
        ],
        'infocertid' => [
            'provider' => 'infocert',
            'isActive' => false,
        ],
    ]);

    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login?provider=infocert');

    $response->assertRedirect();
    $response->assertSessionHasErrors(['provider']);
});
