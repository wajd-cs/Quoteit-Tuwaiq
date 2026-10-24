<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Goal;
use App\Models\Mood;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // after publishing, every link (share links too) uses the public address from APP_URL
        if ($this->app->isProduction() && config('app.url')) {
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with(config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // categories are used in a lot of views (chips, share form, interests)
        View::composer('*', function ($view) {
            static $categories = null;
            $categories ??= Schema::hasTable('categories')
                ? Category::orderBy('name')->get()
                : collect();
            $view->with('categories', $categories);
        });

        // moods and goals for the "how can this quote help" part of the quote form
        View::composer('components.quote-form', function ($view) {
            $view->with([
                'formMoods' => Mood::orderBy('sort')->get(),
                'formGoals' => Goal::orderBy('sort')->get(),
            ]);
        });
    }
}
