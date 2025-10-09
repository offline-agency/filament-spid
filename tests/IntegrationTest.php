<?php

use Filament\Panel;
use OfflineAgency\FilamentSpid\SpidPlugin;

describe('Plugin Integration', function () {
    it('can be registered with panel', function () {
        $plugin = SpidPlugin::make();
        $panel = Panel::make()->id('admin');

        $plugin->register($panel);

        expect($plugin)->toBeInstanceOf(SpidPlugin::class);
    });

    it('plugin has correct id', function () {
        $plugin = SpidPlugin::make();

        expect($plugin->getId())->toBe('spid');
    });

    it('plugin can be made statically', function () {
        $plugin = SpidPlugin::make();

        expect($plugin)->toBeInstanceOf(SpidPlugin::class);
    });
});

describe('Configuration Integration', function () {
    it('uses configured providers when set', function () {
        config(['filament-spid.providers' => ['posteid', 'arubaid']]);

        $providers = config('filament-spid.providers');

        expect($providers)->toBe(['posteid', 'arubaid']);
    });

    it('field mapping works with real spid data structure', function () {
        $mapping = config('filament-spid.field_mapping');
        $spidUser = [
            'spidCode' => 'AGID-001',
            'name' => 'Mario',
            'familyName' => 'Rossi',
            'fiscalNumber' => 'RSSMRA80A01H501U',
            'email' => 'mario.rossi@example.com',
        ];

        $name = $mapping['name']($spidUser);
        $email = $mapping['email']($spidUser);
        $fiscalCode = $mapping['fiscal_code']($spidUser);

        expect($name)->toBe('Mario Rossi')
            ->and($email)->toBe('mario.rossi@example.com')
            ->and($fiscalCode)->toBe('RSSMRA80A01H501U');
    });

    it('configuration has all expected keys', function () {
        $config = config('filament-spid');

        expect($config)->toHaveKeys([
            'user_model',
            'redirect_after_login',
            'spid_level',
            'auto_create_users',
            'update_user_data',
            'providers',
            'field_mapping',
            'create_user_callback',
            'update_user_callback',
        ]);
    });
});

describe('View Integration', function () {
    it('login view file exists', function () {
        $viewPath = __DIR__.'/../resources/views/login.blade.php';

        expect(file_exists($viewPath))->toBeTrue();
    });

    it('login view contains spid elements', function () {
        $viewPath = __DIR__.'/../resources/views/login.blade.php';
        $content = file_get_contents($viewPath);

        expect($content)->toContain('provider')
            ->and($content)->toContain('SPID');
    });
});

describe('Asset Integration', function () {
    it('css file exists', function () {
        $cssPath = __DIR__.'/../resources/dist/filament-spid.css';

        expect(file_exists($cssPath))->toBeTrue();
    });

    it('js file exists', function () {
        $jsPath = __DIR__.'/../resources/dist/filament-spid.js';

        expect(file_exists($jsPath))->toBeTrue();
    });

    it('css file is readable', function () {
        $cssPath = __DIR__.'/../resources/dist/filament-spid.css';

        expect(is_readable($cssPath))->toBeTrue();
    });

    it('js file is readable', function () {
        $jsPath = __DIR__.'/../resources/dist/filament-spid.js';

        expect(is_readable($jsPath))->toBeTrue();
    });
});
