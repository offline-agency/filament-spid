<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use OfflineAgency\FilamentSpid\SpidPlugin;

/**
 * Registers a real "admin" panel during tests.
 *
 * Registering the panel through a provider (instead of Filament::registerPanel()
 * at runtime) is what makes the panel routes — filament.admin.auth.login among
 * them — actually exist, and gives SpidPlugin::get() a panel to resolve from.
 */
class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(SpidPlugin::make());
    }
}
