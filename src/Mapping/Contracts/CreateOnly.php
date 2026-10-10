<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Mapping\Contracts;

/**
 * A field_mapping mapper applied when the account is created, never on later
 * logins (update_user_data skips it).
 */
interface CreateOnly {}
