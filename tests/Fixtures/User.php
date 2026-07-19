<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal user model backing the package test suite.
 *
 * Mirrors the columns created in TestCase::getEnvironmentSetUp() plus the
 * SPID columns added by the package migration.
 */
class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
