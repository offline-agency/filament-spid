<?php

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Config;
use OfflineAgency\FilamentSpid\SpidPlugin;

beforeEach(function () {
    Config::set('spid-idps', [
        'posteid' => [
            'provider' => 'poste',
            'entityName' => 'Poste ID',
            'logo' => 'spid-idp-posteid.svg',
            'real' => true,
            'isActive' => true,
        ],
        'infocertid' => [
            'provider' => 'infocert',
            'entityName' => 'Infocert ID',
            'logo' => 'spid-idp-infocertid.svg',
            'real' => true,
            'isActive' => true,
        ],
        'timid' => [
            'provider' => 'tim',
            'entityName' => 'Tim ID',
            'logo' => 'spid-idp-timid.svg',
            'real' => true,
            'isActive' => false,
        ],
    ]);
});

function renderSpidButton(): string
{
    return (string) view('filament-spid::components.spid-button-filament', ['size' => 'l'])->render();
}

it('renders the default label', function () {
    expect(renderSpidButton())->toContain(__('filament-spid::spid.login_with_spid'));
});

it('renders the label configured on the plugin', function () {
    filament()->getPanel('admin')->plugin(SpidPlugin::make()->spidButtonLabel('Accedi con SPID'));

    expect(renderSpidButton())->toContain('Accedi con SPID');
});

it('renders the icon configured on the plugin instead of the SPID mark', function () {
    filament()->getPanel('admin')->plugin(SpidPlugin::make()->spidButtonIcon('heroicon-o-shield-check'));

    $html = renderSpidButton();

    // The heroicon is inlined as SVG, so the icon name itself is not in the output.
    expect($html)->toContain('<svg')
        ->and($html)->not->toContain('spid-ico-circle-bb.svg');
});

it('falls back to the SPID mark when no icon is configured', function () {
    expect(renderSpidButton())->toContain('spid-ico-circle-bb.svg');
});

it('lists every active provider by default', function () {
    $html = renderSpidButton();

    expect($html)->toContain('Poste ID')
        ->and($html)->toContain('Infocert ID')
        ->and($html)->not->toContain('Tim ID');
});

it('lists only the providers allowed by the plugin', function () {
    filament()->getPanel('admin')->plugin(SpidPlugin::make()->providers(['posteid']));

    $html = renderSpidButton();

    expect($html)->toContain('Poste ID')
        ->and($html)->not->toContain('Infocert ID');
});

it('never lists an inactive provider even when allowed', function () {
    filament()->getPanel('admin')->plugin(SpidPlugin::make()->providers(['posteid', 'timid']));

    $html = renderSpidButton();

    expect($html)->toContain('Poste ID')
        ->and($html)->not->toContain('Tim ID');
});

/**
 * The button's behaviour script, as shipped.
 */
function spidButtonScript(): string
{
    return file_get_contents(__DIR__.'/../resources/dist/filament-spid-button.js');
}

it('loads its behaviour from a script file, with no inline script or handler', function () {
    // A strict Content-Security-Policy blocks inline scripts and on* attributes.
    $html = renderSpidButton();

    expect($html)->toContain('<script src="'.FilamentAsset::getScriptSrc('filament-spid-button', 'offline-agency/filament-spid').'"')
        ->and($html)->not->toMatch('/<script(?![^>]*\bsrc=)[^>]*>/')
        ->and($html)->not->toMatch('/\son[a-z]+=/i');
});

it('hands the loader the jQuery and AgID script URLs from the package assets', function () {
    $html = renderSpidButton();

    expect($html)->toContain('data-jquery-src="'.e(FilamentAsset::getScriptSrc('spid-jquery', 'offline-agency/filament-spid')).'"')
        ->and($html)->toContain('data-button-src="'.e(asset('vendor/spid-auth/js/spid-sp-access-button.min.js')).'"')
        ->and($html)->not->toContain('code.jquery.com');
});

it('loads jQuery only when missing, and the AgID script after it, without document.write', function () {
    // spid-sp-access-button.min.js starts with `jQuery&&...`.
    $script = spidButtonScript();

    expect($script)->toContain("typeof win.jQuery === 'undefined'")
        ->and($script)->toContain("loadScript(doc, 'spid-jquery'")
        ->and($script)->toContain('withButtonScript')
        ->and($script)->not->toContain('document.write');
});

it('renders the provider form posting to the library login route', function () {
    $html = renderSpidButton();

    expect($html)->toContain(route('spid-auth_do-login'))
        ->and($html)->toContain('name="provider"');
});

it('gives every button on a page its own form, input and menu ids', function () {
    $html = renderSpidButton().renderSpidButton();

    preg_match_all('/\sid="([^"]+)"/', $html, $ids);
    expect($ids[1])->toHaveCount(6)
        ->and(array_unique($ids[1]))->toHaveCount(6);

    // Each trigger opens its own menu.
    preg_match_all('/spid-idp-button="#([^"]+)"/', $html, $targets);
    expect($targets[1])->toHaveCount(2)
        ->and(array_unique($targets[1]))->toHaveCount(2);
    foreach ($targets[1] as $target) {
        expect($html)->toContain('id="'.$target.'"');
    }
});

it('scopes its script to its own wrapper', function () {
    $script = spidButtonScript();

    expect($script)->toContain("wrapper.querySelector('input[name=\"provider\"]')")
        ->and($script)->not->toContain("jQuery('#spid_idp_access_provider')")
        ->and($script)->toContain('data-spid-initialized')
        ->and($script)->toContain('livewire:navigated');
});

it('exposes the menu state to assistive technology', function () {
    $html = renderSpidButton();

    expect($html)->toContain('role="button"')
        ->and($html)->toContain('aria-haspopup="true"')
        ->and($html)->toContain('aria-expanded="false"')
        ->and($html)->toMatch('/aria-controls="spid-[a-z0-9]+-menu"/');
});

it('keeps the information links after the providers', function () {
    $html = renderSpidButton();

    expect(strpos($html, 'data-spidlink="info"'))->toBeGreaterThan(strpos($html, 'data-idp="posteid"'))
        ->and($html)->toContain('https://www.spid.gov.it/richiedi-spid')
        ->and($html)->toContain('https://www.spid.gov.it/serve-aiuto');
});

it('ships a hidden message for when its scripts cannot load', function () {
    expect(renderSpidButton())->toMatch('/<p[^>]*data-spid-load-error[^>]*hidden/');
});
