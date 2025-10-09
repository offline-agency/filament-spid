<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model that will be used for SPID authentication.
    | Make sure this model has 'fiscal_code' and 'spid_data' fields.
    |
    */
    'user_model' => env('SPID_USER_MODEL', \App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Redirect After Login
    |--------------------------------------------------------------------------
    |
    | The URL to redirect to after successful SPID authentication.
    |
    */
    'redirect_after_login' => env('SPID_REDIRECT_AFTER_LOGIN', '/admin'),

    // UI-only: default SPID level suggested in the UI (actual level enforcement comes from spid-auth config)
    'spid_level' => env('SPID_LEVEL', 'https://www.spid.gov.it/SpidL2'),

    /*
    |--------------------------------------------------------------------------
    | Auto Create Users
    |--------------------------------------------------------------------------
    |
    | Automatically create a new user if the fiscal code doesn't exist.
    |
    */
    'auto_create_users' => env('SPID_AUTO_CREATE_USERS', true),

    /*
    |--------------------------------------------------------------------------
    | Update User Data
    |--------------------------------------------------------------------------
    |
    | Update user data on each login with SPID information.
    |
    */
    'update_user_data' => env('SPID_UPDATE_USER_DATA', true),

    // UI-only: optional allowlist for rendering provider logos; leave empty to defer entirely to spid-idps.php
    'providers' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Control caching for provider lists and other computed data.
    |
    */
    'cache' => [
        'providers_ttl' => env('FILAMENT_SPID_PROVIDERS_TTL', 3600),
    ],

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
        'email' => function ($spidUser) {
            return $spidUser['email'] ?? $spidUser['fiscalNumber'].'@spid.local';
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
