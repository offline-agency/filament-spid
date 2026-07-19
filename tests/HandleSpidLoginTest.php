<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Italia\SPIDAuth\Events\LoginEvent;
use Italia\SPIDAuth\SPIDUser;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationSucceeded;
use OfflineAgency\FilamentSpid\Listeners\HandleSpidLogin;
use OfflineAgency\FilamentSpid\Tests\Fixtures\User;

function spidUser(array $overrides = []): SPIDUser
{
    return new SPIDUser(array_merge([
        'fiscalNumber' => ['TINIT-RSSMRA80A01H501U'],
        'name' => ['Mario'],
        'familyName' => ['Rossi'],
        'email' => ['mario.rossi@example.com'],
        'spidCode' => ['SPID123'],
    ], $overrides));
}

function loginEvent(array $overrides = []): LoginEvent
{
    return new LoginEvent(spidUser($overrides), 'Poste ID');
}

beforeEach(function () {
    Model::unguard();
    Config::set('filament-spid.field_mapping', [
        'name' => fn ($spidUser) => $spidUser['name'].' '.$spidUser['familyName'],
        'email' => fn ($spidUser) => $spidUser['email'] ?? $spidUser['fiscalNumber'].'@spid.local',
        'fiscal_code' => fn ($spidUser) => $spidUser['fiscalNumber'],
        'password' => fn () => bcrypt('secret'),
    ]);
});

it('provisions and authenticates the user on the panel guard', function () {
    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(Auth::guard('web')->check())->toBeTrue()
        ->and(Auth::guard('web')->user()->fiscal_code)->toBe('RSSMRA80A01H501U')
        ->and(User::where('fiscal_code', 'RSSMRA80A01H501U')->count())->toBe(1);
});

it('strips the TINIT prefix from the fiscal number', function () {
    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(User::first()->fiscal_code)->toBe('RSSMRA80A01H501U');
});

it('regenerates the session on login', function () {
    $before = session()->getId();

    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(session()->getId())->not->toBe($before);
});

it('dispatches SpidAuthenticationSucceeded', function () {
    Event::fake([SpidAuthenticationSucceeded::class]);

    app(HandleSpidLogin::class)->handle(loginEvent());

    Event::assertDispatched(SpidAuthenticationSucceeded::class);
});

it('does not set a remember cookie', function () {
    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(Auth::guard('web')->user()->getRememberToken())->toBeEmpty();
});

it('redirects to the panel login page when provisioning returns no user', function () {
    Config::set('filament-spid.auto_create_users', false);

    expect(fn () => app(HandleSpidLogin::class)->handle(loginEvent()))
        ->toThrow(HttpResponseException::class);

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('dispatches SpidAuthenticationFailed when provisioning fails', function () {
    Config::set('filament-spid.auto_create_users', false);
    Event::fake([SpidAuthenticationFailed::class]);

    try {
        app(HandleSpidLogin::class)->handle(loginEvent());
    } catch (HttpResponseException $e) {
        // expected
    }

    Event::assertDispatched(SpidAuthenticationFailed::class);
});

it('carries a flash error on the failure redirect', function () {
    Config::set('filament-spid.auto_create_users', false);

    try {
        app(HandleSpidLogin::class)->handle(loginEvent());
        $response = null;
    } catch (HttpResponseException $e) {
        $response = $e->getResponse();
    }

    expect($response)->not->toBeNull()
        ->and($response->getTargetUrl())->toContain('/admin/login')
        ->and(session()->get('spid_error'))->toBe(__('filament-spid::spid.authentication_failed'));
});

it('logs in an existing user without creating a duplicate', function () {
    User::create([
        'name' => 'Mario Rossi',
        'email' => 'mario.rossi@example.com',
        'password' => bcrypt('secret'),
        'fiscal_code' => 'RSSMRA80A01H501U',
    ]);

    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(User::count())->toBe(1)
        ->and(Auth::guard('web')->check())->toBeTrue();
});
