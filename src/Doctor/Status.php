<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor;

enum Status: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
    // Not observable from here: never a success.
    case Skip = 'skip';
}
