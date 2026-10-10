<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;

/**
 * Test and validator IdPs, which must not reach production.
 */
class TestIdpsCheck implements Check
{
    public function run(): Result
    {
        $enabled = array_keys(array_filter([
            'spid-auth.test_idp' => (bool) config('spid-auth.test_idp'),
            'spid-auth.validator_idp' => (bool) config('spid-auth.validator_idp'),
            'spid-auth.hide_real_idps' => (bool) config('spid-auth.hide_real_idps'),
        ]));

        if ($enabled === []) {
            return Result::pass('test_idps', 'No test or validator IdP is enabled, and real IdPs are shown.');
        }

        if (app()->isProduction()) {
            return Result::fail('test_idps', 'In production: '.implode(', ', $enabled).' enabled.', 'Set test_idp and validator_idp to false and hide_real_idps to false in config/spid-auth.php for production.');
        }

        return Result::pass('test_idps', 'Outside production: '.implode(', ', $enabled).' enabled.');
    }
}
