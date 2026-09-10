<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),
    'path' => 'api/v1/oauth',

    /*
    |--------------------------------------------------------------------------
    | Auto Password-Grant Client Secret
    |--------------------------------------------------------------------------
    |
    | The secret shared by the single password-grant client that the SPA uses
    | (GET /api/v1/oauth/client). Passport stores client secrets hashed, so the
    | plaintext secret must live in config to be returned again on later calls.
    | The client is created once and reused across every tab/session — recreating
    | it would invalidate refresh tokens held by other tabs (B50).
    |
    */

    'auto_client_secret' => env('PASSPORT_AUTO_CLIENT_SECRET', 'local-dev-auto-client-secret'),

];
