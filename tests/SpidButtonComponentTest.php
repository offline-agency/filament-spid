<?php

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
