<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

/**
 * HTTPS and the session cookie, as configured (a proxy may still change them).
 */
class HttpsSessionCheck implements Check
{
    public function run(): Result
    {
        $problems = [];

        if (! str_starts_with(TypedConfig::string('app.url') ?? '', 'https://')) {
            $problems[] = 'app.url is not HTTPS';
        }

        if (config('session.secure') !== true) {
            $problems[] = 'session.secure is not true';
        }

        if ($problems !== []) {
            return Result::warn('https_session', 'Not secure: '.implode('; ', $problems).'.', 'Serve the application over HTTPS and set SESSION_SECURE_COOKIE=true: the SPID session must not travel in clear.');
        }

        return Result::pass('https_session', 'app.url is HTTPS and the session cookie is secure.');
    }
}
