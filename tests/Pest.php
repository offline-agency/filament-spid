<?php

use OfflineAgency\FilamentSpid\Tests\InstallTestCase;
use OfflineAgency\FilamentSpid\Tests\TestCase;

require_once __DIR__.'/Install/helpers.php';

// The installer tests run on a throwaway copy of the application skeleton.
uses(InstallTestCase::class)->in(__DIR__.'/Install');
uses(TestCase::class)->in(...glob(__DIR__.'/*Test.php'));
