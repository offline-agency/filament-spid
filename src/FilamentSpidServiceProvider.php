<?php

namespace OfflineAgency\FilamentSpid;

use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Italia\SPIDAuth\SPIDAuth;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentSpidServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-spid';

    public static string $viewNamespace = 'filament-spid';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigration('add_spid_fields_to_users_table');
    }

    public function packageRegistered(): void
    {
        $this->app->bind(SPIDAuth::class, function () {
            return new SPIDAuth;
        });

        // Register custom CSRF middleware with proper dependencies (avoid singleton for Octane compatibility)
        $this->app->bind(VerifyCsrfToken::class, function ($app) {
            return new Http\Middleware\VerifyCsrfToken(
                $app, $app->make(Encrypter::class)
            );
        });
    }

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Publish SPID (spid-laravel) config files for convenience
        $spidVendorConfigPath = base_path('vendor/italia/spid-laravel/config');
        $appConfigPath = config_path();

        $this->publishes([
            $spidVendorConfigPath.'/spid-auth.php' => $appConfigPath.'/spid-auth.php',
            $spidVendorConfigPath.'/spid-idps.php' => $appConfigPath.'/spid-idps.php',
        ], 'filament-spid-config');

        // Publish images. publishes() is keyed by source path, so a directory can
        // only ever reach one target: previously public/images silently won and
        // public/vendor/filament-spid/images — the path the views ask for — was
        // never written.
        $this->publishes([
            __DIR__.'/../resources/images' => public_path('vendor/filament-spid/images'),
        ], 'filament-spid-images');

        // Auto-copy on first boot if missing (idempotent)
        try {
            /** @var Filesystem $files */
            $files = $this->app->make(Filesystem::class);
            if ($files->exists($spidVendorConfigPath.'/spid-auth.php') && ! $files->exists($appConfigPath.'/spid-auth.php')) {
                $files->copy($spidVendorConfigPath.'/spid-auth.php', $appConfigPath.'/spid-auth.php');
            }
            if ($files->exists($spidVendorConfigPath.'/spid-idps.php') && ! $files->exists($appConfigPath.'/spid-idps.php')) {
                $files->copy($spidVendorConfigPath.'/spid-idps.php', $appConfigPath.'/spid-idps.php');
            }
        } catch (\Throwable $e) {
            // Silently ignore copy errors; developer can publish manually
        }
    }

    protected function getAssetPackageName(): ?string
    {
        return 'offline-agency/filament-spid';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            Css::make('filament-spid-styles', __DIR__.'/../resources/dist/filament-spid.css'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }
}
