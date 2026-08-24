<?php

namespace Bitsnio\AsasFlow\Features\Tenancy\Http\Middleware;

use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancy
{
    /**
     * Handle request with tenant initialization.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for central routes
        if ($this->isCentralRoute($request)) {
            return $next($request);
        }

        // Skip if tenancy disabled
        if (!config('asasflow.tenancy.enabled', false)) {
            return $next($request);
        }

        $tenant = TenantContext::initialize();

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found or inactive',
                'domain' => $request->getHost(),
            ], 404);
        }

        // Attach tenant to request
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);

        return $next($request);
    }

    /**
     * Check if route is central (landlord).
     */
    protected function isCentralRoute(Request $request): bool
    {
        $centralRoutes = config('asasflow.tenancy.central_routes', [
            'api/central/*',
            'admin/*',
            'health',
            'tenants/*',
            'asasflow/*',
        ]);

        $routeName = $request->route()?->getName() ?? '';
        
        foreach ($centralRoutes as $pattern) {
            if ($request->is($pattern) || str_starts_with($routeName, str_replace('/*', '', $pattern))) {
                return true;
            }
        }

        // Check central domains
        $centralDomains = config('asasflow.tenancy.central_domains', []);
        if (in_array($request->getHost(), $centralDomains)) {
            // Only central routes allowed on central domains
            return !$request->is('api/module/*');
        }

        return false;
    }
}
