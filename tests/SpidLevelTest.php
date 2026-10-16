<?php

use OfflineAgency\FilamentSpid\Constants\SpidLevel;

it('has three cases', function () {
    expect(SpidLevel::cases())->toHaveCount(3);
});

it('has LEVEL_1 case with correct value', function () {
    expect(SpidLevel::LEVEL_1->value)->toBe('https://www.spid.gov.it/SpidL1');
});

it('has LEVEL_2 case with correct value', function () {
    expect(SpidLevel::LEVEL_2->value)->toBe('https://www.spid.gov.it/SpidL2');
});

it('has LEVEL_3 case with correct value', function () {
    expect(SpidLevel::LEVEL_3->value)->toBe('https://www.spid.gov.it/SpidL3');
});

it('resolves from valid LEVEL_1 value', function () {
    expect(SpidLevel::from('https://www.spid.gov.it/SpidL1'))->toBe(SpidLevel::LEVEL_1);
});

it('resolves from valid LEVEL_2 value', function () {
    expect(SpidLevel::from('https://www.spid.gov.it/SpidL2'))->toBe(SpidLevel::LEVEL_2);
});

it('resolves from valid LEVEL_3 value', function () {
    expect(SpidLevel::from('https://www.spid.gov.it/SpidL3'))->toBe(SpidLevel::LEVEL_3);
});

it('returns null for invalid value via tryFrom', function () {
    expect(SpidLevel::tryFrom('https://www.spid.gov.it/SpidL99'))->toBeNull();
});

it('returns null for empty string via tryFrom', function () {
    expect(SpidLevel::tryFrom(''))->toBeNull();
});

it('throws for invalid value via from', function () {
    expect(fn () => SpidLevel::from('invalid'))->toThrow(ValueError::class);
});

it('is string backed', function () {
    expect(SpidLevel::LEVEL_1->value)->toBeString();
});

it('ranks the levels', function (SpidLevel $level, int $rank) {
    expect($level->rank())->toBe($rank);
})->with([
    [SpidLevel::LEVEL_1, 1],
    [SpidLevel::LEVEL_2, 2],
    [SpidLevel::LEVEL_3, 3],
]);

it('tells whether a level meets a minimum', function (SpidLevel $level, SpidLevel $minimum, bool $meets) {
    expect($level->meets($minimum))->toBe($meets);
})->with([
    [SpidLevel::LEVEL_1, SpidLevel::LEVEL_2, false],
    [SpidLevel::LEVEL_2, SpidLevel::LEVEL_2, true],
    [SpidLevel::LEVEL_3, SpidLevel::LEVEL_2, true],
    [SpidLevel::LEVEL_2, SpidLevel::LEVEL_3, false],
]);

it('requires SpidL2 by default', function () {
    expect(config('filament-spid.minimum_level'))->toBe(SpidLevel::LEVEL_2->value);
});

it('is satisfied when the requested level meets the minimum', function (string $requested, string $minimum, bool $satisfied) {
    config()->set('spid-auth.sp_spid_level', $requested);
    config()->set('filament-spid.minimum_level', $minimum);

    expect(SpidLevel::requestedMeetsMinimum())->toBe($satisfied);
})->with([
    ['https://www.spid.gov.it/SpidL1', 'https://www.spid.gov.it/SpidL2', false],
    ['https://www.spid.gov.it/SpidL2', 'https://www.spid.gov.it/SpidL2', true],
    ['https://www.spid.gov.it/SpidL3', 'https://www.spid.gov.it/SpidL2', true],
    ['not-a-level', 'https://www.spid.gov.it/SpidL2', false],
]);

it('fails closed when the minimum is not a SPID level', function () {
    // A typo in a minimum meant to be SpidL3 must not quietly become SpidL2.
    config()->set('spid-auth.sp_spid_level', 'https://www.spid.gov.it/SpidL3');
    config()->set('filament-spid.minimum_level', 'https://www.spid.gov.it/SpidL3 ');

    expect(SpidLevel::requestedMeetsMinimum())->toBeFalse();
});

it('reads the configured minimum level', function () {
    config()->set('filament-spid.minimum_level', 'https://www.spid.gov.it/SpidL3');
    expect(SpidLevel::minimum())->toBe(SpidLevel::LEVEL_3);

    config()->set('filament-spid.minimum_level', 'typo');
    expect(SpidLevel::minimum())->toBeNull();
});
