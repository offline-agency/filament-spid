<?php

use Illuminate\Support\ServiceProvider;
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

    it('ships styles in the registered css asset', function () {
        $css = file_get_contents(__DIR__.'/../resources/dist/filament-spid.css');

        expect(trim($css))->not->toBe('')
            ->and($css)->toContain('.spid-button-wrapper');
    });

    it('registers no js asset', function () {
        // The bundle was empty and loaded on every Filament page; the button
        // ships its own Blade-rendered script instead.
        expect(file_exists(__DIR__.'/../resources/dist/filament-spid.js'))->toBeFalse();
    });
});

describe('FilamentSpidServiceProvider - Publishing', function () {
    it('publishes images to the path the views reference', function () {
        $paths = ServiceProvider::pathsToPublish(FilamentSpidServiceProvider::class, 'filament-spid-images');

        expect(array_values($paths))->toContain(public_path('vendor/filament-spid/images'))
            ->and(array_values($paths))->not->toContain(public_path('images'));
    });

    it('publishes the spid-laravel config files', function () {
        $paths = ServiceProvider::pathsToPublish(FilamentSpidServiceProvider::class, 'filament-spid-config');

        expect(array_values($paths))->toContain(config_path().'/spid-auth.php')
            ->and(array_values($paths))->toContain(config_path().'/spid-idps.php');
    });
});
