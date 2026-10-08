<?php

/**
 * Guards the published composer.json: consumers install exactly what it
 * says, so its constraints are part of the package's behaviour.
 */
function composerManifest(): array
{
    return json_decode(file_get_contents(__DIR__.'/../composer.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('does not ship a repositories entry', function () {
    expect(composerManifest())->not->toHaveKey('repositories');
});

it('requires php ^8.2', function () {
    expect(composerManifest()['require']['php'])->toBe('^8.2');
});

it('allows beta dependencies while preferring stable ones', function () {
    expect(composerManifest()['minimum-stability'])->toBe('beta')
        ->and(composerManifest()['prefer-stable'])->toBeTrue();
});

it('supports Laravel 12 and 13 only', function () {
    // Every Laravel 11 release carries unpatched advisories (11.x is end of
    // life), so Composer refuses to install it.
    expect(composerManifest()['require']['illuminate/contracts'])->toBe('^12.0|^13.0');
});

it('requires a package-tools release that supports Laravel 13', function () {
    expect(composerManifest()['require']['spatie/laravel-package-tools'])->toBe('^1.93');
});

it('allows the dev tooling of every supported Laravel major', function (string $package, string $constraint) {
    expect(composerManifest()['require-dev'][$package])->toBe($constraint);
})->with([
    ['pestphp/pest', '^3.0|^4.0'],
    ['pestphp/pest-plugin-laravel', '^3.0|^4.0'],
    ['pestphp/pest-plugin-arch', '^3.0|^4.0'],
    ['nunomaduro/collision', '^8.0|^9.0'],
    ['orchestra/testbench', '^10.0|^11.0'],
    ['larastan/larastan', '^3.0'],
]);

it('accepts any italia/spid-laravel 2.x from 2.1.0-beta', function () {
    expect(composerManifest()['require']['italia/spid-laravel'])->toBe('^2.1.0-beta');
});
