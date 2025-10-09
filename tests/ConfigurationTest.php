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

    it('has redirect_after_login configuration', function () {
        $redirect = config('filament-spid.redirect_after_login');

        expect($redirect)->toBeString();
    });

    it('has spid_level configuration', function () {
        $level = config('filament-spid.spid_level');

        expect($level)->toBeString()
            ->and($level)->toContain('https://www.spid.gov.it/SpidL');
    });

    it('has auto_create_users configuration', function () {
        $autoCreate = config('filament-spid.auto_create_users');

        expect($autoCreate)->toBeBool();
    });

    it('has update_user_data configuration', function () {
        $updateData = config('filament-spid.update_user_data');

        expect($updateData)->toBeBool();
    });

    it('has providers configuration', function () {
        $providers = config('filament-spid.providers');

        expect($providers)->toBeArray();
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

    it('field_mapping email handles missing email', function () {
        $mapping = config('filament-spid.field_mapping');
        $spidUser = [
            'name' => 'Mario',
            'familyName' => 'Rossi',
            'fiscalNumber' => 'RSSMRA80A01H501U',
        ];

        $email = $mapping['email']($spidUser);

        expect($email)->toBe('RSSMRA80A01H501U@spid.local');
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
