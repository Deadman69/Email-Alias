<?php

return [
    // CURRENT VERSION VALUE IS AVAILABLE IN config('emailalias.version')

    /*
    |--------------------------------------------------------------------------
    | GitHub Repository
    |--------------------------------------------------------------------------
    | Format: owner/repository
    */

    'repository' => env('GITHUB_REPOSITORY', 'vendor/project'),

    /*
    |--------------------------------------------------------------------------
    | GitHub Token (optional)
    |--------------------------------------------------------------------------
    */

    'token' => env('GITHUB_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Cache duration in seconds
    |--------------------------------------------------------------------------
    */

    'cache_ttl' => env('GITHUB_CHECK_VERSION_TTL', 3600),

];