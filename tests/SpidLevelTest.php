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
