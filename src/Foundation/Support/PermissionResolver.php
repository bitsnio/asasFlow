<?php

namespace Bitsnio\AsasFlow\Foundation\Support;


use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PermissionResolver
{
    public function __construct(protected MenuService $menuService) {}
    protected array $methodMap = [
        'GET' => 'view',
        'POST' => 'create',
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete',
    ];


    public function resolve(
        string $uri,
        string $method
    ): ?string {
        $uri = $this->normalizeUri($uri);
        $method = strtoupper($method);

        if ($uri === '') {
            return null;
        }

        /*
     * Custom actions must be resolved before the CRUD method map.
     */
        $customPermission = $this->resolveCustomAction(
            $uri,
            $method
        );

        if ($customPermission !== null) {
            return $customPermission;
        }

        $action = $this->actionForMethod($method);

        if (!$action) {
            return null;
        }

        $parts = collect(explode('/', $uri))
            ->filter()
            ->map(function ($part) {
                if ($this->isParameter($part)) {
                    return null;
                }

                return Str::kebab($part);
            })
            ->filter()
            ->values()
            ->all();

        if (empty($parts)) {
            return null;
        }

        return implode('.', $parts) . '.' . $action;
    }

    protected function resolveCustomAction(
        string $uri,
        string $method
    ): ?string {
        foreach ($this->menuService->getDefinitions() as $definition) {
            $permission = $this->findCustomActionPermission(
                $definition,
                $uri,
                $method
            );

            if ($permission !== null) {
                return $permission;
            }
        }

        return null;
    }

    protected function findCustomActionPermission(
        MenuDefinition $definition,
        string $uri,
        string $method
    ): ?string {
        if ($definition->isResource()) {
            foreach ($definition->customActions() as $name => $config) {
                if (
                    $definition->customActionRoutePath($name) === $uri
                    && $definition->customActionMethod($name) === $method
                ) {
                    return $definition->customActionPermissionKey($name);
                }
            }
        }

        foreach ($definition->children as $child) {
            $permission = $this->findCustomActionPermission(
                $child,
                $uri,
                $method
            );

            if ($permission !== null) {
                return $permission;
            }
        }

        return null;
    }

    public function resolveRequest(
        Request $request
    ): ?string {
        return $this->resolve(
            $request->path(),
            $request->method()
        );
    }

    public function actionForMethod(
        string $method
    ): ?string {
        return $this->methodMap[strtoupper($method)] ?? null;
    }

    protected function normalizeUri(
        string $uri
    ): string {
        $uri = trim($uri, '/');

        if (Str::startsWith($uri, 'api/')) {
            $uri = substr($uri, 4);
        }

        return trim($uri, '/');
    }

    protected function isParameter(
        string $part
    ): bool {
        return preg_match(
            '/^\{[^}]+\}$/',
            $part
        ) === 1;
    }
}
