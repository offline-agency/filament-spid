<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Italia\SPIDAuth\Events\LogoutEvent;
use Italia\SPIDAuth\SPIDUser;
use OfflineAgency\FilamentSpid\Listeners\HandleSpidLogout;
use OfflineAgency\FilamentSpid\Tests\Fixtures\User;

function logoutEvent(): LogoutEvent
{
    return new LogoutEvent(new SPIDUser([
        'fiscalNumber' => ['TINIT-RSSMRA80A01H501U'],
        'name' => ['Mario'],
        'familyName' => ['Rossi'],
    ]), 'Poste ID');
}

beforeEach(function () {
    Model::unguard();
});

it('logs the user out of the panel guard', function () {
    $user = User::create([
        'name' => 'Mario Rossi',
        'email' => 'mario.rossi@example.com',
        'password' => bcrypt('secret'),
        'fiscal_code' => 'RSSMRA80A01H501U',
    ]);
    Auth::guard('web')->login($user);

    app(HandleSpidLogout::class)->handle(logoutEvent());

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('invalidates the session', function () {
    session()->put('spid_marker', 'present');
    $before = session()->getId();

    app(HandleSpidLogout::class)->handle(logoutEvent());

    expect(session()->has('spid_marker'))->toBeFalse()
        ->and(session()->getId())->not->toBe($before);
});

it('regenerates the CSRF token', function () {
    $before = session()->token();

    app(HandleSpidLogout::class)->handle(logoutEvent());

    expect(session()->token())->not->toBe($before);
});

it('is safe to run when nobody is authenticated', function () {
    app(HandleSpidLogout::class)->handle(logoutEvent());

    expect(Auth::guard('web')->check())->toBeFalse();
});
