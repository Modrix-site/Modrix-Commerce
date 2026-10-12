<?php

use App\Providers\AppServiceProvider;
use App\Providers\CommerceServiceProvider;
use App\Providers\CoreServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ModuleServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    CommerceServiceProvider::class,
    FortifyServiceProvider::class,
    ModuleServiceProvider::class,
];
