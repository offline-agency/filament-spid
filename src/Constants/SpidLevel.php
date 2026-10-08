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
     * every accepted login meets the minimum. Fails closed when either level
     * is unreadable: a typo in a minimum meant as SpidL3 must not quietly
     * become a lower one.
     */
    public static function requestedMeetsMinimum(): bool
    {
        $requested = self::tryFrom((string) config('spid-auth.sp_spid_level'));
        $minimum = self::minimum();

        return $requested !== null && $minimum !== null && $requested->meets($minimum);
    }

    /**
     * filament-spid.minimum_level, or null when it is not a SPID level URI.
     */
    public static function minimum(): ?self
    {
        return self::tryFrom((string) config('filament-spid.minimum_level', self::LEVEL_2->value));
    }
}
