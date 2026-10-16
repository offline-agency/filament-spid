<?php

use Filament\Facades\Filament;
use OfflineAgency\FilamentSpid\Exceptions\SpidPanelNotFoundException;
use OfflineAgency\FilamentSpid\Listeners\ResolvesPanel;

/**
 * Exposes the trait's protected helpers.
 */
function panelResolver(): object
{
    return new class
    {
        use ResolvesPanel {
            panel as public;
            guard as public;
            loginUrl as public;
        }
    };
}

it('wraps a Filament failure for a configured panel in SpidPanelNotFoundException', function () {
    config()->set('filament-spid.panel', 'admin');
    $boom = new RuntimeException('registry unavailable');
    Filament::partialMock()->shouldReceive('getPanel')->with('admin')->andThrow($boom);

    try {
        panelResolver()->panel();
        $this->fail('SpidPanelNotFoundException was not thrown');
    } catch (SpidPanelNotFoundException $e) {
        expect($e->getMessage())->toContain('[admin]')
            ->and($e->getPrevious())->toBe($boom);
    }
});

it('treats a missing default panel as no panel', function () {
    // Outside a panel request with no default panel Filament throws.
    Filament::partialMock()->shouldReceive('getCurrentPanel')->andReturnNull();
    Filament::partialMock()->shouldReceive('getDefaultPanel')->andThrow(new RuntimeException('no default panel'));

    $resolver = panelResolver();

    expect($resolver->panel())->toBeNull()
        ->and($resolver->guard())->toBe(config('auth.defaults.guard'));
});

it('falls back to the site root when the login URL cannot be resolved', function () {
    config()->set('filament-spid.panel', 'does-not-exist');

    expect(panelResolver()->loginUrl())->toBe(url('/'));
});

it('falls back to the site root when there is no panel', function () {
    Filament::partialMock()->shouldReceive('getCurrentPanel')->andReturnNull();
    Filament::partialMock()->shouldReceive('getDefaultPanel')->andThrow(new RuntimeException('no default panel'));

    expect(panelResolver()->loginUrl())->toBe(url('/'));
});
