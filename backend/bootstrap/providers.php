<?php

use App\Providers\AppServiceProvider;
use Barryvdh\DomPDF\ServiceProvider as DomPDFServiceProvider;

return [
    AppServiceProvider::class,
    DomPDFServiceProvider::class,
];
