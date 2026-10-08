<?php

use App\Models\User;
use OfflineAgency\FilamentSpid\Constants\SpidLevel;

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
    | Map SPID attributes to user model fields.
    |
    */
    'field_mapping' => [
        'name' => function ($spidUser) {
            return $spidUser['name'].' '.$spidUser['familyName'];
        },
        // SPID only sends an email when the IdP has one. The fallback is unique
        // per citizen and stable across logins, and .invalid (RFC 2606) never
        // resolves, so it neither receives mail nor collides with a real
        // address. Return null instead if your users table allows it.
        'email' => function ($spidUser) {
            return $spidUser['email'] ?? strtolower($spidUser['fiscalNumber']).'@spid.invalid';
        },
        'fiscal_code' => function ($spidUser) {
            return $spidUser['fiscalNumber'];
        },
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
