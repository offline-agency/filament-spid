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

it('accepts any italia/spid-laravel 2.x from 2.1.0-beta', function () {
    expect(composerManifest()['require']['italia/spid-laravel'])->toBe('^2.1.0-beta');
});
