<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vite Dev Server
    |--------------------------------------------------------------------------
    |
    | Controls how the PHP asset resolver switches between Vite 8 dev mode
    | (.vite-dev.json) and production builds (assets/.vite/manifest.json).
    |
    */

    'dev_server' => [

        /*
        |--------------------------------------------------------------------------
        | Dev Server Enabled
        |--------------------------------------------------------------------------
        |
        | When null, dev mode is enabled automatically when a valid
        | .vite-dev.json file exists for the active theme or plugin.
        | Set to false to force production manifest resolution.
        |
        */

        'enabled' => env('VITE_DEV_SERVER_ENABLED'),

        /*
        |--------------------------------------------------------------------------
        | Debug Logging
        |--------------------------------------------------------------------------
        |
        | When true and app.debug is enabled, the resolver writes details to
        | the application log via Log::debug(). Defaults to false; set
        | VITE_RESOLVER_DEBUG=true when debugging asset resolution.
        |
        */

        'debug_logging' => env('VITE_RESOLVER_DEBUG', false),

    ],

];
