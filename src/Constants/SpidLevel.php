<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Constants;

enum SpidLevel: string
{
    case LEVEL_1 = 'https://www.spid.gov.it/SpidL1';
    case LEVEL_2 = 'https://www.spid.gov.it/SpidL2';
    case LEVEL_3 = 'https://www.spid.gov.it/SpidL3';

    public function rank(): int
    {
        return match ($this) {
            self::LEVEL_1 => 1,
            self::LEVEL_2 => 2,
            self::LEVEL_3 => 3,
        };
    }

    public function meets(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }

    /**
     * Whether the level the SP requests meets filament-spid.minimum_level.
     *
     * italia/spid-laravel requests spid-auth.sp_spid_level and rejects any
     * assertion below it, so checking the configured level is enough to know
     * every accepted login meets the minimum. An unreadable requested level
     * fails; an unreadable minimum falls back to SpidL2.
     */
    public static function requestedMeetsMinimum(): bool
    {
        $requested = self::tryFrom((string) config('spid-auth.sp_spid_level'));
        $minimum = self::tryFrom((string) config('filament-spid.minimum_level')) ?? self::LEVEL_2;

        return $requested?->meets($minimum) ?? false;
    }
}
