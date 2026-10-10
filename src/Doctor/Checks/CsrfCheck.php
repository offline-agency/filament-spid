<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

/**
 * The CSRF exemption of the ACS route lives in the application's middleware
 * configuration, which the CLI cannot observe as a request would.
 */
class CsrfCheck implements Check
{
    public function run(): Result
    {
        $acs = trim(TypedConfig::string('spid-auth.routes_prefix', 'spid') ?? 'spid', '/').'/acs';

        return Result::skip('csrf', "Not verifiable from the CLI: whether only {$acs} is exempt from CSRF verification.", "Exclude exactly {$acs} (validateCsrfTokens(except: ['{$acs}'])), then check that a POST to {$acs} is not answered 419 and that other POST routes still are.");
    }
}
