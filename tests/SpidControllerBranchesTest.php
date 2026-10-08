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

it('metadata returns the signed SP metadata built by italia/spid-laravel', function () {
    // Real SPIDAuth singleton: the library's default config ships a test SP
    // key and certificate, so the metadata is actually generated and signed.
    $this->app['router']->get('/spid/sp-metadata', [SpidController::class, 'metadata']);

    $response = $this->get('/spid/sp-metadata');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('xml')
        ->and($response->getContent())
        ->toContain('<md:EntityDescriptor')
        ->toContain('entityID="https://test.local"')
        ->toContain('<ds:Signature');
});

it('metadata is not found when the library does not expose it', function () {
    config()->set('spid-auth.expose_sp_metadata', false);
    $this->app['router']->get('/spid/sp-metadata', [SpidController::class, 'metadata']);

    $this->get('/spid/sp-metadata')->assertNotFound();
});
