<?php

use App\Models\User;
use OfflineAgency\FilamentSpid\Constants\SpidLevel;
use OfflineAgency\FilamentSpid\Mapping\EmailOrFallback;
use OfflineAgency\FilamentSpid\Mapping\FullName;
use OfflineAgency\FilamentSpid\Mapping\RandomPassword;

return [
    /*
    |--------------------------------------------------------------------------
    | Enable SPID Login
    |--------------------------------------------------------------------------
    |
    | Enable or disable SPID authentication. When disabled, the standard
    | Filament login page will be used instead of the SPID login page.
    |
    */
    'enabled' => env('FILAMENT_SPID_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Register Authentication Listeners
    |--------------------------------------------------------------------------
    |
    | The package listens to the LoginEvent and LogoutEvent fired by
    | italia/spid-laravel to provision the user, authenticate them on the panel
    | guard and tear the session down on logout. Disable this to take over the
    | flow with your own listeners.
    |
    */
    'register_listeners' => env('FILAMENT_SPID_REGISTER_LISTENERS', true),

    /*
    |--------------------------------------------------------------------------
    | SPID Panel
    |--------------------------------------------------------------------------
    |
    | Id of the panel SPID users are authenticated against. The SAML callback
    | runs on a library route, outside any panel, so multi-panel applications
    | should name the panel here. Null falls back to the default panel.
    |
    */
    'panel' => env('FILAMENT_SPID_PANEL'),

    /*
    |--------------------------------------------------------------------------
    | Minimum SPID Level
    |--------------------------------------------------------------------------
    |
    | Logins are refused unless spid-auth.sp_spid_level (the level the SP
    | requests, which italia/spid-laravel enforces on every assertion) is at
    | least this level. SpidL1 is password only: admin panels need SpidL2.
    |
    */
    'minimum_level' => env('FILAMENT_SPID_MINIMUM_LEVEL', SpidLevel::LEVEL_2->value),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model that will be used for SPID authentication.
    | Make sure this model has 'fiscal_code' and 'spid_data' fields.
    |
    | In production Filament only lets users in whose model implements
    | Filament\Models\Contracts\FilamentUser and returns true from
    | canAccessPanel(): that method is the authorisation gate for SPID logins.
    |
    */
    'user_model' => env('SPID_USER_MODEL', User::class),

    /*
    |--------------------------------------------------------------------------
    | Auto Create Users
    |--------------------------------------------------------------------------
    |
    | Create a user when no account matches the citizen's fiscal code. Off by
    | default: with it on, anyone holding a SPID identity gets an account, so
    | canAccessPanel() becomes the only thing between them and the panel.
    |
    */
    'auto_create_users' => env('SPID_AUTO_CREATE_USERS', false),

    /*
    |--------------------------------------------------------------------------
    | Update User Data
    |--------------------------------------------------------------------------
    |
    | Update user data on each login with SPID information.
    |
    */
    'update_user_data' => env('SPID_UPDATE_USER_DATA', true),

    /*
    |--------------------------------------------------------------------------
    | User Field Mapping
    |--------------------------------------------------------------------------
    |
    | Map SPID attributes to user model columns. A value is a SPID attribute
    | name (fiscalNumber, name, familyName, email, spidCode, placeOfBirth,
    | dateOfBirth, gender) or the class name of an invokable mapper receiving
    | the attributes array. Closures work too, but stop
    | `php artisan config:cache` (and `optimize`) from caching the config.
    |
    */
    'field_mapping' => [
        'name' => FullName::class,
        // The SPID email, or <fiscal code>@spid.invalid: unique, stable and
        // never deliverable. Use 'email' instead if your column is nullable.
        'email' => EmailOrFallback::class,
        'fiscal_code' => 'fiscalNumber',
        // A hash of a random password, set on creation only: the stock users
        // table declares password NOT NULL. Drop it if yours has no password.
        'password' => RandomPassword::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom User Creation
    |--------------------------------------------------------------------------
    |
    | Define a custom callback for user creation.
    | If null, the default user creation logic will be used.
    |
    */
    'create_user_callback' => null,

    /*
    |--------------------------------------------------------------------------
    | Custom User Update
    |--------------------------------------------------------------------------
    |
    | Define a custom callback for user update.
    | If null, the default user update logic will be used.
    |
    */
    'update_user_callback' => null,
];
