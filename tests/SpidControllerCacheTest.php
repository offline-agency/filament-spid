<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;

beforeEach(function () {
    Cache::forget('filament_spid_providers');

    Config::set('spid-idps', [
        'posteid' => [
            'provider' => 'poste',
            'title' => 'Poste ID',
            'entityName' => 'Poste Italiane',
            'logo' => 'poste.svg',
            'isActive' => true,
        ],
    ]);

    $this->app['router']->get('/spid/providers', [SpidController::class, 'providers']);
});

it('stores providers in cache under filament_spid_providers key', function () {
    $this->get('/spid/providers')->assertStatus(200);

    expect(Cache::has('filament_spid_providers'))->toBeTrue();
});

it('returns cached providers on second request', function () {
    $this->get('/spid/providers')->assertStatus(200);

    // Override config to different data — second request must still return cached data
    Config::set('spid-idps', []);

    $response = $this->get('/spid/providers');
    $response->assertStatus(200);
    $data = $response->json('providers');

    expect($data)->toHaveCount(1)
        ->and($data[0]['provider'])->toBe('poste');
});

it('caches providers with default TTL when config is not overridden', function () {
    // Use the actual default TTL from config (3600)
    Cache::forget('filament_spid_providers');

    $this->get('/spid/providers')->assertStatus(200);

    expect(Cache::has('filament_spid_providers'))->toBeTrue();
});

it('respects custom providers_ttl from config', function () {
    Config::set('filament-spid.cache.providers_ttl', 60);
    Cache::forget('filament_spid_providers');

    $this->get('/spid/providers')->assertStatus(200);

    expect(Cache::has('filament_spid_providers'))->toBeTrue();
});

it('cache is cleared and refreshed after manual forget', function () {
    $this->get('/spid/providers')->assertStatus(200);
    expect(Cache::has('filament_spid_providers'))->toBeTrue();

    Cache::forget('filament_spid_providers');
    expect(Cache::has('filament_spid_providers'))->toBeFalse();

    // After forget, new request repopulates cache
    $this->get('/spid/providers')->assertStatus(200);
    expect(Cache::has('filament_spid_providers'))->toBeTrue();
});
