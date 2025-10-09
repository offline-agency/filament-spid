<?php

namespace OfflineAgency\FilamentSpid\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
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
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        // Set minimal SPID configuration for tests
        config()->set('spid-auth.sp_entity_id', 'https://test.local');
        config()->set('spid-auth.sp_base_url', 'https://test.local');
        config()->set('spid-auth.sp_service_name', 'Test Service');
        config()->set('spid-auth.sp_organization_name', 'Test Org');
        config()->set('spid-auth.user_model', \Illuminate\Foundation\Auth\User::class);

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
    }

    /**
     * Set up a fake Filament panel for testing
     */
    protected function setupFakeFilamentPanel(): Panel
    {
        $panel = Panel::make()
            ->id('admin')
            ->path('admin')
            ->login(\OfflineAgency\FilamentSpid\Pages\SpidLogin::class)
            ->default();

        // Register the panel with Filament
        \Filament\Facades\Filament::registerPanel($panel);

        return $panel;
    }

    /**
     * Set up a fake Filament panel with SPID plugin for testing
     */
    protected function setupFakeFilamentPanelWithPlugin(): Panel
    {
        $panel = $this->setupFakeFilamentPanel();
        
        // Register the SPID plugin with the panel
        $plugin = \OfflineAgency\FilamentSpid\SpidPlugin::make();
        $panel->plugin($plugin);

        return $panel;
    }
}
