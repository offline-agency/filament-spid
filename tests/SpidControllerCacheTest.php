<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;

beforeEach(function () {
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

it('does not cache the providers list', function () {
    $this->get('/spid/providers')->assertStatus(200);

    expect(Cache::has('filament_spid_providers'))->toBeFalse();
});

it('reflects a configuration change immediately', function () {
    expect($this->get('/spid/providers')->json('providers'))->toHaveCount(1);

    Config::set('spid-idps', []);

    expect($this->get('/spid/providers')->json('providers'))->toBeEmpty();
});

it('skips inactive providers and the empty placeholder', function () {
    Config::set('spid-idps', [
        'empty' => ['isActive' => true],
        'posteid' => ['provider' => 'poste', 'isActive' => true],
        'timid' => ['provider' => 'tim', 'isActive' => false],
    ]);

    $providers = collect($this->get('/spid/providers')->json('providers'))->pluck('provider');

    expect($providers->all())->toBe(['poste']);
});
