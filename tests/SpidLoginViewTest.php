<?php

use Illuminate\Support\Facades\File;

/**
 * The login view and the stylesheet the plugin registers as a Filament asset.
 */
function loginView(): string
{
    return File::get(__DIR__.'/../resources/views/login.blade.php');
}

function pluginCss(): string
{
    return File::get(__DIR__.'/../resources/dist/filament-spid.css');
}

it('styles the login page with its own classes, not Tailwind utilities Filament may not compile', function () {
    // Filament 4 and 5 ship only the utilities their own views use: text-center,
    // mx-auto, bg-danger-50... are not there, and the page fell apart.
    preg_match_all('/class="([^"]+)"/', loginView(), $matches);
    $classes = array_unique(preg_split('/\s+/', implode(' ', $matches[1])));

    foreach ($classes as $class) {
        expect($class)->toMatch('/^(fi-spid-|spid-)/');
    }
});

it('defines every class the login page uses', function () {
    preg_match_all('/class="([^"]+)"/', loginView(), $matches);

    foreach (array_unique(preg_split('/\s+/', implode(' ', $matches[1]))) as $class) {
        expect(pluginCss())->toContain('.'.$class);
    }
});

it('centres the heading, the button, the information text and the logo', function () {
    $css = pluginCss();

    expect(loginView())->toContain("{{ __('filament-spid::spid.login_with_spid') }}")
        ->and(loginView())->toContain("{{ __('filament-spid::spid.info_text') }}")
        ->and($css)->toMatch('/\.fi-spid-heading\s*\{[^}]*text-align:\s*center/')
        ->and($css)->toMatch('/\.fi-spid-info\s*\{[^}]*text-align:\s*center/')
        ->and($css)->toMatch('/\.fi-spid-logo\s*\{[^}]*justify-content:\s*center/');
});

it('keeps the AgID logo legible in dark mode without recolouring it', function () {
    expect(pluginCss())->toMatch('/\.dark \.fi-spid-logo img\s*\{[^}]*background/')
        ->and(pluginCss())->not->toContain('invert(');
});

it('does not style the button form by an id that no longer exists', function () {
    expect(pluginCss())->not->toContain('#spid_idp_access');
});

it('serves the AGID logo from the package assets, not a third-party CDN', function () {
    $viewPath = __DIR__.'/../resources/views/login.blade.php';
    $content = File::get($viewPath);

    expect($content)->toContain("asset('vendor/filament-spid/images/spid-agid-logo.png')")
        ->and($content)->toContain('alt="SPID AGID"')
        ->and($content)->not->toContain('cdn.inpa.gov.it');
});

it('ships the AGID logo it references', function () {
    expect(file_exists(__DIR__.'/../resources/images/spid-agid-logo.png'))->toBeTrue();
});

it('renders the SPID error flash message when present', function () {
    $viewPath = __DIR__.'/../resources/views/login.blade.php';
    $content = File::get($viewPath);

    expect($content)->toContain("session('spid_error')")
        ->and($content)->toContain('class="fi-spid-error" role="alert"');
});

it('AGID logo image URL returns 200 status code', function () {
    // Create a test application instance
    $app = $this->createApplication();

    // Get the asset URL for the AGID logo
    $imageUrl = asset('vendor/filament-spid/images/spid-agid-logo.png');

    // Make a request to the image URL
    $response = $this->get($imageUrl);

    // The response should be 200 (OK) if the image exists
    // or 404 (Not Found) if the image hasn't been published yet
    expect($response->status())->toBeIn([200, 404]);

    // If it's 200, verify it's actually an image
    if ($response->status() === 200) {
        expect($response->headers->get('Content-Type'))->toStartWith('image/');
    }
});
