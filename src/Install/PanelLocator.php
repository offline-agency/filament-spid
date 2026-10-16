<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install;

use Closure;
use Filament\Facades\Filament;

/**
 * The ids of the registered Filament panels.
 */
class PanelLocator
{
    /** @var Closure(): list<string> */
    protected Closure $panels;

    /**
     * @param  (Closure(): list<string>)|null  $panels  null asks Filament
     */
    public function __construct(?Closure $panels = null)
    {
        $this->panels = $panels ?? fn (): array => array_map('strval', array_keys(Filament::getPanels()));
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return ($this->panels)();
    }
}
