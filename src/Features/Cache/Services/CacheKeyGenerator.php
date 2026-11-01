<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Bitsnio\AsasFlow\Features\Cache\Contracts\CacheKeyStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CacheKeyGenerator implements CacheKeyStrategy
{
    public function generate(Request $request, array $context = []): string
    {
        $config = config('asasflow-cache.key_strategy', []);
        $parts = [];

        $parts[] = config('asasflow-cache.tagging.service_prefix', 'asasflow-service');
        $parts[] = strtolower($request->method());
        $parts[] = $request->path();

        $ignoreQueryParams = (bool) ($context['ignore_query_params'] ?? false);
        unset($context['ignore_query_params']);

        if (!$ignoreQueryParams && ($config['include_query_params'] ?? true)) {
            $query = $this->filterQueryParams($request->query());
            if (!empty($query)) {
                $normalizedQuery = $this->normalizeQueryArray($query);
                $parts[] = md5(json_encode($normalizedQuery) ?: serialize($normalizedQuery));
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
            $normalizedContext = $this->normalizeQueryArray($context);
            $parts[] = 'ctx:' . md5(json_encode($normalizedContext) ?: serialize($normalizedContext));
        }

        return implode('|', $parts);
    }

    protected function filterQueryParams(array $params): array
    {
        $ignore = config('asasflow-cache.key_strategy.ignore_params', []);

        return array_diff_key($params, array_flip(is_array($ignore) ? $ignore : []));
    }

    protected function normalizeQueryArray(array $values): array
    {
        ksort($values);

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->normalizeQueryArray($value);
            } elseif (is_bool($value)) {
                $values[$key] = $value ? '1' : '0';
            } elseif (is_scalar($value)) {
                $values[$key] = (string) $value;
            }
        }

        return $values;
    }

    public function generateModelTag(string $modelClass, ?string $modelId = null): string
    {
        $tag = 'model:' . str_replace('\\', '_', ltrim($modelClass, '\\'));

        return ($modelId !== null && $modelId !== '') ? "{$tag}:{$modelId}" : $tag;
    }

    public function generateRouteTag(string $routeName): string
    {
        return 'route:' . $routeName;
    }
}
