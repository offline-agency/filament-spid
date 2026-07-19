<?php

namespace OfflineAgency\FilamentSpid\Pages;

use Filament\Pages\SimplePage;
use OfflineAgency\FilamentSpid\SpidPlugin;

class SpidLogin extends SimplePage
{
    public function getView(): string
    {
        return SpidPlugin::resolve()?->getLoginView() ?? 'filament-spid::login';
    }

    public function getHeading(): string
    {
        return __('filament-spid::spid.login_with_spid');
    }
}
