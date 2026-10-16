<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Commands;

use Illuminate\Console\Command;
use OfflineAgency\FilamentSpid\Doctor\Doctor;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read-only diagnostics of a filament-spid installation.
 *
 * Exit codes: 0 no failure; 1 a failure, or a warning with --strict;
 * 2 an internal error or an invalid invocation.
 */
class DoctorCommand extends Command
{
    public const EXIT_INTERNAL = 2;

    protected $signature = 'filament-spid:doctor
        {--json : Print the report as JSON only}
        {--strict : Exit with 1 on warnings too}';

    protected $description = 'Check the filament-spid installation (read-only: no writes, no logins, no network)';

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ExceptionInterface $e) {
            // An unknown option or argument: invalid invocation.
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return self::EXIT_INTERNAL;
        }
    }

    public function handle(Doctor $doctor): int
    {
        $report = $doctor->report();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->renderText($report);
        }

        return match ($report['overall_status']) {
            'error' => self::EXIT_INTERNAL,
            'fail' => self::FAILURE,
            'warn' => $this->option('strict') ? self::FAILURE : self::SUCCESS,
            default => self::SUCCESS,
        };
    }

    /**
     * @param  array{schema_version: int, overall_status: string, checks: list<array{id: string, status: string, message: string, remediation: string|null}>}  $report
     */
    protected function renderText(array $report): void
    {
        foreach ($report['checks'] as $check) {
            $this->line(sprintf('[%s] %s: %s', strtoupper($check['status']), $check['id'], $check['message']));

            if ($check['remediation'] !== null) {
                $this->line('       '.$check['remediation']);
            }
        }

        $this->newLine();
        $this->line('Overall: '.strtoupper($report['overall_status']));
    }
}
