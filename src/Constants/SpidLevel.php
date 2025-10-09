<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Constants;

enum SpidLevel: string
{
    case LEVEL_1 = 'https://www.spid.gov.it/SpidL1';
    case LEVEL_2 = 'https://www.spid.gov.it/SpidL2';
    case LEVEL_3 = 'https://www.spid.gov.it/SpidL3';
}
