<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Italia\SPIDAuth\Events\LoginEvent;
use Italia\SPIDAuth\SPIDUser;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationSucceeded;
use OfflineAgency\FilamentSpid\Events\SpidUserCreated;
use OfflineAgency\FilamentSpid\Events\SpidUserUpdated;
use OfflineAgency\FilamentSpid\Listeners\HandleSpidLogin;

it('dispatches SpidAuthenticationFailed when provisioning fails', function () {
    Event::fake([SpidAuthenticationFailed::class]);
    Config::set('filament-spid.auto_create_users', false);

    try {
        app(HandleSpidLogin::class)->handle(new LoginEvent(new SPIDUser([
            'fiscalNumber' => ['TINIT-RSSMRA80A01H501U'],
        ]), 'Poste ID'));
    } catch (HttpResponseException $e) {
        // the listener aborts the request with a redirect
    }

    Event::assertDispatched(SpidAuthenticationFailed::class);
});

it('SpidAuthenticationFailed event has reason property', function () {
    Event::fake([SpidAuthenticationFailed::class]);
    Config::set('filament-spid.auto_create_users', false);

    try {
        app(HandleSpidLogin::class)->handle(new LoginEvent(new SPIDUser([
            'fiscalNumber' => ['TINIT-RSSMRA80A01H501U'],
        ]), 'Poste ID'));
    } catch (HttpResponseException $e) {
        // the listener aborts the request with a redirect
    }

    Event::assertDispatched(SpidAuthenticationFailed::class, function ($event) {
        return is_string($event->reason) && strlen($event->reason) > 0;
    });
});

it('SpidUserCreated event can be instantiated', function () {
    $user = new User;
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    $event = new SpidUserCreated($user, $spidData);

    expect($event->user)->toBe($user)
        ->and($event->spidData)->toBe($spidData);
});

it('SpidUserUpdated event can be instantiated', function () {
    $user = new User;
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    $event = new SpidUserUpdated($user, $spidData);

    expect($event->user)->toBe($user)
        ->and($event->spidData)->toBe($spidData);
});

it('SpidAuthenticationSucceeded event can be instantiated', function () {
    $user = new User;
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    $event = new SpidAuthenticationSucceeded($user, $spidData);

    expect($event->user)->toBe($user)
        ->and($event->spidData)->toBe($spidData);
});

it('SpidAuthenticationFailed event can be instantiated', function () {
    $event = new SpidAuthenticationFailed('Test error message');

    expect($event->reason)->toBe('Test error message');
});
