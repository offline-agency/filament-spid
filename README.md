# Filament SPID

[![Latest Version on Packagist](https://img.shields.io/packagist/v/offline-agency/filament-spid.svg?style=flat-square)](https://packagist.org/packages/offline-agency/filament-spid)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/offline-agency/filament-spid/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/offline-agency/filament-spid/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/offline-agency/filament-spid/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/offline-agency/filament-spid/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/offline-agency/filament-spid.svg?style=flat-square)](https://packagist.org/packages/offline-agency/filament-spid)

SPID (Sistema Pubblico di Identità Digitale) authentication plugin for Filament based on [italia/spid-laravel](https://github.com/italia/spid-laravel).

This package allows you to integrate SPID authentication into your Filament admin panels, enabling Italian public administration identity management.

![Filament SPID Banner](https://banners.beyondco.de/Filament%20Spid.png?theme=dark&packageManager=composer+require&packageName=offline-agency%2Ffilament-spid&pattern=eyes&style=style_1&description=Filament+plugin+for+SPID+authentication+in+Laravel.&md=1&showWatermark=0&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)

## Features

- 🇮🇹 Full SPID integration for Filament
- 🔐 Secure SAML2 authentication
- 🎨 Customizable login view
- 🔧 Compatible with Filament 3 & 4
- 📦 Support for Laravel 10, 11, 12
- ⚡ PHP 8.2+ ready
- 🧪 Fully tested

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x
- Filament 3.x or 4.x
- [italia/spid-laravel](https://github.com/italia/spid-laravel) package

## Installation

You can install the package via composer:

```bash
composer require offline-agency/filament-spid
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="filament-spid-config"
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="filament-spid-migrations"
php artisan migrate
```

Optionally, you can publish the views:

```bash
php artisan vendor:publish --tag="filament-spid-views"
```

### Publishing Images

The login page renders the SPID AGID logo from your own public directory, so
publish it once:

```bash
php artisan vendor:publish --tag="filament-spid-images"
```

This copies the logo to `public/vendor/filament-spid/images/`, making it
available at `/vendor/filament-spid/images/spid-agid-logo.png`.

## SPID Configuration

First, configure the base SPID Laravel package. Follow the [italia/spid-laravel documentation](https://github.com/italia/spid-laravel) to:

1. Generate SPID certificates
2. Configure your Service Provider metadata
3. Set up SPID Identity Providers

Add the following fields to your users table migration (if not already published):

```php
$table->string('fiscal_code')->unique()->nullable();
$table->json('spid_data')->nullable();
```

## Usage

Register the plugin in your Filament Panel Provider:

```php
use OfflineAgency\FilamentSpid\SpidPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->plugin(
            SpidPlugin::make()
                ->spidButtonLabel('Entra con SPID')
                ->providers([
                    'arubaid',
                    'infocertid',
                    'lepidaid',
                    'namirialid',
                    'posteid',
                    'sielteid',
                    'spiditalia',
                    'timid',
                    'teamsystemid'
                ])
        );
}
```

### CSRF

The Identity Provider posts the SAML assertion straight to the ACS endpoint and
cannot carry a Laravel CSRF token, so that one route must be excluded. In
`bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['spid/acs']);
})
```

Keep the exclusion minimal: only the ACS path (matching
`spid-auth.routes_prefix`). Never exclude the logout route, which is posted by
your own forms and does carry a token.

### Authenticating users

Nothing else is required: the package listens to the `LoginEvent` and
`LogoutEvent` fired by `italia/spid-laravel` once the SAML response is
validated, and takes care of

- building a `SpidUserData` from the SPID attributes,
- creating or updating the user through `SpidUserService`,
- authenticating them on the panel guard (no remember-me cookie) and
  regenerating the session,
- tearing the session down on SPID logout.

A failed provisioning sends the citizen back to the panel login page with a
translated `spid_error` flash message instead of surfacing a 500.

Customize the behaviour through `config/filament-spid.php`:

| Key | Purpose |
| --- | --- |
| `register_listeners` | Set to `false` to replace the listeners with your own |
| `user_model` | Model to provision |
| `field_mapping` | SPID attribute → user column mapping |
| `create_user_callback` / `update_user_callback` | Take over provisioning entirely |
| `auto_create_users` / `update_user_data` | Whether to create or refresh accounts |

The package also emits its own events — `SpidUserCreated`, `SpidUserUpdated`,
`SpidAuthenticationSucceeded` and `SpidAuthenticationFailed` — to hook into
without replacing the flow.

### Customization

#### Custom Login View

```php
SpidPlugin::make()
    ->loginView('your-custom-view')
```

#### Optional Helper Routes

The SAML endpoints belong to `italia/spid-laravel`. The plugin can additionally
expose a few convenience routes (a login redirect, a providers JSON endpoint, a
metadata proxy and a logout); they are **off by default**:

```php
SpidPlugin::make()
    ->registerRoutes(true)
    ->loginRoute('custom.spid.login')
    ->logoutRoute('custom.spid.logout')
    ->metadataRoute('custom.spid.metadata')
```

#### Opt Out of the SPID Login Page

```php
SpidPlugin::make()
    ->showSpidButton(false)
```

The panel keeps its own login page: a SPID page with the button hidden would
leave no way to sign in.

#### Custom Button Label and Icon

```php
SpidPlugin::make()
    ->spidButtonLabel('Login con SPID')
    ->spidButtonIcon('heroicon-o-shield-check')
```

#### Select Specific Providers

Acts as an allowlist over the keys of `config/spid-idps.php`; an empty array (the
default) shows every active provider. Inactive providers are never shown, even
when listed here.

```php
SpidPlugin::make()
    ->providers(['posteid', 'infocertid', 'timid'])
```

## Configuration

The `config/filament-spid.php` configuration file allows you to customize:

```php
return [
    'user_model' => \App\Models\User::class,
    'redirect_after_login' => '/admin',
    'spid_level' => 'https://www.spid.gov.it/SpidL2',
];
```

## User Model

The model is resolved from `filament-spid.user_model`. For backward compatibility
`spid-auth.user_model` is used when that key is not set.

Your User model should have these fields:

```php
protected $fillable = [
    'name',
    'email',
    'fiscal_code',
    'spid_data',
    // ... other fields
];

protected $casts = [
    'spid_data' => 'array',
];
```

The `spid_data` cast is optional: the package stores the SPID payload as a raw
array when the model casts the column, and as a JSON string when it does not, so
the value is never double-encoded either way.

## SPID Levels

SPID supports three security levels:

- `SpidL1` - Level 1 (Username and password)
- `SpidL2` - Level 2 (Username, password, and OTP) - **Default**
- `SpidL3` - Level 3 (Smart card or hardware token)

Configure the level in your config file or when calling the login:

```php
route('spid.login', ['provider' => 'posteid', 'level' => 'SpidL2'])
```

## Testing

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

## Code Style

```bash
composer format
```

## Static Analysis

```bash
composer analyse
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Offline Agency](https://offlineagency.it)
- [All Contributors](../../contributors)
- Based on [italia/spid-laravel](https://github.com/italia/spid-laravel)

## Support

For support and questions:
- Email: support@offlineagency.it
- GitHub Issues: [Create an issue](https://github.com/offline-agency/filament-spid/issues)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## About Offline Agency

Offline Agency is a web development agency based in Italy, specializing in Laravel and Filament applications.

- Website: [https://offlineagency.it](https://offlineagency.it)
- GitHub: [@offline-agency](https://github.com/offline-agency)
- Email: support@offlineagency.it
