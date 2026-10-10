<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use Illuminate\Support\Facades\Route;
use OfflineAgency\FilamentSpid\Constants\SpidLevel;
use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

/**
 * The SP identity and requested level, as italia/spid-laravel reads them.
 */
class SpidConfigCheck implements Check
{
    public function run(): Result
    {
        $entityId = TypedConfig::string('spid-auth.sp_entity_id');
        $baseUrl = TypedConfig::string('spid-auth.sp_base_url');

        if (! $entityId || ! $baseUrl) {
            return Result::fail('spid_config', 'spid-auth.sp_entity_id or spid-auth.sp_base_url is not set.', 'Set both in config/spid-auth.php (php artisan vendor:publish --tag=spid-config).');
        }

        if (! SpidLevel::requestedMeetsMinimum()) {
            return Result::fail('spid_config', 'spid-auth.sp_spid_level is below filament-spid.minimum_level, or one of them is not a SPID level URI: logins are refused.', 'Request at least https://www.spid.gov.it/SpidL2 in spid-auth.sp_spid_level.');
        }

        $acs = Route::has('spid-auth_acs') ? route('spid-auth_acs') : 'not registered';
        $summary = sprintf('Level %s; ACS %s.', TypedConfig::string('spid-auth.sp_spid_level'), $acs);

        if (! str_starts_with($baseUrl, 'https://')) {
            return Result::warn('spid_config', $summary.' spid-auth.sp_base_url is not HTTPS.', 'Serve the SP over HTTPS: IdPs post assertions to an HTTPS ACS only.');
        }

        return Result::pass('spid_config', $summary);
    }
}
