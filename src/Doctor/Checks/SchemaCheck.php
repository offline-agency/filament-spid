<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use Illuminate\Database\Eloquent\Model;
use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Services\SpidUserService;

/**
 * The SPID columns on the user model's table, read through the schema
 * builder of the model's own connection. Nothing is written.
 */
class SchemaCheck implements Check
{
    public function __construct(protected SpidUserService $users) {}

    public function run(): Result
    {
        try {
            $class = $this->users->resolveUserModel();
        } catch (\InvalidArgumentException) {
            return Result::skip('schema', 'Not checked: the user model cannot be resolved.', 'Fix the user_model check first.');
        }

        /** @var Model $model */
        $model = new $class;
        $table = $model->getTable();

        try {
            $schema = $model->getConnection()->getSchemaBuilder();

            if (! $schema->hasTable($table)) {
                return Result::fail('schema', "Table [{$table}] does not exist.", 'Run php artisan migrate.');
            }

            $required = config('filament-spid.store_spid_data', true) ? ['fiscal_code', 'spid_data'] : ['fiscal_code'];
            $missing = array_values(array_filter($required, fn (string $column) => ! $schema->hasColumn($table, $column)));

            if ($missing !== []) {
                return Result::fail('schema', "Table [{$table}] lacks: ".implode(', ', $missing).'.', 'php artisan vendor:publish --tag=filament-spid-migrations && php artisan migrate');
            }

            $unique = array_filter($schema->getIndexes($table), fn (array $index) => $index['unique'] && $index['columns'] === ['fiscal_code']);
        } catch (\Throwable $e) {
            return Result::warn('schema', 'The database could not be read: '.$e::class.'.', 'Run the doctor where the application database is reachable.');
        }

        if ($unique === []) {
            return Result::warn('schema', "Table [{$table}] has no unique index on fiscal_code.", 'Add one: two accounts with the same fiscal code make SPID logins ambiguous.');
        }

        return Result::pass('schema', "Table [{$table}] has fiscal_code (unique)".(in_array('spid_data', $required, true) ? ' and spid_data.' : '.'));
    }
}
