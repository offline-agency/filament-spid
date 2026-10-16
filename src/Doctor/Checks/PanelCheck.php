<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Exceptions\SpidPanelNotFoundException;
use OfflineAgency\FilamentSpid\Listeners\ResolvesPanel;
use OfflineAgency\FilamentSpid\Pages\SpidLogin;

/**
 * The panel SPID logins land on, and its guard: resolved as the listener does.
 */
class PanelCheck implements Check
{
    use ResolvesPanel;

    public function run(): Result
    {
        try {
            $panel = $this->panel();
        } catch (SpidPanelNotFoundException) {
            return Result::fail('panel', 'filament-spid.panel names a panel that is not registered.', 'Set FILAMENT_SPID_PANEL to the id of a registered panel, or unset it to use the default one.');
        }

        if ($panel === null) {
            return Result::fail('panel', 'No Filament panel is registered as default.', 'Register a panel with ->default(), or set filament-spid.panel.');
        }

        $summary = sprintf('Panel [%s], guard [%s].', $panel->getId(), $panel->getAuthGuard());

        if (config('filament-spid.enabled', true) && $panel->getLoginRouteAction() !== SpidLogin::class) {
            return Result::warn('panel', $summary.' Its login page is not the SPID one.', 'Chain ->plugin(SpidPlugin::make()) after ->login() in the panel provider, or drop ->login().');
        }

        return Result::pass('panel', $summary);
    }
}
