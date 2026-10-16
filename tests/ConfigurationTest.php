<?php

use OfflineAgency\FilamentSpid\Mapping\FieldMapper;

/**
 * Map one column through the default filament-spid.field_mapping.
 *
 * @param  array<string, mixed>  $spidUser
 */
function mapField(string $column, array $spidUser): mixed
{
    return FieldMapper::value(config("filament-spid.field_mapping.{$column}"), $spidUser);
}

describe('Configuration', function () {
    it('has user_model configuration', function () {
        $userModel = config('filament-spid.user_model');

        expect($userModel)->toBeString();

        // In test environment, check if the configured model exists
        // Default is App\Models\User which may not exist in package tests
        if (str_contains($userModel, 'Illuminate\\Foundation\\Auth\\User')) {
            expect(class_exists($userModel))->toBeTrue();
        }
    });

    it('does not create users from SPID by default', function () {
        // Any citizen with a SPID identity could otherwise provision an
        // account on an admin panel.
        expect(config('filament-spid.auto_create_users'))->toBeFalse();
    });

    it('defines no keys the package does not read', function (string $key) {
        expect(config('filament-spid'))->not->toHaveKey($key);
    })->with(['redirect_after_login', 'spid_level', 'providers']);

    it('has update_user_data configuration', function () {
        $updateData = config('filament-spid.update_user_data');

        expect($updateData)->toBeBool();
    });

    it('has field_mapping configuration', function () {
        $mapping = config('filament-spid.field_mapping');

        expect($mapping)->toBeArray()
            ->and($mapping)->toHaveKeys(['name', 'email', 'fiscal_code', 'password']);
    });

    it('can be cached with php artisan config:cache', function () {
        // config:cache writes the config with var_export, which cannot
        // represent closures: a closure here breaks `php artisan optimize`.
        $config = config('filament-spid');
        $restored = eval('return '.var_export($config, true).';');

        expect($restored)->toBe($config);
    });

    it('field_mapping defaults map the SPID attributes', function () {
        $spidUser = [
            'name' => 'Mario',
            'familyName' => 'Rossi',
            'email' => 'mario@example.com',
            'fiscalNumber' => 'RSSMRA80A01H501U',
        ];

        expect(mapField('name', $spidUser))->toBe('Mario Rossi')
            ->and(mapField('email', $spidUser))->toBe('mario@example.com')
            ->and(mapField('fiscal_code', $spidUser))->toBe('RSSMRA80A01H501U');
    });

    it('field_mapping still accepts closures', function () {
        expect(FieldMapper::value(fn (array $spidUser) => strtoupper($spidUser['name']), ['name' => 'Mario']))->toBe('MARIO');
    });

    it('falls back to an undeliverable per-citizen email when SPID sends none', function () {
        // .invalid is reserved (RFC 2606): it never resolves, so the address
        // can neither receive mail nor collide with a real one.
        $email = mapField('email', ['fiscalNumber' => 'RSSMRA80A01H501U']);

        expect($email)->toBe('rssmra80a01h501u@spid.invalid');
    });

    it('gives each citizen a different fallback email, the same on every login', function () {
        $email = fn (array $spidUser) => mapField('email', $spidUser);

        expect($email(['fiscalNumber' => 'RSSMRA80A01H501U']))
            ->toBe($email(['fiscalNumber' => 'RSSMRA80A01H501U']))
            ->not->toBe($email(['fiscalNumber' => 'VRDLGI85B02F205X']));
    });

    it('keeps the email SPID provides', function () {
        $email = mapField('email', ['fiscalNumber' => 'RSSMRA80A01H501U', 'email' => 'mario@example.com']);

        expect($email)->toBe('mario@example.com');
    });

    it('has create_user_callback configuration', function () {
        $callback = config('filament-spid.create_user_callback');

        expect($callback)->toBeNull();
    });

    it('has update_user_callback configuration', function () {
        $callback = config('filament-spid.update_user_callback');

        expect($callback)->toBeNull();
    });
});

describe('SPID Configuration', function () {
    it('has spid-idps configuration', function () {
        $idps = config('spid-idps');

        expect($idps)->toBeArray()
            ->and($idps)->not->toBeEmpty();
    });

    it('spid-idps contains common providers', function () {
        $idps = config('spid-idps');

        expect($idps)->toHaveKeys(['infocert', 'poste', 'tim']);
    });

    it('each provider has required fields', function () {
        $idps = config('spid-idps');

        foreach ($idps as $key => $idp) {
            if ($key === 'empty') {
                continue;
            }

            expect($idp)->toHaveKeys(['entityId', 'singleSignOnService']);
        }
    });
});
