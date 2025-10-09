<?php

describe('Translations - English', function () {
    beforeEach(function () {
        app()->setLocale('en');
    });

    it('has login_with_spid translation', function () {
        $translation = __('filament-spid::spid.login_with_spid');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.login_with_spid');
    });

    it('has provider_required translation', function () {
        $translation = __('filament-spid::spid.provider_required');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.provider_required');
    });

    it('has login_error translation', function () {
        $translation = __('filament-spid::spid.login_error');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.login_error');
    });

    it('has authentication_failed translation', function () {
        $translation = __('filament-spid::spid.authentication_failed');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.authentication_failed');
    });

    it('has acs_error translation', function () {
        $translation = __('filament-spid::spid.acs_error');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.acs_error');
    });

    it('has select_provider translation', function () {
        $translation = __('filament-spid::spid.select_provider');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.select_provider');
    });

    it('has info_text translation', function () {
        $translation = __('filament-spid::spid.info_text');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.info_text');
    });

    it('has standard_login translation', function () {
        $translation = __('filament-spid::spid.standard_login');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.standard_login');
    });
});

describe('Translations - Italian', function () {
    beforeEach(function () {
        app()->setLocale('it');
    });

    it('has italian translations', function () {
        $translation = __('filament-spid::spid.login_with_spid');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.login_with_spid');
    });

    it('italian translations are different from english', function () {
        app()->setLocale('it');
        $italian = __('filament-spid::spid.login_with_spid');

        app()->setLocale('en');
        $english = __('filament-spid::spid.login_with_spid');

        expect($italian)->not->toBe($english);
    });
});

describe('Translations - German', function () {
    beforeEach(function () {
        app()->setLocale('de');
    });

    it('has german translations', function () {
        $translation = __('filament-spid::spid.login_with_spid');

        expect($translation)->toBeString()
            ->and($translation)->not->toBe('filament-spid::spid.login_with_spid');
    });

    it('has all required german translations', function () {
        $keys = [
            'login_with_spid',
            'select_provider',
            'provider_required',
            'login_error',
            'authentication_failed',
            'acs_error',
            'standard_login',
            'info_text',
            'logout',
        ];

        foreach ($keys as $key) {
            $translation = __('filament-spid::spid.'.$key);
            expect($translation)->toBeString()
                ->and($translation)->not->toBe('filament-spid::spid.'.$key);
        }
    });

    it('german translations are different from english', function () {
        app()->setLocale('de');
        $german = __('filament-spid::spid.login_with_spid');

        app()->setLocale('en');
        $english = __('filament-spid::spid.login_with_spid');

        expect($german)->not->toBe($english);
    });

    it('german translations are different from italian', function () {
        app()->setLocale('de');
        $german = __('filament-spid::spid.login_with_spid');

        app()->setLocale('it');
        $italian = __('filament-spid::spid.login_with_spid');

        expect($german)->not->toBe($italian);
    });
});
