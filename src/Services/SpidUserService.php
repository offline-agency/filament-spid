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
use OfflineAgency\FilamentSpid\Events\SpidUserProvisioning;
use OfflineAgency\FilamentSpid\Events\SpidUserUpdated;
use OfflineAgency\FilamentSpid\Exceptions\SpidAccessDeniedException;
use OfflineAgency\FilamentSpid\Mapping\Contracts\CreateOnly;
use OfflineAgency\FilamentSpid\Mapping\FieldMapper;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

class SpidUserService
{
    /**
     * Find the citizen's account by fiscal code, creating or updating it as
     * configured.
     *
     * SpidUserProvisioning fires inside the transaction, then $authorize runs on
     * the provisioned user: when it returns false, SpidAccessDeniedException
     * rolls the creation or update back, listener changes included.
     * SpidUserCreated / SpidUserUpdated fire after the transaction.
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
            $user = $userModel::query()->where('fiscal_code', $spidData->fiscalNumber)->first();

            if (! $user && config('filament-spid.auto_create_users', false)) {
                $user = $this->createUser($spidData);
                $event = new SpidUserCreated($user, $spidData);
            } elseif ($user && config('filament-spid.update_user_data', true)) {
                $this->updateUser($user, $spidData);
                $event = new SpidUserUpdated($user, $spidData);
            }

            if ($user) {
                event(new SpidUserProvisioning($user, $spidData, $event instanceof SpidUserCreated));
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
        $callback = config('filament-spid.create_user_callback');

        if (is_callable($callback)) {
            $user = $callback($spidData);

            return $user instanceof Authenticatable
                ? $user
                : throw new \UnexpectedValueException('filament-spid.create_user_callback must return an Authenticatable user.');
        }

        $userModel = $this->resolveUserModel();

        $data = [];
        foreach ($this->fieldMapping() as $field => $mapper) {
            $data[$field] = FieldMapper::value($mapper, $spidData->toArray());
        }

        if (config('filament-spid.store_spid_data', true)) {
            $data['spid_data'] = $this->spidDataFor(new $userModel, $spidData);
        }

        return $userModel::create($data);
    }

    /**
     * @param  Model&Authenticatable  $user
     */
    protected function updateUser(Authenticatable $user, SpidUserData $spidData): void
    {
        $callback = config('filament-spid.update_user_callback');

        if (is_callable($callback)) {
            $callback($user, $spidData);

            return;
        }

        $data = [];

        foreach ($this->fieldMapping() as $field => $mapper) {
            if ($field !== 'fiscal_code' && ! $this->isCreateOnly($mapper)) {
                $data[$field] = FieldMapper::value($mapper, $spidData->toArray());
            }
        }

        if (config('filament-spid.store_spid_data', true)) {
            $data['spid_data'] = $this->spidDataFor($user, $spidData);
        }

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
        $model = config('filament-spid.user_model')
            ?: config('spid-auth.user_model')
            ?: User::class;

        if (is_string($model) && is_a($model, Model::class, true) && is_a($model, Authenticatable::class, true)) {
            return $model;
        }

        throw new \InvalidArgumentException('filament-spid.user_model must name an Eloquent model implementing Authenticatable.');
    }

    /**
     * filament-spid.field_mapping, keyed by column; entries without a column
     * name are ignored.
     *
     * @return array<string, mixed>
     */
    protected function fieldMapping(): array
    {
        return array_filter(TypedConfig::array('filament-spid.field_mapping'), is_string(...), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Encode the SPID payload the way the target model expects it.
     *
     * Models casting spid_data (as the README recommends) encode on write, so
     * handing them a JSON string would store double-encoded JSON. Only the
     * attributes listed in filament-spid.spid_data_attributes are kept (null
     * keeps all of them).
     *
     * @return array<string, mixed>|string
     */
    protected function spidDataFor(Authenticatable $user, SpidUserData $spidData): array|string
    {
        $payload = $spidData->toArray();

        $attributes = config('filament-spid.spid_data_attributes');
        if (is_array($attributes)) {
            $payload = array_intersect_key($payload, array_flip(array_filter($attributes, is_string(...))));
        }

        if ($user instanceof Model && $user->hasCast('spid_data')) {
            return $payload;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
