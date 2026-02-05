<?php 
namespace Bitsnio\Modules\Providers;

use Illuminate\Support\ServiceProvider;
use Bitsnio\Modules\Services\PermissionService;
use Bitsnio\Modules\Contracts\RepositoryInterface;
use Bitsnio\Modules\Services\MenuService;


class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Register MenuService
        $this->app->singleton(PermissionService::class, function ($app) {
            return new PermissionService($app[MenuService::class]);
        });

        // Register Alias
        $this->app->alias(PermissionService::class, 'modules.permission');

    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'modules.permission',
            PermissionService::class
        ];
    }

}