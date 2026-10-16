<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Configuration warnings raised while booting.
 *
 * Boot runs on every request, so a misconfiguration would otherwise flood
 * the log; each distinct message is logged at most once an hour. If the
 * cache is unavailable the warning is logged anyway.
 */
final class Warning
{
    public const TTL_SECONDS = 3600;

    public static function once(string $message): void
    {
        try {
            $first = Cache::add('filament-spid:warning:'.sha1($message), true, self::TTL_SECONDS);
        } catch (\Throwable) {
            $first = true;
        }

        if ($first) {
            Log::warning($message);
        }
    }
}
