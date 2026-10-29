<?php

use App\Models\Quote;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps the demo accounts on the live site up to date. Real accounts are never touched:
// only accounts with an @quoteit.test email belong to the demo data.
Artisan::command('quoteit:demo-data', function () {
    if (! User::query()->exists()) {
        DB::transaction(fn () => $this->call('db:seed', ['--force' => true]));

        return;
    }

    if (! User::where('email', DatabaseSeeder::MARKER_EMAIL)->exists()) {
        $this->info('Replacing the old demo data...');
        // one transaction: if anything fails, the old demo data stays as it was
        DB::transaction(function () {
            $ids = User::where('email', 'like', '%@quoteit.test')->pluck('id');
            Quote::withoutGlobalScopes()->whereIn('user_id', $ids)->get()->each->delete();
            User::whereIn('id', $ids)->delete();
            $this->call('db:seed', ['--force' => true]);
        });

        return;
    }

    if (! Story::active()->whereHas('user', fn ($q) => $q->where('email', 'like', '%@quoteit.test'))->exists()) {
        DatabaseSeeder::seedStories();
        $this->info('Added fresh demo stories.');

        return;
    }

    $this->info('Demo data is up to date.');
})->purpose('Add or refresh the demo data (never touches real accounts)');
