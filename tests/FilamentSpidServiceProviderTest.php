<?php

use OfflineAgency\FilamentSpid\FilamentSpidServiceProvider;

describe('FilamentSpidServiceProvider', function () {
    it('is registered', function () {
        $provider = $this->app->getProvider(FilamentSpidServiceProvider::class);

        expect($provider)->toBeInstanceOf(FilamentSpidServiceProvider::class);
    });

    it('has correct package name', function () {
        expect(FilamentSpidServiceProvider::$name)->toBe('filament-spid');
    });

    it('has correct view namespace', function () {
        expect(FilamentSpidServiceProvider::$viewNamespace)->toBe('filament-spid');
    });

    it('loads views', function () {
        expect(view()->exists('filament-spid::login'))->toBeTrue();
    });

    it('loads translations', function () {
        $translation = __('filament-spid::spid.login_with_spid');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.login_with_spid');
    });

    it('publishes config file', function () {
        expect(config('filament-spid'))->toBeArray();
    });

    it('has migration', function () {
        $migrationPath = __DIR__.'/../database/migrations/add_spid_fields_to_users_table.php.stub';

        expect(file_exists($migrationPath))->toBeTrue();
    });
});

describe('FilamentSpidServiceProvider - Assets', function () {
    it('registers css asset', function () {
        $cssPath = __DIR__.'/../resources/dist/filament-spid.css';

        expect(file_exists($cssPath))->toBeTrue();
    });

    it('registers js asset', function () {
        $jsPath = __DIR__.'/../resources/dist/filament-spid.js';

        expect(file_exists($jsPath))->toBeTrue();
    });
});
