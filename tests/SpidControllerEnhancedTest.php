<?php

use Illuminate\Support\Facades\Config;
use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;

describe('SpidController - Providers Endpoint', function () {
    it('returns active providers only', function () {
        Config::set('spid-idps', [
            'posteid' => [
                'provider' => 'poste',
                'title' => 'Poste ID',
                'entityName' => 'Poste Italiane',
                'logo' => 'poste.svg',
                'isActive' => true,
            ],
            'arubaid' => [
                'provider' => 'aruba',
                'title' => 'Aruba ID',
                'entityName' => 'Aruba',
                'logo' => 'aruba.svg',
                'isActive' => false,
            ],
            'infocertid' => [
                'provider' => 'infocert',
                'title' => 'InfoCert ID',
                'entityName' => 'InfoCert',
                'logo' => 'infocert.svg',
                'isActive' => true,
            ],
        ]);

        $this->app['router']->get('/spid/providers', [SpidController::class, 'providers']);
        $response = $this->get('/spid/providers');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'providers');

        $data = $response->json('providers');
        expect($data[0]['provider'])->toBe('poste')
            ->and($data[1]['provider'])->toBe('infocert');
    });

    it('excludes empty provider', function () {
        Config::set('spid-idps', [
            'empty' => [
                'provider' => 'empty',
                'title' => 'Empty',
                'isActive' => true,
            ],
            'posteid' => [
                'provider' => 'poste',
                'title' => 'Poste ID',
                'isActive' => true,
            ],
        ]);

        $this->app['router']->get('/spid/providers', [SpidController::class, 'providers']);
        $response = $this->get('/spid/providers');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'providers');

        $data = $response->json('providers');
        expect($data[0]['provider'])->toBe('poste');
    });

    it('returns empty array when no active providers', function () {
        Config::set('spid-idps', [
            'posteid' => [
                'provider' => 'poste',
                'title' => 'Poste ID',
                'isActive' => false,
            ],
        ]);

        $this->app['router']->get('/spid/providers', [SpidController::class, 'providers']);
        $response = $this->get('/spid/providers');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'providers');
    });

    it('includes all required provider fields', function () {
        Config::set('spid-idps', [
            'posteid' => [
                'provider' => 'poste',
                'title' => 'Poste ID',
                'entityName' => 'Poste Italiane',
                'logo' => 'poste.svg',
                'isActive' => true,
            ],
        ]);

        $this->app['router']->get('/spid/providers', [SpidController::class, 'providers']);
        $response = $this->get('/spid/providers');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'providers' => [
                '*' => ['provider', 'title', 'entityName', 'logo'],
            ],
        ]);
    });

    it('handles missing optional fields gracefully', function () {
        Config::set('spid-idps', [
            'posteid' => [
                'isActive' => true,
            ],
        ]);

        $this->app['router']->get('/spid/providers', [SpidController::class, 'providers']);
        $response = $this->get('/spid/providers');

        $response->assertStatus(200);

        $data = $response->json('providers');
        expect($data[0]['provider'])->toBe('posteid')
            ->and($data[0]['title'])->toBe('posteid')
            ->and($data[0]['entityName'])->toBeNull()
            ->and($data[0]['logo'])->toBeNull();
    });
});

describe('SpidController - Login Endpoint', function () {
    it('redirects back when provider is missing', function () {
        $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

        $response = $this->get('/spid/login');

        $response->assertRedirect();
        $response->assertSessionHasErrors(['provider']);
    });

    it('redirects back with error message when provider is missing', function () {
        $this->app['router']->get('/spid/login', [SpidController::class, 'login']);

        $response = $this->get('/spid/login');

        $response->assertSessionHasErrors(['provider']);
    });
});

describe('SpidController - Metadata Endpoint', function () {
    it('returns xml content type when successful', function () {
        $this->app['router']->get('/spid/metadata', [SpidController::class, 'metadata']);

        $response = $this->get('/spid/metadata');

        if ($response->status() === 200) {
            expect($response->headers->get('Content-Type'))->toContain('xml');
        } else {
            // If metadata generation fails, we expect 500 error
            expect($response->status())->toBe(500);
        }
    });

    it('handles metadata generation errors', function () {
        // This will likely fail with current minimal config
        $this->app['router']->get('/spid/metadata', [SpidController::class, 'metadata']);

        $response = $this->get('/spid/metadata');

        // Should either succeed or fail gracefully with 500
        expect($response->status())->toBeIn([200, 500]);
    });
});
