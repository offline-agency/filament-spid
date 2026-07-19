<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Events\SpidUserCreated;
use OfflineAgency\FilamentSpid\Events\SpidUserUpdated;
use OfflineAgency\FilamentSpid\Services\SpidUserService;
use OfflineAgency\FilamentSpid\Tests\Fixtures\User;
use OfflineAgency\FilamentSpid\Tests\Fixtures\UserWithSpidDataCast;

beforeEach(function () {
    Config::set('filament-spid.auto_create_users', true);
    Config::set('filament-spid.update_user_data', true);
    Model::unguard();
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

it('stores spid_data as JSON when the model does not cast it', function () {
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

it('stores spid_data as an array when the model casts it', function () {
    Config::set('filament-spid.user_model', UserWithSpidDataCast::class);

    $service = app(SpidUserService::class);

    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
    );

    $user = $service->findOrCreateUser($spidData);

    // The cast decodes on read, so a double-encoded value would come back as a string.
    expect($user->spid_data)->toBeArray()
        ->and($user->spid_data['fiscalNumber'])->toBe('RSSMRA80A01H501U');

    expect($user->fresh()->spid_data)->toBeArray();
});

it('stores spid_data as an array when the model casts it on update', function () {
    Config::set('filament-spid.user_model', UserWithSpidDataCast::class);

    $service = app(SpidUserService::class);

    $service->findOrCreateUser(new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
    ));

    $user = $service->findOrCreateUser(new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario.rossi@example.com',
    ));

    expect($user->fresh()->spid_data)->toBeArray()
        ->and($user->fresh()->spid_data['email'])->toBe('mario.rossi@example.com');
});

it('falls back to the spid-auth user model config key', function () {
    Config::set('filament-spid.user_model', null);
    Config::set('spid-auth.user_model', User::class);

    $service = app(SpidUserService::class);

    $user = $service->findOrCreateUser(new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
    ));

    expect($user)->toBeInstanceOf(User::class);
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

    Event::assertDispatched(SpidUserCreated::class);
});

it('does not dispatch any event when auto_create_users is false and user not found', function () {
    Event::fake();
    Config::set('filament-spid.auto_create_users', false);

    $service = app(SpidUserService::class);

    $spidData = new SpidUserData(
        fiscalNumber: 'NOEVT123456789',
        name: 'No',
        familyName: 'Event',
    );

    $result = $service->findOrCreateUser($spidData);

    expect($result)->toBeNull();
    Event::assertNotDispatched(SpidUserCreated::class);
    Event::assertNotDispatched(SpidUserUpdated::class);
});

it('does not dispatch SpidUserUpdated event when update_user_data is false', function () {
    Event::fake();
    Config::set('filament-spid.update_user_data', false);

    $service = app(SpidUserService::class);

    $spidData = new SpidUserData(
        fiscalNumber: 'NOUPD123456789',
        name: 'No',
        familyName: 'Update',
    );

    // Create first (SpidUserCreated is dispatched here)
    $service->findOrCreateUser($spidData);
    Event::assertDispatched(SpidUserCreated::class);

    // Second call — update_user_data=false, so no SpidUserUpdated
    $service->findOrCreateUser($spidData);
    Event::assertNotDispatched(SpidUserUpdated::class);
});

it('uses string property access when field mapper is not callable', function () {
    Config::set('filament-spid.field_mapping', [
        'fiscal_code' => 'fiscalNumber', // string, not callable
        'name' => function ($spidUser) {
            return ($spidUser['name'] ?? '').' '.($spidUser['familyName'] ?? '');
        },
        'email' => function ($spidUser) {
            return $spidUser['email'] ?? ($spidUser['fiscalNumber'] ?? 'user').'@spid.local';
        },
        'password' => function () {
            return bcrypt('secret');
        },
    ]);

    $service = app(SpidUserService::class);

    $spidData = new SpidUserData(
        fiscalNumber: 'STRMPR80A01H501U',
        name: 'String',
        familyName: 'Mapper',
        email: 'strmapper@example.com',
    );

    $user = $service->findOrCreateUser($spidData);

    expect($user)->not->toBeNull()
        ->and($user->fiscal_code)->toBe('STRMPR80A01H501U');
});

it('does not update fiscal_code when updating existing user', function () {
    $service = app(SpidUserService::class);

    $originalFiscalCode = 'ORGNL80A01H501U';

    $spidData1 = new SpidUserData(
        fiscalNumber: $originalFiscalCode,
        name: 'Original',
        familyName: 'User',
        email: 'original@example.com',
    );

    $user = $service->findOrCreateUser($spidData1);
    expect($user->fiscal_code)->toBe($originalFiscalCode);

    // Attempt update with same fiscal code but different name
    $spidData2 = new SpidUserData(
        fiscalNumber: $originalFiscalCode,
        name: 'Updated',
        familyName: 'User',
        email: 'updated@example.com',
    );

    $updatedUser = $service->findOrCreateUser($spidData2);
    $updatedUser->refresh();

    expect($updatedUser->fiscal_code)->toBe($originalFiscalCode)
        ->and($updatedUser->name)->toContain('Updated');
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

    Event::assertDispatched(SpidUserCreated::class);

    // Find again (should trigger update)
    $service->findOrCreateUser($spidData);

    Event::assertDispatched(SpidUserUpdated::class);
});
