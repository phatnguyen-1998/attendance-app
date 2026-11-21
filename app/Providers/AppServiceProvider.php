<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        // 本番以外では、N+1（遅延読み込み）が起きたら例外にして気づけるようにする
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
