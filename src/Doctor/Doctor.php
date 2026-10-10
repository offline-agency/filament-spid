<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor;

use OfflineAgency\FilamentSpid\Doctor\Checks\AssetsCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\CertificateCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\CspCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\CsrfCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\DependenciesCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\HttpsSessionCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\PanelCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\SchemaCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\SpidConfigCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\SpidPatchCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\TestIdpsCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\UserModelCheck;

/**
 * Runs the checks behind php artisan filament-spid:doctor.
 */
class Doctor
{
    public const SCHEMA_VERSION = 1;

    /** @var list<Check> */
    protected array $checks;

    /**
     * @param  list<Check>|null  $checks  null runs the default set
     */
    public function __construct(?array $checks = null)
    {
        $this->checks = $checks ?? array_map(fn (string $class): Check => app($class), [
            DependenciesCheck::class,
            SpidPatchCheck::class,
            PanelCheck::class,
            UserModelCheck::class,
            SchemaCheck::class,
            SpidConfigCheck::class,
            CsrfCheck::class,
            AssetsCheck::class,
            HttpsSessionCheck::class,
            CspCheck::class,
            CertificateCheck::class,
            TestIdpsCheck::class,
        ]);
    }

    /**
     * @return array{schema_version: int, overall_status: string, checks: list<array{id: string, status: string, message: string, remediation: string|null}>}
     */
    public function report(): array
    {
        $results = [];
        $crashed = false;

        foreach ($this->checks as $check) {
            try {
                $results[] = $check->run();
            } catch (\Throwable $e) {
                // The class only: messages may carry data the report must not.
                $crashed = true;
                $results[] = Result::fail(
                    $check::class,
                    'The check could not run: '.$e::class.'.',
                    'Report it at https://github.com/offline-agency/filament-spid/issues with the exception class.',
                );
            }
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'overall_status' => $crashed ? 'error' : self::overall($results),
            'checks' => array_map(fn (Result $result) => $result->toArray(), $results),
        ];
    }

    /**
     * @param  list<Result>  $results
     */
    protected static function overall(array $results): string
    {
        $statuses = array_map(fn (Result $result) => $result->status, $results);

        return match (true) {
            in_array(Status::Fail, $statuses, true) => Status::Fail->value,
            in_array(Status::Warn, $statuses, true) => Status::Warn->value,
            default => Status::Pass->value,
        };
    }
}
