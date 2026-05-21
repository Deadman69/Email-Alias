<?php

return [
    /*
     * The path prefix for API routes to include in the generated spec.
     * Only routes starting with this prefix will be analyzed.
     */
    'api_path' => 'api',

    /*
     * Restrict the spec to a specific domain (null = any domain).
     */
    'api_domain' => null,

    /*
     * URL path where the OpenAPI spec JSON is exposed.
     * The Swagger UI is served at the same path without the .json extension.
     */
    'export_path' => 'docs/api',

    'info' => [
        'version' => env('APP_VERSION', '1.0.0'),
    ],

    /*
     * Override the servers list in the spec.
     * null = auto-detect from the current request.
     */
    'servers' => null,

    /*
     * Middleware applied to both the Swagger UI and the spec JSON endpoint.
     * Gate access behind auth + verified so only authenticated users can read the docs.
     */
    'middleware' => ['web', 'auth', 'verified'],

    /*
     * Scramble extensions (custom type inference, etc.).
     */
    'extensions' => [],
];
