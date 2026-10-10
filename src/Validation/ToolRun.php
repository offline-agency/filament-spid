<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Validation;

final class ToolRun
{
    public function __construct(
        public readonly ?int $exitCode,
        public readonly bool $timedOut,
    ) {}
}
