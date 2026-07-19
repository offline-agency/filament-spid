<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Listeners;

use Filament\Facades\Filament;
use Filament\Panel;

trait ResolvesPanel
{
    /**
     * Resolve the panel the SPID flow belongs to.
     *
     * Filament throws when no panel is registered as default, which is a
     * legitimate state outside a panel request.
     */
    protected function panel(): ?Panel
    {
        try {
            return Filament::getCurrentPanel() ?? Filament::getDefaultPanel();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function guard(): string
    {
        return $this->panel()?->getAuthGuard() ?? config('auth.defaults.guard');
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
