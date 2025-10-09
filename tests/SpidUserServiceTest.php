<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Services\SpidUserService;

beforeEach(function () {
    Config::set('filament-spid.auto_create_users', true);
    Config::set('filament-spid.update_user_data', true);
    \Illuminate\Database\Eloquent\Model::unguard();
    Config::set('filament-spid.field_mapping', [
        'name' => function ($spidUser) {
            return ($spidUser['name'] ?? '').' '.($spidUser['familyName'] ?? '');
        },
        'email' => function ($spidUser) {
            return $spidUser['email'] ?? ($spidUser['fiscalNumber'] ?? 'user').'@spid.local';
        },
        'fiscal_code' => function ($spidUser) {
            return $spidUser['fiscalNumber'];
        },
        // Provide password to satisfy NOT NULL in test users schema
        'password' => function () {
            return bcrypt('secret');
        },
    ]);
});

it('creates a new user when not existing', function () {
    $service = app(SpidUserService::class);

    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
    );

    $user = $service->findOrCreateUser($spidData);

    expect($user)->not->toBeNull()
        ->and($user->fiscal_code)->toBe('RSSMRA80A01H501U');
});

it('returns null when auto_create_users is false and user not found', function () {
    Config::set('filament-spid.auto_create_users', false);

    $service = app(SpidUserService::class);

    $spidData = new SpidUserData(
        fiscalNumber: 'NEWUSER123456789',
        name: 'New',
        familyName: 'User',
    );

    $user = $service->findOrCreateUser($spidData);

    expect($user)->toBeNull();
});

it('finds existing user by fiscal code', function () {
    $service = app(SpidUserService::class);

    // Create first
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
    );

    $user1 = $service->findOrCreateUser($spidData);

    // Find again
    $user2 = $service->findOrCreateUser($spidData);

    expect($user1->id)->toBe($user2->id);
});

it('updates existing user when update_user_data is true', function () {
    $service = app(SpidUserService::class);

    // Create user
    $spidData1 = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
    );

    $user = $service->findOrCreateUser($spidData1);
    $oldName = $user->name;

    // Update with new data
    $spidData2 = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Giuseppe',
        familyName: 'Rossi',
        email: 'giuseppe@example.com',
    );

    $updatedUser = $service->findOrCreateUser($spidData2);

    expect($updatedUser->id)->toBe($user->id)
        ->and($updatedUser->name)->not->toBe($oldName);
});

it('does not update user when update_user_data is false', function () {
    Config::set('filament-spid.update_user_data', false);

    $service = app(SpidUserService::class);

    // Create user
    $spidData1 = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
    );

    $user = $service->findOrCreateUser($spidData1);
    $originalName = $user->name;

    // Try to update with new data
    $spidData2 = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Giuseppe',
        familyName: 'Rossi',
        email: 'giuseppe@example.com',
    );

    $service->findOrCreateUser($spidData2);

    // Refresh from database
    $user->refresh();

    expect($user->name)->toBe($originalName);
});

it('uses create_user_callback when provided', function () {
    $called = false;

    Config::set('filament-spid.create_user_callback', function ($spidData) use (&$called) {
        $called = true;

        $userModel = config('spid-auth.user_model');

        return $userModel::create([
            'name' => 'Custom '.$spidData->name,
            'email' => $spidData->email ?? 'custom@example.com',
            'fiscal_code' => $spidData->fiscalNumber,
            'password' => bcrypt('custom'),
        ]);
    });

    $service = app(SpidUserService::class);

    $spidData = new SpidUserData(
        fiscalNumber: 'CUSTOM123456789',
        name: 'Test',
        familyName: 'User',
    );

    $user = $service->findOrCreateUser($spidData);

    expect($called)->toBeTrue()
        ->and($user->name)->toContain('Custom');
});

it('uses update_user_callback when provided', function () {
    $called = false;

    $service = app(SpidUserService::class);

    // Create user first
    $spidData1 = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
    );

    $user = $service->findOrCreateUser($spidData1);

    // Set update callback
    Config::set('filament-spid.update_user_callback', function ($user, $spidData) use (&$called) {
        $called = true;
        $user->update(['name' => 'Updated via callback']);
    });

    // Try to find/update again
    $service->findOrCreateUser($spidData1);

    expect($called)->toBeTrue();
});

it('stores spid_data as JSON', function () {
    $service = app(SpidUserService::class);

    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
        spidCode: 'SPID123',
        placeOfBirth: 'Rome',
        dateOfBirth: '1980-01-01',
        gender: 'M',
    );

    $user = $service->findOrCreateUser($spidData);

    expect($user->spid_data)->toBeString()
        ->and(json_decode($user->spid_data, true))->toBeArray()
        ->and(json_decode($user->spid_data, true)['fiscalNumber'])->toBe('RSSMRA80A01H501U');
});

it('dispatches SpidUserCreated event when creating user', function () {
    Event::fake();

    $service = app(SpidUserService::class);

    $spidData = new SpidUserData(
        fiscalNumber: 'NEWEVENT123456789',
        name: 'Event',
        familyName: 'Test',
    );

    $service->findOrCreateUser($spidData);

    Event::assertDispatched(\OfflineAgency\FilamentSpid\Events\SpidUserCreated::class);
});

it('dispatches SpidUserUpdated event when updating user', function () {
    Event::fake();

    $service = app(SpidUserService::class);

    // Create user first
    $spidData = new SpidUserData(
        fiscalNumber: 'UPDATEEVENT123456',
        name: 'Update',
        familyName: 'Test',
    );

    $service->findOrCreateUser($spidData);

    Event::assertDispatched(\OfflineAgency\FilamentSpid\Events\SpidUserCreated::class);

    // Find again (should trigger update)
    $service->findOrCreateUser($spidData);

    Event::assertDispatched(\OfflineAgency\FilamentSpid\Events\SpidUserUpdated::class);
});
