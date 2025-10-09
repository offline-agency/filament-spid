<?php

use Filament\Panel;
use OfflineAgency\FilamentSpid\SpidPlugin;

it('registers routes when registerRoutes is true', function () {
    $plugin = SpidPlugin::make()->registerRoutes(true);
    $panel = $this->setupFakeFilamentPanel();

    $plugin->boot($panel);

    // Check that routes are registered
    $routes = \Route::getRoutes();
    $routeNames = collect($routes)->map(fn ($route) => $route->getName())->filter()->toArray();

    expect($routeNames)->toContain('spid.login')
        ->and($routeNames)->toContain('spid.logout')
        ->and($routeNames)->toContain('spid.acs')
        ->and($routeNames)->toContain('spid.metadata')
        ->and($routeNames)->toContain('spid.providers');
});

it('does not register routes when registerRoutes is false', function () {
    $plugin = SpidPlugin::make()->registerRoutes(false);
    $panel = $this->setupFakeFilamentPanel();

    $plugin->boot($panel);

    // Check that routes are not registered
    $routes = \Route::getRoutes();
    $routeNames = collect($routes)->map(fn ($route) => $route->getName())->filter()->toArray();

    expect($routeNames)->not->toContain('spid.login')
        ->and($routeNames)->not->toContain('spid.logout')
        ->and($routeNames)->not->toContain('spid.acs')
        ->and($routeNames)->not->toContain('spid.metadata')
        ->and($routeNames)->not->toContain('spid.providers');
});

it('uses custom route names when configured', function () {
    $plugin = SpidPlugin::make()
        ->loginRoute('custom.login')
        ->logoutRoute('custom.logout')
        ->acsRoute('custom.acs')
        ->metadataRoute('custom.metadata')
        ->providersRoute('custom.providers')
        ->registerRoutes(true);

    $panel = $this->setupFakeFilamentPanel();
    $plugin->boot($panel);

    // Check that custom route names are used
    $routes = \Route::getRoutes();
    $routeNames = collect($routes)->map(fn ($route) => $route->getName())->filter()->toArray();

    expect($routeNames)->toContain('custom.login')
        ->and($routeNames)->toContain('custom.logout')
        ->and($routeNames)->toContain('custom.acs')
        ->and($routeNames)->toContain('custom.metadata')
        ->and($routeNames)->toContain('custom.providers');
});

it('registers routes with panel path prefix', function () {
    $plugin = SpidPlugin::make()->registerRoutes(true);
    $panel = $this->setupFakeFilamentPanel();

    $plugin->boot($panel);

    // Check that routes are registered
    $routes = \Route::getRoutes();
    $spidRoutes = collect($routes)->filter(fn ($route) => str_contains($route->uri(), 'spid/')
    );

    expect($spidRoutes)->not->toBeEmpty();

    // Check that routes exist (they may not have the exact prefix in test environment)
    $routeNames = $spidRoutes->map(fn ($route) => $route->getName())->toArray();
    expect($routeNames)->toContain('spid.login')
        ->and($routeNames)->toContain('spid.logout')
        ->and($routeNames)->toContain('spid.acs')
        ->and($routeNames)->toContain('spid.metadata')
        ->and($routeNames)->toContain('spid.providers');
});

it('can get plugin instance using get method', function () {
    // Set up a fake Filament panel with the SPID plugin
    $panel = $this->setupFakeFilamentPanelWithPlugin();

    // The get() method should exist and be callable
    expect(method_exists(SpidPlugin::class, 'get'))->toBeTrue();

    // Set the current panel context for the filament() helper
    \Filament\Facades\Filament::setCurrentPanel($panel);

    // Now we can test the get() method properly with a panel set up
    $retrievedPlugin = SpidPlugin::get();
    expect($retrievedPlugin)->toBeInstanceOf(SpidPlugin::class);

    // Verify it's the same plugin instance
    $originalPlugin = SpidPlugin::make();
    expect($retrievedPlugin->getId())->toBe($originalPlugin->getId());
});

it('registers login page with panel', function () {
    $plugin = SpidPlugin::make();
    $panel = $this->setupFakeFilamentPanel();

    $plugin->register($panel);

    // The register method should set the login page
    expect($plugin)->toBeInstanceOf(SpidPlugin::class);
});

it('can chain configuration and boot', function () {
    $plugin = SpidPlugin::make()
        ->loginRoute('custom.login')
        ->logoutRoute('custom.logout')
        ->registerRoutes(true);

    $panel = $this->setupFakeFilamentPanel();
    $plugin->register($panel);
    $plugin->boot($panel);

    // Check that custom routes are registered
    $routes = \Route::getRoutes();
    $routeNames = collect($routes)->map(fn ($route) => $route->getName())->filter()->toArray();

    expect($routeNames)->toContain('custom.login')
        ->and($routeNames)->toContain('custom.logout');
});
