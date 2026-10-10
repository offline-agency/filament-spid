<?php

use OfflineAgency\FilamentSpid\SpidPlugin;

it('renders no credentials link by default', function () {
    // filament()->getLoginUrl() is the SPID page itself: linking to it went
    // nowhere.
    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee(__('filament-spid::spid.standard_login'));
});

it('links to the configured credentials login page', function () {
    filament()->getPanel('admin')->plugin(
        SpidPlugin::make()->credentialsLoginUrl('https://example.test/password-login')
    );

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('href="https://example.test/password-login"', false)
        ->assertSee(__('filament-spid::spid.standard_login'));
});
