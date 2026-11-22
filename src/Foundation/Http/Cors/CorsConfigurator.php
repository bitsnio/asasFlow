<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Http\Cors;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as FoundationHttpKernel;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class CorsConfigurator
{
    public function __construct(
        protected Application $app,
        protected ModuleRepositoryInterface $modules,
    ) {}

    /**
     * Synchronize AsasFlow CORS configuration and module paths into Laravel's `config('cors')`
     * and configure unauthenticated browser API handling.
     */
    public function configure(): void
    {
        $this->configureUnauthenticatedApiHandling();

        if (! (bool) config('asasflow.cors.enabled', true)) {
            return;
        }

        $asasCors = (array) config('asasflow.cors', []);
        $existingCors = (array) config('cors', []);

        $mergedCors = [
            'paths' => $this->resolvePaths(),
            'allowed_methods' => $asasCors['allowed_methods'] ?? $existingCors['allowed_methods'] ?? ['*'],
            'allowed_origins' => $asasCors['allowed_origins'] ?? $existingCors['allowed_origins'] ?? ['*'],
            'allowed_origins_patterns' => array_values(array_unique([
                ...(array) ($existingCors['allowed_origins_patterns'] ?? []),
                ...(array) ($asasCors['allowed_origins_patterns'] ?? []),
            ])),
            'allowed_headers' => $asasCors['allowed_headers'] ?? $existingCors['allowed_headers'] ?? ['*'],
            'exposed_headers' => array_values(array_unique([
                ...(array) ($existingCors['exposed_headers'] ?? []),
                ...(array) ($asasCors['exposed_headers'] ?? ['X-AsasFlow-Cache', 'X-AsasFlow-Cache-Key', 'ETag']),
            ])),
            'max_age' => (int) ($asasCors['max_age'] ?? $existingCors['max_age'] ?? 0),
            'supports_credentials' => (bool) ($asasCors['supports_credentials'] ?? $existingCors['supports_credentials'] ?? false),
        ];

        config(['cors' => $mergedCors]);

        $this->ensureHandleCorsMiddlewareRegistered();
    }

    /**
     * Resolve all path patterns that should receive CORS headers and JSON exception rendering.
     *
     * @return array<int, string>
     */
    public function resolvePaths(): array
    {
        $existingPaths = (array) config('cors.paths', []);
        $configuredPaths = (array) config('asasflow.cors.paths', [
            'api/*',
            'sanctum/csrf-cookie',
            'schema',
            'schema/*',
            'dropdown/*',
            'settings',
            'settings/*',
            'asasflow/*',
        ]);

        $modulePaths = [];

        if ((bool) config('asasflow.cors.auto_discover_module_paths', true)) {
            foreach ($this->modules->all() as $module) {
                $name = $module->getName();
                $prefixes = array_unique([
                    Str::kebab($name),
                    Str::lower($name),
                    $this->resolveModuleAlias($module->getPath()),
                ]);

                foreach (array_filter($prefixes) as $prefix) {
                    $prefix = trim((string) $prefix, '/');
                    if ($prefix !== '') {
                        $modulePaths[] = $prefix;
                        $modulePaths[] = "{$prefix}/*";
                    }
                }
            }
        }

        $allPaths = array_merge($existingPaths, $configuredPaths, $modulePaths);

        $normalized = [];
        foreach ($allPaths as $path) {
            if (is_string($path) && trim($path) !== '') {
                $normalized[] = trim($path);
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Determine whether an incoming request targets an AsasFlow package or module API endpoint.
     */
    public function isAsasFlowApiRequest(Request $request): bool
    {
        foreach ($this->resolvePaths() as $pattern) {
            $trimmed = $pattern !== '/' ? trim($pattern, '/') : '/';

            if ($request->is($trimmed) || $request->fullUrlIs($trimmed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prevent `Route [login] not defined` redirects when a browser sends a wildcard Accept header
     * to an unauthenticated AsasFlow API or module route.
     */
    protected function configureUnauthenticatedApiHandling(): void
    {
        Authenticate::redirectUsing(function (Request $request): ?string {
            if ($this->isAsasFlowApiRequest($request)) {
                return null;
            }

            return Route::has('login') ? route('login') : null;
        });
    }

    /**
     * Ensure `Illuminate\Http\Middleware\HandleCors` is present on the HTTP Kernel.
     */
    protected function ensureHandleCorsMiddlewareRegistered(): void
    {
        if (! $this->app->bound(HttpKernelContract::class)) {
            return;
        }

        $kernel = $this->app->make(HttpKernelContract::class);

        if ($kernel instanceof FoundationHttpKernel && ! $kernel->hasMiddleware(HandleCors::class)) {
            $kernel->prependMiddleware(HandleCors::class);
        }
    }

    protected function resolveModuleAlias(string $modulePath): ?string
    {
        $manifestPath = rtrim($modulePath, '/\\') . '/module.json';
        if (! is_file($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($manifest) && is_string($manifest['alias'] ?? null)
            ? trim($manifest['alias'])
            : null;
    }
}
