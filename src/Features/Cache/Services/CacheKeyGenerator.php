<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Bitsnio\AsasFlow\Features\Cache\Contracts\CacheKeyStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CacheKeyGenerator implements CacheKeyStrategy
{
    public function generate(Request $request, array $context = []): string
    {
        $config = config('asasflow-cache.key_strategy');
        $parts = [];

        $parts[] = config('asasflow-cache.tagging.service_prefix', 'asasflow-service');
        $parts[] = strtolower($request->method());
        $parts[] = $request->path();

        if ($config['include_query_params'] ?? true) {
            $query = $this->filterQueryParams($request->query());
            if (!empty($query)) {
                $parts[] = md5(serialize($query));
            }
        }

        foreach ($config['include_headers'] ?? [] as $header) {
            if ($request->hasHeader($header)) {
                $parts[] = strtolower($header) . ':' . $request->header($header);
            }
        }

        if (($config['include_user'] ?? true) && Auth::check()) {
            $parts[] = 'u:' . Auth::id();
        }

        if (!empty($context)) {
            $parts[] = 'ctx:' . md5(serialize($context));
        }

        return implode('|', $parts);
    }

    protected function filterQueryParams(array $params): array
    {
        $ignore = config('asasflow-cache.key_strategy.ignore_params', []);
        return array_diff_key($params, array_flip($ignore));
    }

    public function generateModelTag(string $modelClass, ?string $modelId = null): string
    {
        $tag = 'model:' . str_replace('\\', '_', $modelClass);
        return $modelId ? "{$tag}:{$modelId}" : $tag;
    }

    public function generateRouteTag(string $routeName): string
    {
        return 'route:' . $routeName;
    }
}
