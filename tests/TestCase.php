<?php

namespace OfflineAgency\FilamentSpid\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Italia\SPIDAuth\ServiceProvider as SPIDAuthServiceProvider;
use Livewire\LivewireServiceProvider;
use OfflineAgency\FilamentSpid\FilamentSpidServiceProvider;
use OfflineAgency\FilamentSpid\Pages\SpidLogin;
use OfflineAgency\FilamentSpid\SpidPlugin;
use OfflineAgency\FilamentSpid\Tests\Fixtures\PanelUser;
use OfflineAgency\FilamentSpid\Tests\Fixtures\TestPanelProvider;
use OfflineAgency\FilamentSpid\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'OfflineAgency\\FilamentSpid\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            SPIDAuthServiceProvider::class,
            FilamentSpidServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        // The web middleware group encrypts cookies.
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));

        // Set minimal SPID configuration for tests
        config()->set('spid-auth.sp_entity_id', 'https://test.local');
        config()->set('spid-auth.sp_base_url', 'https://test.local');
        config()->set('spid-auth.sp_service_name', 'Test Service');
        config()->set('spid-auth.sp_organization_name', 'Test Org');
        // The library defaults to SpidL1; admin panels need at least SpidL2.
        config()->set('spid-auth.sp_spid_level', 'https://www.spid.gov.it/SpidL2');
        config()->set('spid-auth.user_model', User::class);
        // A model with Filament's gate: without one, logins are refused outside
        // the local environment, as Filament refuses them.
        config()->set('filament-spid.user_model', PanelUser::class);

        // Create users table first
        $app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Run SPID migration
        $migration = include __DIR__.'/../database/migrations/add_spid_fields_to_users_table.php.stub';
        $migration->up();

        // A second database, for user models on a connection of their own.
        config()->set('database.connections.secondary', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['db']->connection('secondary')->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('fiscal_code')->unique()->nullable();
            $table->json('spid_data')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Set up a fake Filament panel for testing
     */
    protected function setupFakeFilamentPanel(): Panel
    {
        $panel = Panel::make()
            ->id('admin')
            ->path('admin')
            ->login(SpidLogin::class)
            ->default();

        // Register the panel with Filament
        Filament::registerPanel($panel);

        return $panel;
    }

    /**
     * Set up a fake Filament panel with SPID plugin for testing
     */
    protected function setupFakeFilamentPanelWithPlugin(): Panel
    {
        $panel = $this->setupFakeFilamentPanel();

        // Register the SPID plugin with the panel
        $plugin = SpidPlugin::make();
        $panel->plugin($plugin);

        return $panel;
    }
}
