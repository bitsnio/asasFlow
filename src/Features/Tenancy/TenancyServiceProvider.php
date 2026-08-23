<?php

namespace AsasFlow\Features\Tenancy;

use AsasFlow\Features\Tenancy\Http\Middleware\InitializeTenancy;
use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tenant context is stateless, no singleton needed
    }

    public function boot(): void
    {
        // Register middleware
        $this->app['router']->aliasMiddleware('asasflow.tenancy', InitializeTenancy::class);

        // Auto-apply tenancy to module routes
        $this->autoApplyTenancy();
    }

    protected function autoApplyTenancy(): void
    {
        if (!config('asasflow.tenancy.enabled', false)) {
            return;
        }

        $router = $this->app['router'];

        // Apply to all module routes
        $router->matched(function ($route, $request) {
            $name = $route->getName() ?? '';
            
            if (str_starts_with($name, 'module.')) {
                $route->middleware('asasflow.tenancy');
            }
        });
    }
}
