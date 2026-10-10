<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Validation;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs spid_sp_test (italia/spid-sp-test), the CLI AgID's Validator suite is
 * built on. Arguments are passed as an array, never through a shell.
 */
class SpidSpTest
{
    /** The version this adapter was written and tested against. */
    public const VERSION = '1.2.18';

    public const INSTALL = 'uv tool install spid-sp-test==1.2.18 (or: pipx install spid-sp-test==1.2.18); signature checks also need the xmlsec1 binary';

    public function __construct(protected string $binary, protected int $timeout) {}

    /**
     * The executable, or null when it cannot be found.
     */
    public function executable(): ?string
    {
        if (str_contains($this->binary, DIRECTORY_SEPARATOR)) {
            return is_file($this->binary) && is_executable($this->binary) ? $this->binary : null;
        }

        return (new ExecutableFinder)->find($this->binary);
    }

    public function version(string $executable): ?string
    {
        $process = new Process([$executable, '--version']);
        $process->setTimeout(30);
        $process->run();

        return preg_match('/(\d+\.\d+\.\d+)/', $process->getOutput(), $match) === 1 ? $match[1] : null;
    }

    /**
     * Validate a metadata file; the JSON report is written to $report.
     */
    public function run(string $executable, string $metadataFile, string $profile, string $report): ToolRun
    {
        $process = new Process([
            $executable,
            '--metadata-url', 'file://'.$metadataFile,
            '--profile', $profile,
            '--extra',
            '-rf', 'json',
            '-o', $report,
        ]);
        $process->setTimeout($this->timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return new ToolRun(null, true);
        }

        return new ToolRun($process->getExitCode(), false);
    }
}
