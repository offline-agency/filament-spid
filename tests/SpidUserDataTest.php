<?php

use OfflineAgency\FilamentSpid\DTOs\SpidUserData;

it('can be instantiated with all properties', function () {
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
        spidCode: 'SPID123',
        placeOfBirth: 'Rome',
        dateOfBirth: '1980-01-01',
        gender: 'M',
        rawData: ['test' => 'data']
    );

    expect($spidData->fiscalNumber)->toBe('RSSMRA80A01H501U')
        ->and($spidData->name)->toBe('Mario')
        ->and($spidData->familyName)->toBe('Rossi')
        ->and($spidData->email)->toBe('mario@example.com')
        ->and($spidData->spidCode)->toBe('SPID123')
        ->and($spidData->placeOfBirth)->toBe('Rome')
        ->and($spidData->dateOfBirth)->toBe('1980-01-01')
        ->and($spidData->gender)->toBe('M')
        ->and($spidData->rawData)->toBe(['test' => 'data']);
});

it('can be instantiated with minimal required properties', function () {
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    expect($spidData->fiscalNumber)->toBe('RSSMRA80A01H501U')
        ->and($spidData->name)->toBe('Mario')
        ->and($spidData->familyName)->toBe('Rossi')
        ->and($spidData->email)->toBeNull()
        ->and($spidData->spidCode)->toBeNull()
        ->and($spidData->placeOfBirth)->toBeNull()
        ->and($spidData->dateOfBirth)->toBeNull()
        ->and($spidData->gender)->toBeNull()
        ->and($spidData->rawData)->toBeNull();
});

it('creates instance from SPID auth data with all fields', function () {
    $spidUser = [
        'fiscalNumber' => 'RSSMRA80A01H501U',
        'name' => 'Mario',
        'familyName' => 'Rossi',
        'email' => 'mario@example.com',
        'spidCode' => 'SPID123',
        'placeOfBirth' => 'Rome',
        'dateOfBirth' => '1980-01-01',
        'gender' => 'M',
        'extra' => 'data'
    ];

    $spidData = SpidUserData::fromSpidAuth($spidUser);

    expect($spidData->fiscalNumber)->toBe('RSSMRA80A01H501U')
        ->and($spidData->name)->toBe('Mario')
        ->and($spidData->familyName)->toBe('Rossi')
        ->and($spidData->email)->toBe('mario@example.com')
        ->and($spidData->spidCode)->toBe('SPID123')
        ->and($spidData->placeOfBirth)->toBe('Rome')
        ->and($spidData->dateOfBirth)->toBe('1980-01-01')
        ->and($spidData->gender)->toBe('M')
        ->and($spidData->rawData)->toBe($spidUser);
});

it('creates instance from SPID auth data with missing optional fields', function () {
    $spidUser = [
        'fiscalNumber' => 'RSSMRA80A01H501U',
        'name' => 'Mario',
        'familyName' => 'Rossi'
    ];

    $spidData = SpidUserData::fromSpidAuth($spidUser);

    expect($spidData->fiscalNumber)->toBe('RSSMRA80A01H501U')
        ->and($spidData->name)->toBe('Mario')
        ->and($spidData->familyName)->toBe('Rossi')
        ->and($spidData->email)->toBeNull()
        ->and($spidData->spidCode)->toBeNull()
        ->and($spidData->placeOfBirth)->toBeNull()
        ->and($spidData->dateOfBirth)->toBeNull()
        ->and($spidData->gender)->toBeNull()
        ->and($spidData->rawData)->toBe($spidUser);
});

it('creates instance from SPID auth data with empty optional fields', function () {
    $spidUser = [
        'fiscalNumber' => 'RSSMRA80A01H501U',
        'name' => 'Mario',
        'familyName' => 'Rossi',
        'email' => '',
        'spidCode' => '',
        'placeOfBirth' => '',
        'dateOfBirth' => '',
        'gender' => ''
    ];

    $spidData = SpidUserData::fromSpidAuth($spidUser);

    expect($spidData->fiscalNumber)->toBe('RSSMRA80A01H501U')
        ->and($spidData->name)->toBe('Mario')
        ->and($spidData->familyName)->toBe('Rossi')
        ->and($spidData->email)->toBe('')
        ->and($spidData->spidCode)->toBe('')
        ->and($spidData->placeOfBirth)->toBe('')
        ->and($spidData->dateOfBirth)->toBe('')
        ->and($spidData->gender)->toBe('');
});

it('throws exception when fiscalNumber is missing', function () {
    $spidUser = [
        'name' => 'Mario',
        'familyName' => 'Rossi'
    ];

    expect(fn() => SpidUserData::fromSpidAuth($spidUser))
        ->toThrow(InvalidArgumentException::class, 'fiscalNumber is required');
});

it('converts to array correctly', function () {
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com',
        spidCode: 'SPID123',
        placeOfBirth: 'Rome',
        dateOfBirth: '1980-01-01',
        gender: 'M'
    );

    $array = $spidData->toArray();

    expect($array)->toBe([
        'fiscalNumber' => 'RSSMRA80A01H501U',
        'name' => 'Mario',
        'familyName' => 'Rossi',
        'email' => 'mario@example.com',
        'spidCode' => 'SPID123',
        'placeOfBirth' => 'Rome',
        'dateOfBirth' => '1980-01-01',
        'gender' => 'M'
    ]);
});

it('converts to array with null values', function () {
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    $array = $spidData->toArray();

    expect($array)->toBe([
        'fiscalNumber' => 'RSSMRA80A01H501U',
        'name' => 'Mario',
        'familyName' => 'Rossi',
        'email' => null,
        'spidCode' => null,
        'placeOfBirth' => null,
        'dateOfBirth' => null,
        'gender' => null
    ]);
});

it('converts to JSON correctly', function () {
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi',
        email: 'mario@example.com'
    );

    $json = $spidData->toJson();

    expect($json)->toBeString()
        ->and(json_decode($json, true))->toBe([
            'fiscalNumber' => 'RSSMRA80A01H501U',
            'name' => 'Mario',
            'familyName' => 'Rossi',
            'email' => 'mario@example.com',
            'spidCode' => null,
            'placeOfBirth' => null,
            'dateOfBirth' => null,
            'gender' => null
        ]);
});

it('converts to JSON with custom options', function () {
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    $json = $spidData->toJson(JSON_PRETTY_PRINT);

    expect($json)->toBeString()
        ->and(json_decode($json, true))->toBeArray();
});

it('implements Arrayable interface', function () {
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    expect($spidData)->toBeInstanceOf(\Illuminate\Contracts\Support\Arrayable::class);
});

it('implements Jsonable interface', function () {
    $spidData = new SpidUserData(
        fiscalNumber: 'RSSMRA80A01H501U',
        name: 'Mario',
        familyName: 'Rossi'
    );

    expect($spidData)->toBeInstanceOf(\Illuminate\Contracts\Support\Jsonable::class);
});
