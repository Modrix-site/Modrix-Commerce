<?php

use App\Providers\AppServiceProvider;
use App\Providers\CommerceServiceProvider;
use App\Providers\CoreServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\GeneralSettingsServiceProvider;
use App\Providers\ModuleServiceProvider;
use App\Providers\ThemeServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    CommerceServiceProvider::class,
    FortifyServiceProvider::class,
    GeneralSettingsServiceProvider::class,
    ThemeServiceProvider::class,
    ModuleServiceProvider::class,
];
