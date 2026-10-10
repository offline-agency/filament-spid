<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;

/**
 * A Content-Security-Policy is set by the web server, a proxy or middleware:
 * not observable from the CLI.
 */
class CspCheck implements Check
{
    public function run(): Result
    {
        return Result::skip('csp', 'Not verifiable from the CLI: the Content-Security-Policy of the login page.', "The login page needs script-src and style-src 'self' (no inline scripts) and img-src 'self'; check the browser console on the login page.");
    }
}
