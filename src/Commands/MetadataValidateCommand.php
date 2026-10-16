<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Commands;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Doctor\Status;
use OfflineAgency\FilamentSpid\Support\TypedConfig;
use OfflineAgency\FilamentSpid\Validation\MetadataFetcher;
use OfflineAgency\FilamentSpid\Validation\MetadataUnavailable;
use OfflineAgency\FilamentSpid\Validation\SpidSpTest;
use OfflineAgency\FilamentSpid\Validation\ToolReport;
use OfflineAgency\FilamentSpid\Validation\ValidationRun;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Validates the SP metadata with spid_sp_test (italia/spid-sp-test).
 *
 * A thin adapter: the SPID rules, XSDs and signature checks are the tool's.
 * It only obtains the exact metadata bytes, runs the pinned tool and reads
 * its exit code AND its report: a missing tool, a crash, a timeout, an empty
 * or unreadable report are errors, never a pass.
 *
 * Exit codes: 0 no failed check; 1 a failed check (or a warning with
 * --strict); 2 an error or an invalid invocation.
 */
class MetadataValidateCommand extends Command
{
    public const SCHEMA_VERSION = 1;

    public const PROFILES = ['spid-sp-public', 'spid-sp-private'];

    protected $signature = 'filament-spid:metadata:validate
        {--url= : HTTPS URL of the published metadata}
        {--file= : Metadata file, as downloaded}
        {--profile= : spid-sp-public or spid-sp-private (default: from filament-spid.sp_profile)}
        {--report= : Write the JSON report to this path (keep it private)}
        {--json : Print the JSON report only}
        {--strict : Exit with 1 on warnings too}
        {--tool= : spid_sp_test executable (default: filament-spid.validation.spid_sp_test)}
        {--ca-file= : CA bundle to verify the metadata host with (a local lab CA)}
        {--timeout=300 : Seconds for the download and for the tool}';

