<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Tests\Fixtures;

/**
 * A user model whose table never got the SPID migration.
 */
class UserWithoutSpidColumns extends User
{
    protected $table = 'plain_users';
}
