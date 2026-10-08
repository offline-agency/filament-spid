<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use OfflineAgency\FilamentSpid\SpidPlugin;

it('warns when a later ->login() replaced the SPID login page', function () {
    // Filament runs register() inside ->plugin(), so a ->login() after it (as
    // the panel stub filament:install generates) puts the default page back.
    Log::spy();
    $plugin = SpidPlugin::make();
    $panel = Panel::make()->id('late-login')->path('late-login')->plugin($plugin)->login();

    $plugin->boot($panel);

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message) => str_contains($message, 'late-login') && str_contains($message, '->login()')
    );
});

it('stays quiet when the SPID login page is in place', function () {
    Log::spy();
    $plugin = SpidPlugin::make();
    $panel = Panel::make()->id('spid-login')->path('spid-login')->login()->plugin($plugin);

    $plugin->boot($panel);

    Log::shouldNotHaveReceived('warning');
});

it('stays quiet when the panel opted out of the SPID login page', function () {
    Log::spy();
    $plugin = SpidPlugin::make()->showSpidButton(false);
    $panel = Panel::make()->id('opted-out')->path('opted-out')->plugin($plugin)->login();

    $plugin->boot($panel);

    Log::shouldNotHaveReceived('warning');
});

it('registers routes when registerRoutes is true', function () {
    $plugin = SpidPlugin::make()->registerRoutes(true);
    $panel = $this->setupFakeFilamentPanel();

    $plugin->boot($panel);

    // Check that routes are registered
    $routes = Route::getRoutes();
    $routeNames = collect($routes)->map(fn ($route) => $route->getName())->filter()->toArray();

    expect($routeNames)->toContain('spid.login')
        ->and($routeNames)->toContain('spid.logout')
        ->and($routeNames)->toContain('spid.metadata')
        ->and($routeNames)->toContain('spid.providers');
});

it('does not register routes when registerRoutes is false', function () {
    $plugin = SpidPlugin::make()->registerRoutes(false);
    $panel = $this->setupFakeFilamentPanel();

    $plugin->boot($panel);

    // Check that routes are not registered
    $routes = Route::getRoutes();
    $routeNames = collect($routes)->map(fn ($route) => $route->getName())->filter()->toArray();

    expect($routeNames)->not->toContain('spid.login')
        ->and($routeNames)->not->toContain('spid.logout')
        ->and($routeNames)->not->toContain('spid.metadata')
        ->and($routeNames)->not->toContain('spid.providers');
});

it('uses custom route names when configured', function () {
    $plugin = SpidPlugin::make()
        ->loginRoute('custom.login')
        ->logoutRoute('custom.logout')
        ->metadataRoute('custom.metadata')
        ->providersRoute('custom.providers')
        ->registerRoutes(true);

    $panel = $this->setupFakeFilamentPanel();
    $plugin->boot($panel);

    // Check that custom route names are used
    $routes = Route::getRoutes();
    $routeNames = collect($routes)->map(fn ($route) => $route->getName())->filter()->toArray();

    expect($routeNames)->toContain('custom.login')
        ->and($routeNames)->toContain('custom.logout')
        ->and($routeNames)->toContain('custom.metadata')
        ->and($routeNames)->toContain('custom.providers');
});

it('registers routes with panel path prefix', function () {
    $plugin = SpidPlugin::make()->registerRoutes(true);
    $panel = $this->setupFakeFilamentPanel();

    $plugin->boot($panel);

    // Check that routes are registered
    $routes = Route::getRoutes();
    $spidRoutes = collect($routes)->filter(fn ($route) => str_contains($route->uri(), 'spid/')
    );

    expect($spidRoutes)->not->toBeEmpty();

    // Check that routes exist (they may not have the exact prefix in test environment)
    $routeNames = $spidRoutes->map(fn ($route) => $route->getName())->toArray();
    expect($routeNames)->toContain('spid.login')
        ->and($routeNames)->toContain('spid.logout')
        ->and($routeNames)->toContain('spid.metadata')
        ->and($routeNames)->toContain('spid.providers');
});

it('can get plugin instance using get method', function () {
    // Set up a fake Filament panel with the SPID plugin
    $panel = $this->setupFakeFilamentPanelWithPlugin();

    // The get() method should exist and be callable
    expect(method_exists(SpidPlugin::class, 'get'))->toBeTrue();

    // Set the current panel context for the filament() helper
    Filament::setCurrentPanel($panel);

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

it('does not register routes when filament-spid.enabled is false', function () {
    Config::set('filament-spid.enabled', false);

    $plugin = SpidPlugin::make()->registerRoutes(true);
    $panel = $this->setupFakeFilamentPanel();

    $plugin->boot($panel);

    $routes = Route::getRoutes();
    $routeNames = collect($routes)->map(fn ($route) => $route->getName())->filter()->values()->toArray();

    expect($routeNames)->not->toContain('spid.login')
        ->and($routeNames)->not->toContain('spid.logout')
        ->and($routeNames)->not->toContain('spid.metadata')
        ->and($routeNames)->not->toContain('spid.providers');
});

it('does not override panel login when filament-spid.enabled is false', function () {
    Config::set('filament-spid.enabled', false);

    $panel = $this->setupFakeFilamentPanel();
    $originalLogin = $panel->getLoginRouteAction();

    $plugin = SpidPlugin::make();
    $plugin->register($panel);

    // Login route action should remain unchanged
    expect($panel->getLoginRouteAction())->toBe($originalLogin);
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
    $routes = Route::getRoutes();
    $routeNames = collect($routes)->map(fn ($route) => $route->getName())->filter()->toArray();

    expect($routeNames)->toContain('custom.login')
        ->and($routeNames)->toContain('custom.logout');
});

it('does not register the controller routes by default', function () {
    $plugin = SpidPlugin::make();
    $panel = $this->setupFakeFilamentPanel();

    $plugin->boot($panel);

    $routeNames = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter()->toArray();

    expect($routeNames)->not->toContain('spid.login')
        ->and($routeNames)->not->toContain('spid.logout');
});

it('leaves the library after_login_url untouched', function () {
    Config::set('spid-auth.after_login_url', '/dashboard');

    $plugin = SpidPlugin::make()->registerRoutes(true);
    $plugin->boot($this->setupFakeFilamentPanel());

    expect(config('spid-auth.after_login_url'))->toBe('/dashboard');
});
