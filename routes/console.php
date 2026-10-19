<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('portal:about', function () {
    $this->info('Employee Management & Work Tracking Portal');
})->purpose('Show portal information');
