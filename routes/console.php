<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Adds the demo users and quotes the first time the site starts on an empty database.
Artisan::command('quoteit:demo-data', function () {
    if (User::query()->exists()) {
        $this->info('Demo data is already there.');

        return;
    }

    $this->call('db:seed', ['--force' => true]);
})->purpose('Add the demo data once, on an empty database');
