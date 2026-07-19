<?php

use Italia\SPIDAuth\SPIDAuth;
use Mockery as m;
use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;

it('login redirects to the panel login page', function () {
    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login');

    $response->assertRedirect(route('filament.admin.auth.login'));
});

it('login redirects to the panel login page even when a provider is supplied', function () {
    // The SAML handshake is triggered by the button posting to spid-auth_do-login,
    // so the provider query string is irrelevant here.
    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login?provider=poste');

    $response->assertRedirect(route('filament.admin.auth.login'));
});

it('logout succeeds and redirects to login', function () {
    $this->setupFakeFilamentPanel();

    $spidMock = m::mock(SPIDAuth::class);
    $spidMock->shouldReceive('logout')->once();
    $this->app->instance(SPIDAuth::class, $spidMock);

    $this->app['router']->get('/admin/login', function () {
        return 'login';
    })->name('filament.admin.auth.login');

    $this->app['router']->get('/spid/logout', [SpidController::class, 'logout']);

    $response = $this->get('/spid/logout');

    $response->assertRedirect(route('filament.admin.auth.login'));
});

it('logout handles exception and still redirects to login', function () {
    $this->setupFakeFilamentPanel();

    $spidMock = m::mock(SPIDAuth::class);
    $spidMock->shouldReceive('logout')->once()->andThrow(new Exception('boom'));
    $this->app->instance(SPIDAuth::class, $spidMock);

    $this->app['router']->get('/admin/login', function () {
        return 'login';
    })->name('filament.admin.auth.login');

    $this->app['router']->get('/spid/logout', [SpidController::class, 'logout']);

    $response = $this->get('/spid/logout');

    $response->assertRedirect(route('filament.admin.auth.login'));
});

it('metadata returns xml on success', function () {
    $spidMock = m::mock(SPIDAuth::class);
    $spidMock->shouldReceive('getSPMetadata')->once()->andReturn('<xml/>');
    $this->app->instance(SPIDAuth::class, $spidMock);

    $this->app['router']->get('/spid/metadata', [SpidController::class, 'metadata']);

    $response = $this->get('/spid/metadata');

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('xml');
});
