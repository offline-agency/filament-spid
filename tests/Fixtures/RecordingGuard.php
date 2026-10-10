<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Tests\Fixtures;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;

/**
 * A stateful guard that is not a SessionGuard, recording logouts.
 */
class RecordingGuard implements StatefulGuard
{
    use GuardHelpers;

    public int $logouts = 0;

    public bool $throwOnLogout = false;

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function attempt(array $credentials = [], $remember = false): bool
    {
        return false;
    }

    public function once(array $credentials = []): bool
    {
        return false;
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        $this->user = $user;
    }

    public function loginUsingId($id, $remember = false): Authenticatable|false
    {
        return false;
    }

    public function onceUsingId($id): Authenticatable|false
    {
        return false;
    }

    public function viaRemember(): bool
    {
        return false;
    }

    public function logout(): void
    {
        $this->logouts++;

        if ($this->throwOnLogout) {
            throw new \RuntimeException('logout');
        }

        $this->user = null;
    }
}
