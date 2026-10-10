<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install;

use Closure;

/**
 * One step of the installation plan. Built without side effects; apply()
 * performs it. A manual step only carries instructions.
 */
final class Change
{
    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const UNCHANGED = 'unchanged';

    public const RUN = 'run';

    public const MANUAL = 'manual';

    /**
     * @param  (Closure(): mixed)|null  $apply
     */
    public function __construct(
        public readonly string $status,
        public readonly string $target,
        public readonly string $description,
        public readonly ?string $detail = null,
        protected readonly ?Closure $apply = null,
    ) {}

    public function apply(): void
    {
        if ($this->apply !== null) {
            ($this->apply)();
        }
    }

    public function writes(): bool
    {
        return in_array($this->status, [self::CREATE, self::UPDATE, self::RUN], true);
    }
}
