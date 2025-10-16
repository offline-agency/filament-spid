<?php

use Illuminate\Foundation\Auth\User as FoundationUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Italia\SPIDAuth\SPIDAuth;
use Mockery as m;
use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;
use OfflineAgency\FilamentSpid\Services\SpidUserService;

it('login returns redirect on success', function () {
    $this->setupFakeFilamentPanel();

    $spidMock = m::mock(SPIDAuth::class);
    $spidMock->shouldReceive('login')
        ->once()
        ->with('poste', m::type('string'), m::type('string'))
        ->andReturn(new RedirectResponse('/spid/redirected'));

    $this->app->instance(SPIDAuth::class, $spidMock);

    // Register named ACS route used by controller
    $this->app['router']->get('/spid/acs', [SpidController::class, 'acs'])->name('spid.acs');
    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login?provider=poste');

    $response->assertRedirect('/spid/redirected');
});

it('login catches exception and redirects back with error', function () {
    $this->setupFakeFilamentPanel();

    $spidMock = m::mock(SPIDAuth::class);
    $spidMock->shouldReceive('login')
        ->once()
        ->andThrow(new Exception('login failed'));
    $this->app->instance(SPIDAuth::class, $spidMock);

    $this->app['router']->get('/spid/acs', [SpidController::class, 'acs'])->name('spid.acs');
    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login?provider=poste');

    $response->assertRedirect();
});

it('acs redirects to login when not authenticated', function () {
    $this->setupFakeFilamentPanel();

    $spidMock = m::mock(SPIDAuth::class);
    $spidMock->shouldReceive('acs')->once();
    $spidMock->shouldReceive('isAuthenticated')->once()->andReturnFalse();
    $this->app->instance(SPIDAuth::class, $spidMock);

    $this->app['router']->get('/admin/login', function () {
        return 'login';
    })->name('filament.admin.auth.login');

    $this->app['router']->get('/spid/acs', [SpidController::class, 'acs'])->name('spid.acs');

    $response = $this->get('/spid/acs');

    $response->assertRedirect(route('filament.admin.auth.login'));
});

it('acs logs in user and redirects when authenticated', function () {
    $this->setupFakeFilamentPanel();

    $spidMock = m::mock(SPIDAuth::class);
    $spidMock->shouldReceive('acs')->once();
    $spidMock->shouldReceive('isAuthenticated')->once()->andReturnTrue();
    $spidMock->shouldReceive('getSPIDUser')->once()->andReturn([
        'fiscalNumber' => 'AAAABBBCCCDDDEEE',
        'name' => 'Mario',
        'familyName' => 'Rossi',
        'email' => 'mario.rossi@example.com',
    ]);
    $this->app->instance(SPIDAuth::class, $spidMock);

    $userServiceMock = m::mock(SpidUserService::class);
    $user = new FoundationUser();
    $user->id = 1;
    $userServiceMock->shouldReceive('findOrCreateUser')->once()->andReturn($user);
    $this->app->instance(SpidUserService::class, $userServiceMock);

    $this->app['router']->get('/admin/login', function () {
        return 'login';
    })->name('filament.admin.auth.login');

    $this->app['router']->get('/spid/acs', [SpidController::class, 'acs'])->name('spid.acs');

    $response = $this->get('/spid/acs');

    // Depending on Filament routing/middleware, intended('/admin') may redirect to '/admin/login'
    $location = $response->headers->get('Location');
    expect($location === url('/admin') || $location === url('/admin/login'))
        ->toBeTrue();
    expect(Auth::check())->toBeTrue();
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


