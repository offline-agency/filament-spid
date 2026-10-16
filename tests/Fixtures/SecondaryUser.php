<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Tests\Fixtures;

/**
 * A user model on a connection other than the default one.
 */
class SecondaryUser extends User
{
    protected $connection = 'secondary';
}
