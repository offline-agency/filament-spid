<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Validation;

/**
 * What one metadata validation looked at and what the tool did.
 */
final class ValidationRun
{
    public ?string $sha256 = null;

    public ?int $bytes = null;

    public ?string $toolVersion = null;

    public ?int $toolExitCode = null;

    public string $toolStatus = 'not_run';

    /** @var array{executed: int, passed: int, failed: int, warnings: int} */
    public array $summary = ['executed' => 0, 'passed' => 0, 'failed' => 0, 'warnings' => 0];

    public function __construct(public readonly string $source, public readonly string $profile) {}
}
