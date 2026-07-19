<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use OfflineAgency\FilamentSpid\Constants\SpidLevel;
use OfflineAgency\FilamentSpid\Http\Requests\SpidLoginRequest;

/**
 * SpidController::login() no longer validates a provider: the SAML handshake is
 * triggered by the button posting to italia/spid-laravel, so the rules are
 * exercised here directly against the FormRequest.
 */
function validateSpidLogin(array $data): Illuminate\Contracts\Validation\Validator
{
    return Validator::make($data, (new SpidLoginRequest)->rules());
}

beforeEach(function () {
    Config::set('spid-idps', [
        'posteid' => ['provider' => 'poste', 'isActive' => true],
        'infocertid' => ['provider' => 'infocert', 'isActive' => false],
    ]);
});

it('fails validation when provider is missing', function () {
    $validator = validateSpidLogin([]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('provider'))->toBeTrue();
});

it('fails validation when provider is not active/allowed', function () {
    $validator = validateSpidLogin(['provider' => 'infocertid']);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('provider'))->toBeTrue();
});

it('fails validation when provider is unknown', function () {
    $validator = validateSpidLogin(['provider' => 'nonexistent']);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('provider'))->toBeTrue();
});

it('fails validation when level is invalid', function () {
    $validator = validateSpidLogin(['provider' => 'posteid', 'level' => 'INVALID']);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('level'))->toBeTrue();
});

it('accepts each valid SPID level', function (string $level) {
    $validator = validateSpidLogin(['provider' => 'posteid', 'level' => $level]);

    expect($validator->fails())->toBeFalse();
})->with([
    SpidLevel::LEVEL_1->value,
    SpidLevel::LEVEL_2->value,
    SpidLevel::LEVEL_3->value,
]);

it('accepts a missing level', function () {
    $validator = validateSpidLogin(['provider' => 'posteid']);

    expect($validator->fails())->toBeFalse();
});

it('validates provider against active providers only', function () {
    expect(validateSpidLogin(['provider' => 'posteid'])->fails())->toBeFalse()
        ->and(validateSpidLogin(['provider' => 'infocertid'])->fails())->toBeTrue();
});

it('authorizes every request', function () {
    expect((new SpidLoginRequest)->authorize())->toBeTrue();
});

it('provides translated validation messages', function () {
    $messages = (new SpidLoginRequest)->messages();

    expect($messages)->toHaveKeys(['provider.required', 'provider.in', 'level.in']);
});
