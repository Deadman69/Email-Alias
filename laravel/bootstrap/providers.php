<?php

use App\Providers\AppServiceProvider;
use App\Providers\ForceHttpsServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    ForceHttpsServiceProvider::class,
    FortifyServiceProvider::class,
];
