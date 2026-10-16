<?php

use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Mapping\FieldMapper;
use OfflineAgency\FilamentSpid\Mapping\FullName;
use OfflineAgency\FilamentSpid\Tests\Fixtures\NotInvokable;
use OfflineAgency\FilamentSpid\Tests\Fixtures\StaticMapper;

$spidUser = ['name' => 'Mario', 'familyName' => 'Rossi', 'fiscalNumber' => 'RSSMRA80A01H501U'];

it('reads a SPID attribute named by a string', function () use ($spidUser) {
    expect(FieldMapper::value('fiscalNumber', $spidUser))->toBe('RSSMRA80A01H501U');
});

it('maps a known SPID attribute the IdP did not send to null', function () use ($spidUser) {
    expect(FieldMapper::value('email', $spidUser))->toBeNull();
});

it('reads an attribute present in the payload even if it is not a standard one', function () {
    expect(FieldMapper::value('mobilePhone', ['mobilePhone' => '+39 333']))->toBe('+39 333');
});

it('exposes the SPID attribute names', function () {
    expect(SpidUserData::ATTRIBUTES)->toContain('fiscalNumber', 'gender');
});

it('invokes a mapper class resolved through the container', function () use ($spidUser) {
    expect(FieldMapper::value(FullName::class, $spidUser))->toBe('Mario Rossi');
});

it('invokes a closure', function () use ($spidUser) {
    expect(FieldMapper::value(fn (array $user) => $user['familyName'], $spidUser))->toBe('Rossi');
});

it('invokes an array callable', function () use ($spidUser) {
    expect(FieldMapper::value([StaticMapper::class, 'shout'], $spidUser))->toBe('MARIO');
});

it('never calls a PHP function named by a plain string', function (string $function) use ($spidUser) {
    // A typo in a config value must not run arbitrary functions.
    expect(FieldMapper::value($function, $spidUser))->toBeNull();
})->with(['strtoupper', 'time', 'strrev']);

it('maps anything else to null', function (mixed $mapper) use ($spidUser) {
    expect(FieldMapper::value($mapper, $spidUser))->toBeNull();
})->with([
    'an integer' => [42],
    'null' => [null],
    'a non-callable array' => [['not', 'callable']],
]);

it('maps a class without __invoke to null', function () use ($spidUser) {
    expect(FieldMapper::value(NotInvokable::class, $spidUser))->toBeNull();
});
