<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Events\SpidUserCreated;
use OfflineAgency\FilamentSpid\Events\SpidUserUpdated;

class SpidUserService
{
    public function findOrCreateUser(SpidUserData $spidData): ?Authenticatable
    {
        $userModel = config('spid-auth.user_model', \App\Models\User::class);

        return DB::transaction(function () use ($userModel, $spidData) {
            $user = $userModel::where('fiscal_code', $spidData->fiscalNumber)->first();

            if (! $user && config('filament-spid.auto_create_users', true)) {
                $user = $this->createUser($spidData);
                event(new SpidUserCreated($user, $spidData));
            } elseif ($user && config('filament-spid.update_user_data', true)) {
                $this->updateUser($user, $spidData);
                event(new SpidUserUpdated($user, $spidData));
            }

            return $user;
        });
    }

    protected function createUser(SpidUserData $spidData): Authenticatable
    {
        if ($callback = config('filament-spid.create_user_callback')) {
            return $callback($spidData);
        }

        $userModel = config('spid-auth.user_model');
        $mapping = config('filament-spid.field_mapping', []);

        $data = [];
        foreach ($mapping as $field => $mapper) {
            $data[$field] = is_callable($mapper) ? $mapper($spidData->toArray()) : $spidData->{$mapper};
        }

        $data['spid_data'] = json_encode($spidData->toArray());

        return $userModel::create($data);
    }

    protected function updateUser(Authenticatable $user, SpidUserData $spidData): void
    {
        if ($callback = config('filament-spid.update_user_callback')) {
            $callback($user, $spidData);

            return;
        }

        $mapping = config('filament-spid.field_mapping', []);
        $data = [];

        foreach ($mapping as $field => $mapper) {
            if ($field !== 'fiscal_code') {
                $data[$field] = is_callable($mapper) ? $mapper($spidData->toArray()) : $spidData->{$mapper};
            }
        }

        $data['spid_data'] = json_encode($spidData->toArray());

        $user->update($data);
    }
}
