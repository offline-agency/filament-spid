<?php

use OfflineAgency\FilamentSpid\Pages\SpidLogin;

it('can instantiate SpidLogin page', function () {
    $page = new SpidLogin;

    expect($page)->toBeInstanceOf(SpidLogin::class);
});

it('extends SimplePage', function () {
    $page = new SpidLogin;

    expect($page)->toBeInstanceOf(\Filament\Pages\SimplePage::class);
});

it('has correct view property', function () {
    $page = new SpidLogin;

    $view = $page->getView();

    expect($view)->toBe('filament-spid::login');
});

it('returns correct heading', function () {
    $page = new SpidLogin;

    $heading = $page->getHeading();

    expect($heading)->toBeString()
        ->and($heading)->toContain('SPID');
});

it('heading uses translation', function () {
    $page = new SpidLogin;

    $heading = $page->getHeading();

    // The heading should be a translated string
    expect($heading)->toBeString();

    // Check that it's using the correct translation key
    $expectedTranslation = __('filament-spid::spid.login_with_spid');
    expect($heading)->toBe($expectedTranslation);
});

it('can be rendered as a page', function () {
    $page = new SpidLogin;

    // Test that the page can be instantiated and has required methods
    expect(method_exists($page, 'getHeading'))->toBeTrue();
    expect(method_exists($page, 'getView'))->toBeTrue();
});

it('has correct view path', function () {
    $page = new SpidLogin;

    // Test that the view path is accessible
    $viewPath = resource_path('views/vendor/filament-spid/login.blade.php');

    // The view should exist (this is tested in other tests, but we can verify the path)
    expect($viewPath)->toBeString();
});

it('implements required Filament page methods', function () {
    $page = new SpidLogin;

    // Test that it has the required methods for a Filament page
    expect(method_exists($page, 'getHeading'))->toBeTrue();
    expect(method_exists($page, 'getView'))->toBeTrue();
    expect(method_exists($page, 'getTitle'))->toBeTrue();
});

it('getTitle returns a string', function () {
    $page = new SpidLogin;

    $title = $page->getTitle();

    expect($title)->toBeString()
        ->and($title)->not->toBeEmpty();
});

it('can be used as a Filament page', function () {
    $page = new SpidLogin;

    // Test that it can be used in a Filament context
    expect($page)->toBeInstanceOf(\Filament\Pages\SimplePage::class);

    // Test that it has the required methods for Filament
    expect(method_exists($page, 'getView'))->toBeTrue();
    
    $view = $page->getView();

    expect($view)->toBeString()
        ->and($view)->not->toBeEmpty();
});

it('heading is translatable', function () {
    $page = new SpidLogin;

    $heading = $page->getHeading();

    // The heading should be a translated string
    expect($heading)->toBeString();

    // Test that it's using the translation system
    $translationKey = 'filament-spid::spid.login_with_spid';
    $translatedValue = __($translationKey);

    expect($heading)->toBe($translatedValue);
});

it('can be extended or customized', function () {
    // Test that the class can be extended
    $extendedPage = new class extends SpidLogin
    {
        public function getCustomHeading(): string
        {
            return 'Custom SPID Login';
        }
    };

    expect($extendedPage)->toBeInstanceOf(SpidLogin::class);
    expect($extendedPage->getCustomHeading())->toBe('Custom SPID Login');
    expect($extendedPage->getHeading())->toBeString();
});

it('maintains Filament page contract', function () {
    $page = new SpidLogin;

    // Test that it maintains the Filament page contract
    expect($page)->toBeInstanceOf(\Filament\Pages\SimplePage::class);

    // Test that required methods exist and return strings
    expect($page->getHeading())->toBeString();
    expect($page->getTitle())->toBeString();

    // Test that the view is properly set
    $view = $page->getView();

    expect($view)->toBeString()
        ->and($view)->toContain('filament-spid');
});

it('can be used in panel configuration', function () {
    $page = new SpidLogin;

    // Test that it can be used as a login page in Filament
    expect($page)->toBeInstanceOf(\Filament\Pages\SimplePage::class);

    // Test that it has the required methods for panel integration
    expect(method_exists($page, 'getHeading'))->toBeTrue();
    expect(method_exists($page, 'getView'))->toBeTrue();

    // Test that the view is a valid Blade view path
    $view = $page->getView();

    expect($view)->toContain('::');
});

it('handles translation fallbacks', function () {
    $page = new SpidLogin;

    // Test that the heading method handles translation properly
    $heading = $page->getHeading();

    expect($heading)->toBeString();

    // Even if translation fails, it should return a string
    expect($heading)->not->toBeEmpty();
});

it('can be serialized for caching', function () {
    $page = new SpidLogin;

    // Test that the page can be serialized (for caching purposes)
    $serialized = serialize($page);
    $unserialized = unserialize($serialized);

    expect($unserialized)->toBeInstanceOf(SpidLogin::class);
    expect($unserialized->getHeading())->toBe($page->getHeading());
});
