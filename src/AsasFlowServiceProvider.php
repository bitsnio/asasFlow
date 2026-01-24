<?php
namespace Bitsnio\AsasFlow;

use Illuminate\Support\ServiceProvider;

abstract class AsasFlowServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register config or singletons
    }

    public function boot()
    {
        // Load routes, views, or migrations
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'asasflow');
    }
}