<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;
use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Services\SpidUserService;

/**
 * The user model: resolvable, gated, and able to take the mapped columns.
 */
class UserModelCheck implements Check
{
    public function __construct(protected SpidUserService $users) {}

    public function run(): Result
    {
        try {
            $class = $this->users->resolveUserModel();
        } catch (\InvalidArgumentException) {
            return Result::fail('user_model', 'filament-spid.user_model does not name an Eloquent model implementing Authenticatable.', 'Set SPID_USER_MODEL (filament-spid.user_model) to your user model class.');
        }

        /** @var Model $model */
        $model = new $class;
        $provisioning = config('filament-spid.auto_create_users', false) ? 'creates accounts' : 'only signs in existing accounts';
        $summary = sprintf('%s; SPID %s.', $class, $provisioning);

        if (! $model instanceof FilamentUser) {
            return Result::warn('user_model', $summary.' It does not implement FilamentUser.', 'Implement Filament\Models\Contracts\FilamentUser::canAccessPanel() and decide there who may enter each panel.');
        }

        $columns = array_keys($this->users->fieldMapping());
        if (config('filament-spid.store_spid_data', true)) {
            $columns[] = 'spid_data';
        }

        $notFillable = array_values(array_filter($columns, fn (string $column) => ! $model->isFillable($column)));

        if ($notFillable !== [] && config('filament-spid.create_user_callback') === null) {
            return Result::warn('user_model', $summary.' Not fillable: '.implode(', ', $notFillable).'.', 'Add these columns to the model\'s $fillable, or remove them from filament-spid.field_mapping.');
        }

        return Result::pass('user_model', $summary);
    }
}
