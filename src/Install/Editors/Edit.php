<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install\Editors;

/**
 * The outcome of an editor: the new source, or why it was left alone.
 */
final class Edit
{
    public function __construct(
        public readonly string $source,
        public readonly bool $recognised,
        public readonly string $reason = '',
    ) {}

    public static function unrecognised(string $source, string $reason): self
    {
        return new self($source, false, $reason);
    }
}
