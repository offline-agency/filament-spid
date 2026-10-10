<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Listeners;

use Filament\Facades\Filament;
use Filament\Panel;
use OfflineAgency\FilamentSpid\Exceptions\SpidPanelNotFoundException;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

trait ResolvesPanel
{
    /**
     * Resolve the panel the SPID flow belongs to.
     *
     * The ACS request runs on a library route, outside any panel, so in a
     * multi-panel application the panel to authenticate against has to be
     * named explicitly. A named panel that does not exist is a configuration
     * error and throws: falling back would log the citizen in on whatever
     * guard happens to be the default.
     *
     * @throws SpidPanelNotFoundException
     */
    protected function panel(): ?Panel
    {
        if ($id = TypedConfig::string('filament-spid.panel')) {
            try {
                // The facade docblock promises a Panel, but FilamentManager
                // can return null (it does on Filament 5).
                /** @var Panel|null $panel */
                $panel = Filament::getPanel($id);
            } catch (\Throwable $e) {
                throw SpidPanelNotFoundException::forId($id, $e);
            }

            // Depending on the major, Filament returns null or the default
            // panel for an unknown id instead of throwing.
            if (! $panel instanceof Panel || $panel->getId() !== $id) {
                throw SpidPanelNotFoundException::forId($id);
            }

            return $panel;
        }

        // Filament throws when no panel is registered as default, which is a
        // legitimate state outside a panel request.
        try {
            return Filament::getCurrentPanel() ?? Filament::getDefaultPanel();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function guard(): string
    {
        return $this->panel()?->getAuthGuard() ?? TypedConfig::string('auth.defaults.guard') ?? 'web';
    }

    protected function loginUrl(): string
    {
        try {
            return $this->panel()?->getLoginUrl() ?? url('/');
        } catch (\Throwable) {
            return url('/');
        }
    }
}
