<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Exceptions;

use RuntimeException;

/**
 * The authorisation check refused the provisioned user. Thrown inside the
 * provisioning transaction, so a refused account is never committed.
 */
class SpidAccessDeniedException extends RuntimeException
{
    public static function make(): self
    {
        return new self('The user may not access the panel.');
    }
}
