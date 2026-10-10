<?php

namespace OfflineAgency\FilamentSpid;

use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Italia\SPIDAuth\Events\LoginEvent;
use Italia\SPIDAuth\Events\LogoutEvent;
use Italia\SPIDAuth\SPIDAuth;
use OfflineAgency\FilamentSpid\Constants\SpidLevel;
use OfflineAgency\FilamentSpid\Listeners\HandleSpidLogin;
use OfflineAgency\FilamentSpid\Listeners\HandleSpidLogout;
use OfflineAgency\FilamentSpid\Support\TypedConfig;
use OfflineAgency\FilamentSpid\Support\Warning;
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
        // italia/spid-laravel only binds the 'SPIDAuth' alias, so resolving the
        // class would build a second, unrelated instance. Point it at the
        // library singleton instead of registering one of our own.
        $this->app->bind(SPIDAuth::class, fn (Application $app) => $app->make('SPIDAuth'));
    }

    public function packageBooted(): void
    {
        // Authentication core: italia/spid-laravel fires these once the SAML
        // response is validated, so consumer apps need no listeners of their own.
        if (config('filament-spid.register_listeners', true)) {
            Event::listen(LoginEvent::class, HandleSpidLogin::class);
            Event::listen(LogoutEvent::class, HandleSpidLogout::class);
        }

        // HandleSpidLogin refuses these logins; say why before anyone tries.
        if (config('filament-spid.enabled', true) && ! SpidLevel::requestedMeetsMinimum()) {
            Warning::once(SpidLevel::minimum() === null
                ? sprintf(
                    'filament-spid: filament-spid.minimum_level [%s] is not a SPID level URI; SPID logins will be refused.',
                    TypedConfig::string('filament-spid.minimum_level'),
                )
                : sprintf(
                    'filament-spid: spid-auth.sp_spid_level [%s] is below filament-spid.minimum_level [%s]; SPID logins will be refused.',
                    TypedConfig::string('spid-auth.sp_spid_level'),
                    TypedConfig::string('filament-spid.minimum_level'),
                ));
        }

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

        // Convenience copy of the library config on first artisan run. Guarded to
        // console: writing into config_path() while serving requests is a
        // surprising side effect and fails outright on a read-only filesystem.
        if (! $this->app->runningInConsole()) {
            return;
        }

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

    protected function getAssetPackageName(): string
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
            // jQuery 3.7.1 for the AgID SPID button, served from the app
            // (php artisan filament:assets) instead of a third-party CDN.
            // Loaded on request: only the button's loader pulls it in, and
            // only when the page has no jQuery of its own.
            Js::make('spid-jquery', __DIR__.'/../resources/dist/jquery.min.js')->loadedOnRequest(),
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
