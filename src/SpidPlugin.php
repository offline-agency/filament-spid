<?php

namespace OfflineAgency\FilamentSpid;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;
use OfflineAgency\FilamentSpid\Pages\SpidLogin;

class SpidPlugin implements Plugin
{
    use EvaluatesClosures;

    protected string $loginRoute = 'spid.login';

    protected string $logoutRoute = 'spid.logout';

    protected string $acsRoute = 'spid.acs';

    protected string $metadataRoute = 'spid.metadata';

    protected string $providersRoute = 'spid.providers';

    protected bool $showSpidButton = true;

    protected ?string $spidButtonLabel = null;

    protected ?string $spidButtonIcon = null;

    protected string $loginView = 'filament-spid::login';

    protected array $providers = [];

    protected bool $registerRoutes = true;

    public function getId(): string
    {
        return 'spid';
    }

    public function register(Panel $panel): void
    {
        $panel->login(SpidLogin::class);
    }

    public function boot(Panel $panel): void
    {
        if ($this->registerRoutes) {
            \Route::middleware(['web'])
                ->prefix($panel->getPath())
                ->group(function () {
                    \Route::get('/spid/login', [SpidController::class, 'login'])->name($this->loginRoute);
                    \Route::get('/spid/providers', [SpidController::class, 'providers'])->name($this->providersRoute);
                    \Route::post('/spid/logout', [SpidController::class, 'logout'])->name($this->logoutRoute);
                    \Route::post('/spid/acs', [SpidController::class, 'acs'])->name($this->acsRoute);
                    \Route::get('/spid/metadata', [SpidController::class, 'metadata'])->name($this->metadataRoute);
                });
        }
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());
        return $plugin;
    }

    public function loginRoute(string $route): static
    {
        $this->loginRoute = $route;

        return $this;
    }

    public function logoutRoute(string $route): static
    {
        $this->logoutRoute = $route;

        return $this;
    }

    public function acsRoute(string $route): static
    {
        $this->acsRoute = $route;

        return $this;
    }

    public function metadataRoute(string $route): static
    {
        $this->metadataRoute = $route;

        return $this;
    }

    public function providersRoute(string $route): static
    {
        $this->providersRoute = $route;

        return $this;
    }

    public function showSpidButton(bool $show = true): static
    {
        $this->showSpidButton = $show;

        return $this;
    }

    public function spidButtonLabel(?string $label): static
    {
        $this->spidButtonLabel = $label;

        return $this;
    }

    public function spidButtonIcon(?string $icon): static
    {
        $this->spidButtonIcon = $icon;

        return $this;
    }

    public function loginView(string $view): static
    {
        $this->loginView = $view;

        return $this;
    }

    public function providers(array $providers): static
    {
        $this->providers = $providers;

        return $this;
    }

    public function registerRoutes(bool $register = true): static
    {
        $this->registerRoutes = $register;

        return $this;
    }

    public function getLoginRoute(): string
    {
        return $this->loginRoute;
    }

    public function getLogoutRoute(): string
    {
        return $this->logoutRoute;
    }

    public function getAcsRoute(): string
    {
        return $this->acsRoute;
    }

    public function getMetadataRoute(): string
    {
        return $this->metadataRoute;
    }

    public function getProvidersRoute(): string
    {
        return $this->providersRoute;
    }

    public function getShowSpidButton(): bool
    {
        return $this->showSpidButton;
    }

    public function getSpidButtonLabel(): string
    {
        return $this->spidButtonLabel ?? __('filament-spid::spid.login_with_spid');
    }

    public function getSpidButtonIcon(): ?string
    {
        return $this->spidButtonIcon;
    }

    public function getLoginView(): string
    {
        return $this->loginView;
    }

    public function getProviders(): array
    {
        return $this->providers;
    }
}
