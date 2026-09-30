<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PermissionResolver
{
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
        $action = $this->actionForMethod($method);

        if (!$action) {
            return null;
        }

        $uri = $this->normalizeUri($uri);

        if ($uri === '') {
            return null;
        }

        $parts = collect(
            explode('/', $uri)
        )
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

        return implode('.', $parts)
            . '.'
            . $action;
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
        return $this->methodMap[
            strtoupper($method)
        ] ?? null;
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