<?php

use Illuminate\Support\Facades\Config;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

it('reads a string value', function () {
    Config::set('filament-spid.panel', 'admin');

    expect(TypedConfig::string('filament-spid.panel'))->toBe('admin');
});

it('falls back to the default when the key is missing', function () {
    expect(TypedConfig::string('filament-spid.missing', 'fallback'))->toBe('fallback');
});

it('reads a value that is not a string as null', function (mixed $value) {
    Config::set('filament-spid.panel', $value);

    expect(TypedConfig::string('filament-spid.panel', 'unused'))->toBeNull();
})->with([
    'null' => [null],
    'an integer' => [42],
    'an array' => [['admin']],
]);

it('reads an array value', function () {
    Config::set('spid-idps', ['poste' => []]);

    expect(TypedConfig::array('spid-idps'))->toBe(['poste' => []]);
});

it('reads a value that is not an array as empty', function () {
    Config::set('spid-idps', 'not an array');

    expect(TypedConfig::array('spid-idps'))->toBe([]);
});
