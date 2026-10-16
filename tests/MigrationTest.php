<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

function migrationInstance(): Migration
{
    return include __DIR__.'/../database/migrations/add_spid_fields_to_users_table.php.stub';
}

describe('Migration', function () {
    it('adds fiscal_code column to users table', function () {
        expect(Schema::hasColumn('users', 'fiscal_code'))->toBeTrue();
    });

    it('adds spid_data column to users table', function () {
        expect(Schema::hasColumn('users', 'spid_data'))->toBeTrue();
    });

    it('fiscal_code column is nullable', function () {
        $columns = Schema::getColumns('users');
        $fiscalCode = collect($columns)->firstWhere('name', 'fiscal_code');

        expect($fiscalCode)->not->toBeNull()
            ->and($fiscalCode['nullable'])->toBeTrue();
    });

    it('spid_data column is nullable', function () {
        $columns = Schema::getColumns('users');
        $spidData = collect($columns)->firstWhere('name', 'spid_data');

        expect($spidData)->not->toBeNull()
            ->and($spidData['nullable'])->toBeTrue();
    });

    it('users table has required columns', function () {
        expect(Schema::hasColumn('users', 'id'))->toBeTrue()
            ->and(Schema::hasColumn('users', 'name'))->toBeTrue()
            ->and(Schema::hasColumn('users', 'email'))->toBeTrue();
    });
});

describe('Migration rollback', function () {
    it('removes the SPID columns', function () {
        migrationInstance()->down();

        expect(Schema::hasColumn('users', 'fiscal_code'))->toBeFalse()
            ->and(Schema::hasColumn('users', 'spid_data'))->toBeFalse();
    });

    it('is safe to run when the SPID columns are absent', function () {
        $migration = migrationInstance();
        $migration->down();

        $migration->down();

        expect(Schema::hasColumn('users', 'fiscal_code'))->toBeFalse();
    });

    it('leaves untouched columns in place', function () {
        migrationInstance()->down();

        expect(Schema::hasColumn('users', 'email'))->toBeTrue()
            ->and(Schema::hasColumn('users', 'name'))->toBeTrue();
    });
});

describe('Migration File', function () {
    it('migration file exists', function () {
        $migrationPath = __DIR__.'/../database/migrations/add_spid_fields_to_users_table.php.stub';

        expect(file_exists($migrationPath))->toBeTrue();
    });

    it('migration file is readable', function () {
        $migrationPath = __DIR__.'/../database/migrations/add_spid_fields_to_users_table.php.stub';

        expect(is_readable($migrationPath))->toBeTrue();
    });

    it('migration file contains up method', function () {
        $migrationPath = __DIR__.'/../database/migrations/add_spid_fields_to_users_table.php.stub';
        $content = file_get_contents($migrationPath);

        expect($content)->toContain('public function up()')
            ->and($content)->toContain('fiscal_code')
            ->and($content)->toContain('spid_data');
    });

    it('migration file contains down method', function () {
        $migrationPath = __DIR__.'/../database/migrations/add_spid_fields_to_users_table.php.stub';
        $content = file_get_contents($migrationPath);

        expect($content)->toContain('public function down()');
    });
});