    protected $description = 'Validate the SP metadata with italia/spid-sp-test (no upload to third parties)';

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ExceptionInterface $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return DoctorCommand::EXIT_INTERNAL;
        }
    }

    public function handle(MetadataFetcher $fetcher): int
    {
        $url = $this->stringOption('url');
        $file = $this->stringOption('file');
        $timeout = max(1, (int) $this->stringOption('timeout'));

        $invalid = match (true) {
            $url === null && $file === null => 'Pass --url or --file.',
            $url !== null && $file !== null => 'Pass --url or --file, not both.',
            $url !== null && ! str_starts_with($url, 'https://') => 'The metadata URL must be https://: IdPs only fetch metadata over TLS.',
            default => null,
        };
        $profile = $this->profile();
        $invalid ??= $profile === null ? 'Pass --profile=spid-sp-public or spid-sp-private, or set FILAMENT_SPID_SP_PROFILE: the profile is not guessed.' : null;
        $invalid ??= $file !== null && (! is_file($file) || ! is_readable($file)) ? "--file: {$file} is not a readable file." : null;

        if ($invalid !== null || $profile === null) {
            $this->error((string) $invalid);

            return DoctorCommand::EXIT_INTERNAL;
        }

        $run = new ValidationRun($url ?? (string) $file, $profile);

        try {
            $bytes = $url !== null
                ? $fetcher->fetch($url, $timeout, $this->stringOption('ca-file'))
                : MetadataFetcher::ensureMetadata((string) file_get_contents((string) $file), (string) $file);
        } catch (MetadataUnavailable $e) {
            return $this->finish($run, [Result::fail('metadata', $e->getMessage(), 'Check that the URL serves the metadata itself (php artisan route:list --name=spid-auth_metadata) and that spid-auth.expose_sp_metadata is true.')]);
        }

        $run->sha256 = hash('sha256', $bytes);
        $run->bytes = strlen($bytes);

        return $this->validate($run, $bytes, $timeout);
    }

    protected function validate(ValidationRun $run, string $bytes, int $timeout): int
    {
        $tool = new SpidSpTest($this->stringOption('tool') ?? (TypedConfig::string('filament-spid.validation.spid_sp_test') ?? 'spid_sp_test'), $timeout);
        $executable = $tool->executable();

        if ($executable === null) {
            $run->toolStatus = 'missing';

            return $this->finish($run, [Result::fail('spid_sp_test', 'spid_sp_test was not found.', 'Install it: '.SpidSpTest::INSTALL.'.')]);
        }

        $run->toolVersion = $tool->version($executable);
        if ($run->toolVersion !== SpidSpTest::VERSION) {
            $run->toolStatus = 'unsupported_version';

            return $this->finish($run, [Result::fail('spid_sp_test', 'spid_sp_test '.($run->toolVersion ?? 'of unknown version').' is installed; this adapter is tested with '.SpidSpTest::VERSION.'.', 'Install: '.SpidSpTest::INSTALL.'.')]);
        }

        // The exact bytes, in a private working directory.
        $workdir = sys_get_temp_dir().'/filament-spid-validate-'.bin2hex(random_bytes(8));
        mkdir($workdir, 0700, true);
        $metadataFile = $workdir.'/metadata.xml';
        $rawReport = $workdir.'/spid_sp_test.json';
        file_put_contents($metadataFile, $bytes);

        try {
            $result = $tool->run($executable, $metadataFile, $run->profile, $rawReport);
            $parsed = is_file($rawReport) ? ToolReport::parse((string) file_get_contents($rawReport)) : null;
        } finally {
            @unlink($metadataFile);
            @unlink($rawReport);
            @rmdir($workdir);
        }

        $run->toolExitCode = $result->exitCode;

        [$status, $message] = match (true) {
            $result->timedOut => ['timeout', "spid_sp_test did not finish within {$timeout} seconds."],
            ! in_array($result->exitCode, [0, 1], true) => ['crashed', "spid_sp_test exited with {$result->exitCode}."],
            $parsed === null => ['unparseable', 'spid_sp_test wrote no report this adapter can read.'],
            $parsed->checks === [] => ['no_checks', 'spid_sp_test ran no check.'],
            $parsed->dependencyMissing => ['dependency_missing', 'spid_sp_test could not run some checks: a system dependency is missing (xmlsec1).'],
            // Exit 1 means a failure: a report without one hides it.
            $result->exitCode === 1 && $parsed->summary()['failed'] === 0 => ['inconsistent', 'spid_sp_test exited with 1 but its report lists no failed check.'],
            default => ['ok', ''],
        };
        $run->toolStatus = $status;

        if ($status !== 'ok' || $parsed === null) {
            return $this->finish($run, [Result::fail('spid_sp_test', $message, 'Install: '.SpidSpTest::INSTALL.'.'), ...($parsed->checks ?? [])]);
        }

        $run->summary = $parsed->summary();

        return $this->finish($run, $parsed->checks, toolReported: true);
    }

    /**
     * @param  list<Result>  $checks
     */
    protected function finish(ValidationRun $run, array $checks, bool $toolReported = false): int
    {
        $statuses = array_map(fn (Result $check) => $check->status, $checks);
        $status = match (true) {
            ! $toolReported => 'error',
            in_array(Status::Fail, $statuses, true) => 'fail',
            in_array(Status::Warn, $statuses, true) => 'warn',
            default => 'pass',
        };

        $report = [
            'schema_version' => self::SCHEMA_VERSION,
            'overall_status' => $status,
            'evidence' => [
                match ($status) {
                    'pass', 'warn' => 'LOCAL_METADATA_CHECK_PASSED',
                    'fail' => 'LOCAL_METADATA_CHECK_FAILED',
                    default => 'LOCAL_METADATA_CHECK_ERROR',
                },
                // This command never contacts the hosted Validator or AgID.
                'HOSTED_VALIDATOR_NOT_RUN',
                'FORMAL_AGID_ACCEPTANCE_NOT_OBTAINED',
            ],
            'validated_at' => now()->toIso8601String(),
            'profile' => $run->profile,
            'plugin' => $this->pluginVersion(),
            'metadata' => ['source' => $run->source, 'sha256' => $run->sha256, 'bytes' => $run->bytes],
            'tool' => ['name' => 'spid_sp_test', 'version' => $run->toolVersion, 'exit_code' => $run->toolExitCode, 'status' => $run->toolStatus],
            'summary' => $run->summary,
            'checks' => array_map(fn (Result $check) => $check->toArray(), $checks),
        ];
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $path = $this->stringOption('report');
        if ($path !== null) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            file_put_contents($path, $json."\n");
        }

        if ($this->option('json')) {
            $this->line($json);
        } else {
            foreach ($checks as $check) {
                if ($check->status !== Status::Pass) {
                    $this->line(sprintf('[%s] %s: %s', strtoupper($check->status->value), $check->id, $check->message));
                }
            }
            $this->newLine();
            $this->line(sprintf('Metadata sha256 %s, profile %s, spid_sp_test %s.', $run->sha256 ?? 'n/a', $run->profile, $run->toolVersion ?? 'n/a'));
            $this->line('Evidence: '.implode(', ', $report['evidence']));
        }

        return match ($status) {
            'error' => DoctorCommand::EXIT_INTERNAL,
            'fail' => self::FAILURE,
            'warn' => $this->option('strict') ? self::FAILURE : self::SUCCESS,
            default => self::SUCCESS,
        };
    }

    protected function profile(): ?string
    {
        $option = $this->stringOption('profile');
        if ($option !== null) {
            return in_array($option, self::PROFILES, true) ? $option : null;
        }

        return match (TypedConfig::string('filament-spid.sp_profile')) {
            'public' => 'spid-sp-public',
            'private' => 'spid-sp-private',
            default => null,
        };
    }

    /**
     * @return array{version: string, reference: string|null}
     */
    protected function pluginVersion(): array
    {
        $package = 'offline-agency/filament-spid';

        return InstalledVersions::isInstalled($package)
            ? ['version' => (string) InstalledVersions::getPrettyVersion($package), 'reference' => InstalledVersions::getReference($package)]
            : ['version' => 'unknown', 'reference' => null];
    }

    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
