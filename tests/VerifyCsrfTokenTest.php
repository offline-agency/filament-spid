<?php

use OfflineAgency\FilamentSpid\Http\Middleware\VerifyCsrfToken;

it('extends Laravel base VerifyCsrfToken', function () {
    expect(VerifyCsrfToken::class)
        ->toExtend(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
});

it('excludes */spid/* from CSRF verification', function () {
    $defaults = (new \ReflectionClass(VerifyCsrfToken::class))->getDefaultProperties();

    expect($defaults['except'])->toContain('*/spid/*');
});

it('excludes /spid/* from CSRF verification', function () {
    $defaults = (new \ReflectionClass(VerifyCsrfToken::class))->getDefaultProperties();

    expect($defaults['except'])->toContain('/spid/*');
});

it('has exactly two excluded patterns', function () {
    $defaults = (new \ReflectionClass(VerifyCsrfToken::class))->getDefaultProperties();

    expect($defaults['except'])->toHaveCount(2);
});
