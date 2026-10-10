<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use Filament\Support\Facades\FilamentAsset;
use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;

/**
 * The published files the login page loads.
 */
class AssetsCheck implements Check
{
    /** Published by italia/spid-laravel's spid-assets tag. */
    public const LIBRARY = [
        'vendor/spid-auth/js/spid-sp-access-button.min.js',
        'vendor/spid-auth/css/spid-sp-access-button.min.css',
        'vendor/spid-auth/img/spid-ico-circle-bb.svg',
    ];

    public const IMAGES = ['vendor/filament-spid/images/spid-agid-logo.png'];

    public function run(): Result
    {
        $missing = [];

        foreach (self::LIBRARY as $file) {
            if (! is_file(public_path($file))) {
                $missing['php artisan vendor:publish --tag=spid-assets'][] = $file;
            }
        }

        foreach (self::IMAGES as $file) {
            if (! is_file(public_path($file))) {
                $missing['php artisan vendor:publish --tag=filament-spid-images'][] = $file;
            }
        }

        foreach (FilamentAsset::getScripts(['offline-agency/filament-spid']) as $script) {
            if (! is_file($script->getPublicPath())) {
                $missing['php artisan filament:assets'][] = 'the '.$script->getId().' script';
            }
        }

        if ($missing === []) {
            return Result::pass('assets', 'The SPID button, logo and plugin scripts are published.');
        }

        return Result::fail(
            'assets',
            'Missing: '.implode(', ', array_merge(...array_values($missing))).'.',
            'Run: '.implode(' && ', array_keys($missing)),
        );
    }
}
