<?php

namespace App\Providers;

use App\Models\Announcement;
use Illuminate\Pagination\Paginator;
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
        Paginator::useBootstrapFive();

        // Bell badge in the top bar: announcements from the last 7 days.
        View::composer('partials.topbar', function ($view) {
            $user = auth()->user();

            $view->with('alertCount', $user
                ? Announcement::visibleTo($user)->where('published_at', '>=', now()->subDays(7))->count()
                : 0);
        });
    }
}
