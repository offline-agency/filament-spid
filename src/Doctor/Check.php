<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor;

/**
 * One read-only diagnostic: no writes, no logins, no network.
 */
interface Check
{
    public function run(): Result;
}
