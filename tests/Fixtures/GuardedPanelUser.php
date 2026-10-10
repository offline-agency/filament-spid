<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Tests\Fixtures;

/**
 * A panel user whose $fillable leaves the SPID columns out.
 */
class GuardedPanelUser extends PanelUser
{
    protected $guarded = ['*'];

    protected $fillable = ['name', 'email'];
}
