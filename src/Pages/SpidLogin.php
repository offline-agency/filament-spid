<?php

namespace OfflineAgency\FilamentSpid\Pages;

use Filament\Pages\SimplePage;
use OfflineAgency\FilamentSpid\SpidPlugin;

class SpidLogin extends SimplePage
{
    public function getView(): string
    {
        return SpidPlugin::get()->getLoginView();
    }

    public function getHeading(): string
    {
        return __('filament-spid::spid.login_with_spid');
    }
}
