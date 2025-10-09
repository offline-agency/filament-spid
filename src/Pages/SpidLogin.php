<?php

namespace OfflineAgency\FilamentSpid\Pages;

use Filament\Pages\SimplePage;

class SpidLogin extends SimplePage
{
    public function getView(): string
    {
        return 'filament-spid::login';
    }

    public function getHeading(): string
    {
        return __('filament-spid::spid.login_with_spid');
    }
}
