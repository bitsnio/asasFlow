<?php 
namespace Bitsnio\Modules\Providers;

use Illuminate\Support\ServiceProvider;
use Bitsnio\Modules\Services\MenuService;
use Bitsnio\Modules\Contracts\RepositoryInterface;

class MenuServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Register MenuService
        $this->app->singleton(MenuService::class, function ($app) {
            return new MenuService($app[RepositoryInterface::class]);
        });

        // Register Alias
        $this->app->alias(MenuService::class, 'modules.menu');

    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'modules.menu',
            MenuService::class
        ];
    }

}