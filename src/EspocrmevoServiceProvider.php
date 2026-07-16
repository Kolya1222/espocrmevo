<?php

namespace roilafx\Espocrmevo;

use EvolutionCMS\ServiceProvider;
use Illuminate\Support\Facades\Route;

class EspocrmevoServiceProvider extends ServiceProvider
{
    protected $namespace = 'espocrmevo';
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {}
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
        $this->loadViewsFrom(__DIR__ . '/../views', 'espocrmevo');
        $this->publishes([
            __DIR__ . '/../publishable/'  => MODX_BASE_PATH . 'manager/media/',
        ]);
        $this->app->registerRoutingModule(
            'Интеграция с CRM',
            __DIR__ . '/../routes.php',
            'fa fa-plug'
        );

        Route::group(['middleware' => 'bindings'], function () {
            $this->loadRoutesFrom(
                __DIR__ . '/../routes_oidc.php'
            );
        });
    }
}
