<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Exceptions;

use RuntimeException;
use Throwable;

class SpidPanelNotFoundException extends RuntimeException
{
    public static function forId(string $id, ?Throwable $previous = null): self
    {
        return new self(
            "filament-spid.panel is set to [{$id}], but no Filament panel with that id is registered.",
            previous: $previous,
        );
    }
}
