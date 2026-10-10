<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

/**
 * A user model that implements Filament's panel authorisation gate.
 */
class PanelUser extends User implements FilamentUser
{
    public static bool $canAccessPanel = true;

    public function canAccessPanel(Panel $panel): bool
    {
        return static::$canAccessPanel;
    }
}
