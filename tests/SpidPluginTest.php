<?php

use OfflineAgency\FilamentSpid\SpidPlugin;

it('can instantiate plugin', function () {
    $plugin = SpidPlugin::make();

    expect($plugin)->toBeInstanceOf(SpidPlugin::class);
});

it('can set custom login route', function () {
    $plugin = SpidPlugin::make()->loginRoute('custom.login');

    expect($plugin->getLoginRoute())->toBe('custom.login');
});

it('can set custom logout route', function () {
    $plugin = SpidPlugin::make()->logoutRoute('custom.logout');

    expect($plugin->getLogoutRoute())->toBe('custom.logout');
});

it('can set custom acs route', function () {
    $plugin = SpidPlugin::make()->acsRoute('custom.acs');

    expect($plugin->getAcsRoute())->toBe('custom.acs');
});

it('can set custom metadata route', function () {
    $plugin = SpidPlugin::make()->metadataRoute('custom.metadata');

    expect($plugin->getMetadataRoute())->toBe('custom.metadata');
});

it('can set custom providers route', function () {
    $plugin = SpidPlugin::make()->providersRoute('custom.providers');

    expect($plugin->getProvidersRoute())->toBe('custom.providers');
});

it('can show spid button', function () {
    $plugin = SpidPlugin::make()->showSpidButton(true);

    expect($plugin->getShowSpidButton())->toBeTrue();
});

it('can hide spid button', function () {
    $plugin = SpidPlugin::make()->showSpidButton(false);

    expect($plugin->getShowSpidButton())->toBeFalse();
});

it('can set custom button label', function () {
    $plugin = SpidPlugin::make()->spidButtonLabel('Login con SPID');

    expect($plugin->getSpidButtonLabel())->toBe('Login con SPID');
});

it('can set custom button icon', function () {
    $plugin = SpidPlugin::make()->spidButtonIcon('heroicon-o-shield-check');

    expect($plugin->getSpidButtonIcon())->toBe('heroicon-o-shield-check');
});

it('can set custom login view', function () {
    $plugin = SpidPlugin::make()->loginView('custom.login');

    expect($plugin->getLoginView())->toBe('custom.login');
});

it('can set providers list', function () {
    $providers = ['posteid', 'arubaid', 'infocertid'];
    $plugin = SpidPlugin::make()->providers($providers);

    expect($plugin->getProviders())->toBe($providers);
});

it('can disable route registration', function () {
    $plugin = SpidPlugin::make()->registerRoutes(false);

    expect($plugin)->toBeInstanceOf(SpidPlugin::class);
});

it('has correct plugin id', function () {
    $plugin = SpidPlugin::make();

    expect($plugin->getId())->toBe('spid');
});

it('returns default button label when not set', function () {
    $plugin = SpidPlugin::make();

    expect($plugin->getSpidButtonLabel())->toBeString();
});

it('returns null icon when not set', function () {
    $plugin = SpidPlugin::make();

    expect($plugin->getSpidButtonIcon())->toBeNull();
});

it('returns empty array when providers not set', function () {
    $plugin = SpidPlugin::make();

    expect($plugin->getProviders())->toBe([]);
});

it('uses default routes when not customized', function () {
    $plugin = SpidPlugin::make();

    expect($plugin->getLoginRoute())->toBe('spid.login')
        ->and($plugin->getLogoutRoute())->toBe('spid.logout')
        ->and($plugin->getAcsRoute())->toBe('spid.acs')
        ->and($plugin->getMetadataRoute())->toBe('spid.metadata')
        ->and($plugin->getProvidersRoute())->toBe('spid.providers');
});

it('can chain multiple configuration methods', function () {
    $plugin = SpidPlugin::make()
        ->loginRoute('custom.login')
        ->logoutRoute('custom.logout')
        ->spidButtonLabel('Entra con SPID')
        ->providers(['posteid', 'arubaid']);

    expect($plugin->getLoginRoute())->toBe('custom.login')
        ->and($plugin->getLogoutRoute())->toBe('custom.logout')
        ->and($plugin->getSpidButtonLabel())->toBe('Entra con SPID')
        ->and($plugin->getProviders())->toBe(['posteid', 'arubaid']);
});
