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
