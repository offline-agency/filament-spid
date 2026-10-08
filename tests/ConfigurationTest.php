<?php

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
            ->and($mapping)->toHaveKeys(['name', 'email', 'fiscal_code']);
    });

    it('field_mapping name is callable', function () {
        $mapping = config('filament-spid.field_mapping');

        expect($mapping['name'])->toBeCallable();
    });

    it('field_mapping email is callable', function () {
        $mapping = config('filament-spid.field_mapping');

        expect($mapping['email'])->toBeCallable();
    });

    it('field_mapping fiscal_code is callable', function () {
        $mapping = config('filament-spid.field_mapping');

        expect($mapping['fiscal_code'])->toBeCallable();
    });

    it('field_mapping functions work correctly', function () {
        $mapping = config('filament-spid.field_mapping');
        $spidUser = [
            'name' => 'Mario',
            'familyName' => 'Rossi',
            'email' => 'mario@example.com',
            'fiscalNumber' => 'RSSMRA80A01H501U',
        ];

        $name = $mapping['name']($spidUser);
        $email = $mapping['email']($spidUser);
        $fiscalCode = $mapping['fiscal_code']($spidUser);

        expect($name)->toBe('Mario Rossi')
            ->and($email)->toBe('mario@example.com')
            ->and($fiscalCode)->toBe('RSSMRA80A01H501U');
    });

    it('falls back to an undeliverable per-citizen email when SPID sends none', function () {
        // .invalid is reserved (RFC 2606): it never resolves, so the address
        // can neither receive mail nor collide with a real one.
        $email = config('filament-spid.field_mapping.email')(['fiscalNumber' => 'RSSMRA80A01H501U']);

        expect($email)->toBe('rssmra80a01h501u@spid.invalid');
    });

    it('gives each citizen a different fallback email, the same on every login', function () {
        $email = config('filament-spid.field_mapping.email');

        expect($email(['fiscalNumber' => 'RSSMRA80A01H501U']))
            ->toBe($email(['fiscalNumber' => 'RSSMRA80A01H501U']))
            ->not->toBe($email(['fiscalNumber' => 'VRDLGI85B02F205X']));
    });

    it('keeps the email SPID provides', function () {
        $email = config('filament-spid.field_mapping.email')(['fiscalNumber' => 'RSSMRA80A01H501U', 'email' => 'mario@example.com']);

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
