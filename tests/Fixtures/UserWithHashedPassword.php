<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Tests\Fixtures;

/**
 * User model hashing its password on write, as Laravel's stock User does.
 */
class UserWithHashedPassword extends PanelUser
{
    protected $casts = [
        'password' => 'hashed',
    ];
}
