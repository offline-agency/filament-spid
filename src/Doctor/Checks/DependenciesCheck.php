<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use Closure;
use Composer\InstalledVersions;
use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;

/**
 * The installed SAML stack, and the floors its advisories set.
 */
class DependenciesCheck implements Check
{
    /** Lowest releases without known advisories (CVE-2025-66475, CVE-2026-32313). */
    public const MINIMUM = [
        'onelogin/php-saml' => '4.3.1',
        'robrichards/xmlseclibs' => '3.1.5',
    ];

    public const REPORTED = [
        'offline-agency/filament-spid',
        'italia/spid-laravel',
        'onelogin/php-saml',
        'robrichards/xmlseclibs',
        'filament/filament',
        'livewire/livewire',
        'laravel/framework',
    ];

    /** @var Closure(string): (array{pretty: string, normalized: string}|null) */
    protected Closure $installed;

    /**
     * @param  (Closure(string): (array{pretty: string, normalized: string}|null))|null  $installed  null reads Composer's installed.json
     */
    public function __construct(?Closure $installed = null)
    {
        $this->installed = $installed ?? fn (string $package): ?array => InstalledVersions::isInstalled($package)
            ? ['pretty' => (string) InstalledVersions::getPrettyVersion($package), 'normalized' => (string) InstalledVersions::getVersion($package)]
            : null;
    }

    public function run(): Result
    {
        $versions = [];
        $problems = [];

        foreach (self::REPORTED as $package) {
            $installed = ($this->installed)($package);

            if ($installed === null) {
                $versions[] = "{$package} missing";
                $problems[] = "{$package} is not installed";

                continue;
            }

            $versions[] = $package.' '.$installed['pretty'];
            $minimum = self::MINIMUM[$package] ?? null;
            $version = $installed['normalized'];

            if ($minimum !== null && version_compare($version, $minimum, '<')) {
                $problems[] = "{$package} is below {$minimum}";
            }
        }

        $installed = implode(', ', $versions).'.';

        return $problems === []
            ? Result::pass('dependencies', $installed)
            : Result::fail('dependencies', implode('; ', $problems).'. Installed: '.$installed, 'Install italia/spid-laravel as the README describes, so php-saml >= 4.3.1 and xmlseclibs >= 3.1.5 are resolved, then run composer audit.');
    }
}
