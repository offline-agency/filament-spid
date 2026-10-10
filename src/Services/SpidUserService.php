<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Services;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Events\SpidUserCreated;
use OfflineAgency\FilamentSpid\Events\SpidUserUpdated;
use OfflineAgency\FilamentSpid\Exceptions\SpidAccessDeniedException;
use OfflineAgency\FilamentSpid\Mapping\Contracts\CreateOnly;
use OfflineAgency\FilamentSpid\Mapping\FieldMapper;

class SpidUserService
{
    /**
     * Find the citizen's account by fiscal code, creating or updating it as
     * configured.
     *
     * $authorize runs on the provisioned user inside the transaction: when it
     * returns false, SpidAccessDeniedException rolls the creation or update
     * back. The user events fire only once the transaction has committed.
     *
     * @param  (Closure(Authenticatable): bool)|null  $authorize
     *
     * @throws SpidAccessDeniedException
     */
    public function findOrCreateUser(SpidUserData $spidData, ?Closure $authorize = null): ?Authenticatable
    {
        $userModel = $this->resolveUserModel();

        $event = null;

        $user = DB::transaction(function () use ($userModel, $spidData, $authorize, &$event): ?Authenticatable {
            $user = $userModel::where('fiscal_code', $spidData->fiscalNumber)->first();

            if (! $user && config('filament-spid.auto_create_users', false)) {
                $user = $this->createUser($spidData);
                $event = new SpidUserCreated($user, $spidData);
            } elseif ($user && config('filament-spid.update_user_data', true)) {
                $this->updateUser($user, $spidData);
                $event = new SpidUserUpdated($user, $spidData);
            }

            if ($user && $authorize && ! $authorize($user)) {
                throw SpidAccessDeniedException::make();
            }

            return $user;
        });

        if ($event) {
            event($event);
        }

        return $user;
    }

    protected function createUser(SpidUserData $spidData): Authenticatable
    {
        if ($callback = config('filament-spid.create_user_callback')) {
            return $callback($spidData);
        }

        $userModel = $this->resolveUserModel();
        $mapping = config('filament-spid.field_mapping', []);

        $data = [];
        foreach ($mapping as $field => $mapper) {
            $data[$field] = FieldMapper::value($mapper, $spidData->toArray());
        }

        $data['spid_data'] = $this->spidDataFor(new $userModel, $spidData);

        return $userModel::create($data);
    }

    /**
     * @param  Model&Authenticatable  $user
     */
    protected function updateUser(Authenticatable $user, SpidUserData $spidData): void
    {
        if ($callback = config('filament-spid.update_user_callback')) {
            $callback($user, $spidData);

            return;
        }

        $mapping = config('filament-spid.field_mapping', []);
        $data = [];

        foreach ($mapping as $field => $mapper) {
            if ($field !== 'fiscal_code' && ! $this->isCreateOnly($mapper)) {
                $data[$field] = FieldMapper::value($mapper, $spidData->toArray());
            }
        }

        $data['spid_data'] = $this->spidDataFor($user, $spidData);

        $user->update($data);
    }

    /**
     * Whether the mapper only runs on creation, checked on the class name
     * without instantiating it.
     */
    protected function isCreateOnly(mixed $mapper): bool
    {
        return is_string($mapper)
            ? is_a($mapper, CreateOnly::class, true)
            : $mapper instanceof CreateOnly;
    }

    /**
     * Resolve the configured user model.
     *
     * filament-spid.user_model is the documented key; spid-auth.user_model is
     * honoured for backward compatibility with setups configured before it existed.
     *
     * @return class-string<Model&Authenticatable>
     */
    protected function resolveUserModel(): string
    {
        return config('filament-spid.user_model')
            ?: config('spid-auth.user_model')
            ?: User::class;
    }

    /**
     * Encode the SPID payload the way the target model expects it.
     *
     * Models casting spid_data (as the README recommends) encode on write, so
     * handing them a JSON string would store double-encoded JSON.
     *
     * @return array<string, mixed>|string
     */
    protected function spidDataFor(Authenticatable $user, SpidUserData $spidData): array|string
    {
        $payload = $spidData->toArray();

        if ($user instanceof Model && $user->hasCast('spid_data')) {
            return $payload;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
