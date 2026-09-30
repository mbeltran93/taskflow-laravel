<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Single resources are returned as flat JSON objects (no "data" envelope).
        // Paginated collections still expose { data, links, meta } via the paginator.
        JsonResource::withoutWrapping();
    }
}
