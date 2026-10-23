<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('policyflow:about', function () {
    $this->info('PolicyFlow fictional insurance issuance engine');
});
