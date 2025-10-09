<?php

use Illuminate\Support\Facades\Event;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationSucceeded;
use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;

it('dispatches SpidAuthenticationFailed when not authenticated after ACS', function () {
    Event::fake();

    $this->app['router']->post('/spid/acs', [SpidController::class, 'acs']);

    // Without real SPID data, acs() will catch and emit failed event
    $this->post('/spid/acs');

    Event::assertDispatched(SpidAuthenticationFailed::class);
});

it('SpidAuthenticationFailed event has reason property', function () {
    Event::fake();

    $this->app['router']->post('/spid/acs', [SpidController::class, 'acs']);

    $this->post('/spid/acs');

    Event::assertDispatched(SpidAuthenticationFailed::class, function ($event) {
        return is_string($event->reason) && strlen($event->reason) > 0;
    });
});

it('SpidUserCreated event can be instantiated', function () {
    $user = new \Illuminate\Foundation\Auth\User();
    $spidData = new \OfflineAgency\FilamentSpid\DTOs\SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    $event = new \OfflineAgency\FilamentSpid\Events\SpidUserCreated($user, $spidData);

    expect($event->user)->toBe($user)
        ->and($event->spidData)->toBe($spidData);
});

it('SpidUserUpdated event can be instantiated', function () {
    $user = new \Illuminate\Foundation\Auth\User();
    $spidData = new \OfflineAgency\FilamentSpid\DTOs\SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    $event = new \OfflineAgency\FilamentSpid\Events\SpidUserUpdated($user, $spidData);

    expect($event->user)->toBe($user)
        ->and($event->spidData)->toBe($spidData);
});

it('SpidAuthenticationSucceeded event can be instantiated', function () {
    $user = new \Illuminate\Foundation\Auth\User();
    $spidData = new \OfflineAgency\FilamentSpid\DTOs\SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    $event = new \OfflineAgency\FilamentSpid\Events\SpidAuthenticationSucceeded($user, $spidData);

    expect($event->user)->toBe($user)
        ->and($event->spidData)->toBe($spidData);
});

it('SpidAuthenticationFailed event can be instantiated', function () {
    $event = new \OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed('Test error message');

    expect($event->reason)->toBe('Test error message');
});


