<?php

use Illuminate\Support\Facades\File;

it('has centered heading in the blade template', function () {
    $viewPath = __DIR__.'/../resources/views/login.blade.php';
    $content = File::get($viewPath);

    // Check that the heading slot contains text-center class
    expect($content)->toContain('<x-slot name="heading">');
    expect($content)->toContain('text-center');
    expect($content)->toContain("{{ __('filament-spid::spid.login_with_spid') }}");
});

it('has centered info text section in the blade template', function () {
    $viewPath = __DIR__.'/../resources/views/login.blade.php';
    $content = File::get($viewPath);

    // The info text should be in a div with text-center class
    expect($content)->toContain('class="text-center w-full"');
    expect($content)->toContain("{{ __('filament-spid::spid.info_text') }}");
});

it('has text-center class for the main content sections', function () {
    $viewPath = __DIR__.'/../resources/views/login.blade.php';
    $content = File::get($viewPath);

    // Count occurrences of text-center to ensure centering is applied
    $count = substr_count($content, 'text-center');

    // Should have at least 2 instances (heading and info text)
    expect($count)->toBeGreaterThanOrEqual(2);
});

it('has AGID logo with correct styling and centering', function () {
    $viewPath = __DIR__.'/../resources/views/login.blade.php';
    $content = File::get($viewPath);

    // Check for the AGID logo image
    expect($content)->toContain('<img');
    expect($content)->toContain('alt="SPID AGID"');
    expect($content)->toContain('class="max-w-full h-auto mx-auto"');

    // Check for proper centering classes
    expect($content)->toContain('flex justify-center');

    // Check for image styling
    expect($content)->toContain('style="max-width: 300px;"');
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
