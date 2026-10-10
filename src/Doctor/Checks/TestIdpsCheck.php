<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

/**
 * Test and validator IdPs, judged by filament-spid.deployment (or APP_ENV).
 *
 * Production trusts none. During the AgID acceptance test the procedure lets
 * the SP trust the official Validator, and only it, for the time of the test:
 * that is reported as a warning to remove afterwards, never as a pass. The
 * official Validator is recognised by its entityID and endpoints, not by name.
 */
class TestIdpsCheck implements Check
{
    public const OFFICIAL_VALIDATOR = 'https://validator.spid.gov.it';

    public const OFFICIAL_VALIDATOR_METADATA = 'https://validator.spid.gov.it/metadata.xml';

    public function run(): Result
    {
        $deployment = TypedConfig::string('filament-spid.deployment') ?? (app()->isProduction() ? 'production' : 'local');

        $testIdp = (bool) config('spid-auth.test_idp');
        $hideReal = (bool) config('spid-auth.hide_real_idps');
        $validator = config('spid-auth.validator_idp');
        $official = $this->isOfficialValidator($validator);

        $enabled = array_keys(array_filter([
            'spid-auth.test_idp' => $testIdp,
            'spid-auth.validator_idp' => (bool) $validator,
            'spid-auth.hide_real_idps' => $hideReal,
        ]));

        if ($deployment === 'local') {
            return Result::pass('test_idps', $enabled === [] ? 'No test or validator IdP is enabled, and real IdPs are shown.' : 'Local deployment: '.implode(', ', $enabled).' enabled.');
        }

        if ($deployment === 'acceptance') {
            if ($testIdp || $hideReal || ($validator && ! $official)) {
                return Result::fail('test_idps', 'During the acceptance test only the official AgID Validator may be trusted; enabled: '.implode(', ', $enabled).'.', 'Set test_idp and hide_real_idps to false, and configure validator_idp only from '.self::OFFICIAL_VALIDATOR_METADATA.'.');
            }

            if (! $official) {
                return Result::warn('test_idps', 'Acceptance deployment, but the official Validator is not configured.', 'Configure validator_idp from '.self::OFFICIAL_VALIDATOR_METADATA.' for the acceptance test.');
            }

            return Result::warn('test_idps', 'AgID acceptance window: the official Validator is trusted. This is not a production configuration.', 'When the AgID acceptance test is over, set validator_idp to false and FILAMENT_SPID_DEPLOYMENT=production.');
        }

        if ($enabled !== []) {
            return Result::fail('test_idps', 'Production deployment: '.implode(', ', $enabled).' enabled.', 'Set test_idp and validator_idp to false and hide_real_idps to false in config/spid-auth.php.');
        }

        return Result::pass('test_idps', 'No test or validator IdP is enabled, and real IdPs are shown.');
    }

    protected function isOfficialValidator(mixed $validator): bool
    {
        if (! is_array($validator) || ($validator['entityId'] ?? null) !== self::OFFICIAL_VALIDATOR) {
            return false;
        }

        foreach (['sso_endpoint', 'slo_endpoint'] as $endpoint) {
            $url = $validator[$endpoint] ?? null;
            if (! is_string($url) || parse_url($url, PHP_URL_HOST) !== 'validator.spid.gov.it' || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                return false;
            }
        }

        return true;
    }
}
