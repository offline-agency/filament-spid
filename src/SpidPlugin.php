<?php

namespace OfflineAgency\FilamentSpid;

use Filament\Contracts\Plugin;
use Filament\Panel;
use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;
use OfflineAgency\FilamentSpid\Pages\SpidLogin;
use OfflineAgency\FilamentSpid\Support\Warning;

class SpidPlugin implements Plugin
{
    protected string $loginRoute = 'spid.login';

    protected string $logoutRoute = 'spid.logout';

    protected string $metadataRoute = 'spid.metadata';

    protected string $providersRoute = 'spid.providers';

    protected bool $showSpidButton = true;

    protected ?string $spidButtonLabel = null;

    protected ?string $spidButtonIcon = null;

    protected string $loginView = 'filament-spid::login';

    protected ?string $credentialsLoginUrl = null;

    /** @var list<string> */
    protected array $providers = [];

    /**
     * The shipped login view posts straight to italia/spid-laravel and the
     * package listens to its events, so these routes are opt-in extras.
     */
    protected bool $registerRoutes = false;

    public function getId(): string
    {
        return 'spid';
    }

    public function register(Panel $panel): void
    {
        // showSpidButton(false) opts the panel out of the SPID login page
        // entirely: replacing it with a page whose only button is hidden would
        // leave no way to sign in.
        if (config('filament-spid.enabled', true) && $this->showSpidButton) {
            $panel->login(SpidLogin::class);
        }
    }

    public function boot(Panel $panel): void
    {
        // Filament runs register() inside ->plugin(), so a ->login() chained
        // after it, as in the panel provider filament:install generates,
        // silently puts the default login page back. Routes are registered by
        // now, so all that can be done here is to say so.
        if (config('filament-spid.enabled', true) && $this->showSpidButton
            && $panel->getLoginRouteAction() !== SpidLogin::class) {
            Warning::once(sprintf(
                'filament-spid: panel [%s] does not use the SPID login page. Chain ->plugin(SpidPlugin::make()) after ->login(), or drop ->login().',
                $panel->getId(),
            ));
        }

        if ($this->registerRoutes && config('filament-spid.enabled', true)) {
            \Route::middleware(['web'])
                ->prefix($panel->getPath())
                ->group(function () {
                    \Route::get('/spid/login', [SpidController::class, 'login'])->name($this->loginRoute);
                    \Route::get('/spid/providers', [SpidController::class, 'providers'])->name($this->providersRoute);
                    \Route::post('/spid/logout', [SpidController::class, 'logout'])->name($this->logoutRoute);
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

    /**
     * The plugin registered on the current panel, or null.
     *
     * Unlike get(), this never throws: views and pages may be rendered on a
     * panel the plugin is not registered on, or with no panel at all.
     */
    public static function resolve(): ?static
    {
        try {
            $plugin = filament(app(static::class)->getId());
        } catch (\Throwable) {
            return null;
        }

        return $plugin instanceof static ? $plugin : null;
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

    /**
     * Link the SPID page to a login page for non-SPID credentials, such as a
     * second panel. Null (the default) renders no link: the panel's own login
     * URL is the SPID page itself.
     */
    public function credentialsLoginUrl(?string $url): static
    {
        $this->credentialsLoginUrl = $url;

        return $this;
    }

    /**
     * @param  list<string>  $providers  spid-idps keys allowed on the button; empty allows all
     */
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

    public function getCredentialsLoginUrl(): ?string
    {
        return $this->credentialsLoginUrl;
    }

    public function getLoginView(): string
    {
        return $this->loginView;
    }

    /**
     * @return list<string>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
