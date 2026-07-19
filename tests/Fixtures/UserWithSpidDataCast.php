<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Tests\Fixtures;

/**
 * User model casting spid_data to array, as the README instructs consumers to do.
 */
class UserWithSpidDataCast extends User
{
    protected $casts = [
        'spid_data' => 'array',
    ];
}
