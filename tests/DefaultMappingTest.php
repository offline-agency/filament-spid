<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Italia\SPIDAuth\Events\LoginEvent;
use Italia\SPIDAuth\SPIDUser;
use OfflineAgency\FilamentSpid\Listeners\HandleSpidLogin;
use OfflineAgency\FilamentSpid\Mapping\Contracts\CreateOnly;
use OfflineAgency\FilamentSpid\Mapping\RandomPassword;
use OfflineAgency\FilamentSpid\Tests\Fixtures\User;
use OfflineAgency\FilamentSpid\Tests\Fixtures\UserWithHashedPassword;

/**
 * The shipped field_mapping, untouched, against the stock users table
 * (password NOT NULL) that TestCase creates.
 */
function defaultMappingLogin(string $fiscalNumber, string $name = 'Mario'): void
{
    app(HandleSpidLogin::class)->handle(new LoginEvent(new SPIDUser([
        'fiscalNumber' => ["TINIT-{$fiscalNumber}"],
        'name' => [$name],
        'familyName' => ['Rossi'],
    ]), 'Poste ID'));
}

beforeEach(function () {
    Config::set('filament-spid.auto_create_users', true);
});

afterEach(function () {
    Str::createRandomStringsNormally();
});

it('creates a user on a NOT NULL password column with the shipped mapping', function () {
    defaultMappingLogin('RSSMRA80A01H501U');

    $user = User::where('fiscal_code', 'RSSMRA80A01H501U')->sole();

    expect(Hash::isHashed($user->password))->toBeTrue();
});

it('gives every created user a different password hash', function () {
    defaultMappingLogin('RSSMRA80A01H501U');
    auth()->logout();
    defaultMappingLogin('VRDLGI85B02F205X');

    expect(User::where('fiscal_code', 'RSSMRA80A01H501U')->value('password'))
        ->not->toBe(User::where('fiscal_code', 'VRDLGI85B02F205X')->value('password'));
});

it('keeps the password hash on later logins', function () {
    defaultMappingLogin('RSSMRA80A01H501U');
    $hash = User::sole()->password;
    auth()->logout();

    defaultMappingLogin('RSSMRA80A01H501U', 'Updated');

    expect(User::sole())
        ->name->toBe('Updated Rossi')
        ->password->toBe($hash);
});

it('hashes the random password once, with or without a hashed cast', function (string $model) {
    Config::set('filament-spid.user_model', $model);
    Str::createRandomStringsUsing(fn () => 'known-secret');

    defaultMappingLogin('RSSMRA80A01H501U');

    expect(Hash::check('known-secret', User::sole()->password))->toBeTrue();
})->with([
    'without a cast' => [User::class],
    'with a hashed cast' => [UserWithHashedPassword::class],
]);

it('marks the random password as a create-only mapper', function () {
    expect(new RandomPassword)->toBeInstanceOf(CreateOnly::class);
});
