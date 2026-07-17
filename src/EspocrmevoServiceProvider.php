<?php

namespace roilafx\Espocrmevo;

use EvolutionCMS\ServiceProvider;
use Illuminate\Support\Facades\Route;
use roilafx\Espocrmevo\Commands\CreateClient;

class EspocrmevoServiceProvider extends ServiceProvider
{
    protected $namespace = 'espocrmevo';
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            CreateClient::class,
        ]);
    }
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
        $this->loadViewsFrom(__DIR__ . '/../views', 'espocrmevo');
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
