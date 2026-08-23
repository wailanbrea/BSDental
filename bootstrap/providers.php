<?php

use App\Platform\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    TenancyServiceProvider::class,
    AppServiceProvider::class,
    HorizonServiceProvider::class,
];
