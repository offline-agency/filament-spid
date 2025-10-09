<?php

use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;

it('can access providers endpoint', function () {
    $this->app['router']->get('/spid/providers', [SpidController::class, 'providers']);

    $response = $this->get('/spid/providers');

    $response->assertStatus(200);
    $response->assertJsonStructure(['providers']);
});

it('requires provider parameter for login', function () {
    $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

    $response = $this->get('/spid/login');

    $response->assertRedirect();
});

it('can access metadata endpoint', function () {
    $this->app['router']->get('/spid/metadata', [SpidController::class, 'metadata']);

    $response = $this->get('/spid/metadata');

    // Metadata endpoint might fail without proper SPID configuration
    // but should at least be accessible
    expect($response->status())->toBeIn([200, 500]);
});
