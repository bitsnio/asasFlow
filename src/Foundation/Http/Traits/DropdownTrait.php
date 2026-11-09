<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Http\Traits;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Bitsnio\AsasFlow\Foundation\Http\ApiResponseFactory;
use Bitsnio\AsasFlow\Foundation\Http\Dropdown\DropdownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

trait DropdownTrait
{
    use JsonResponseTrait;

    /**
     * Resolve and return dropdown options as a unified AsasFlow JSON response.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     */
    public function getDropdownData(
        string $table,
        string $label = 'name',
        ?string $value = null,
        array $filters = [],
        ?int $limit = null,
        ?string $groupBy = null,
        ?string $search = null,
        ?string $orderBy = null,
        string $orderDir = 'asc',
        array $options = [],
    ): JsonResponse {
        $normalizedTable = strtolower(trim($table));
        $isVirtual = in_array($normalizedTable, ['modules', 'permissions', 'settings'], true);

        $executeResolution = function () use (
            $table,
            $label,
            $value,
            $filters,
            $limit,
            $groupBy,
            $search,
            $orderBy,
            $orderDir,
            $options,
        ): JsonResponse {
            try {
                $result = app(DropdownService::class)->resolve(
                    table: $table,
                    label: $label,
                    value: $value,
                    filters: $filters,
                    limit: $limit,
                    groupBy: $groupBy,
                    search: $search,
                    orderBy: $orderBy,
                    orderDir: $orderDir,
                    options: $options,
                );

                $status = (int) ($result['status'] ?? 200);
                if ($status >= 400) {
                    return $this->errorResponse(
                        message: (string) ($result['message'] ?? 'Error retrieving dropdown data.'),
                        status: $status,
                        errors: $result['errors'] ?? null,
                    );
                }

                $headers = [];
                if (!empty($result['cache_tag'])) {
                    $headers[ApiResponseFactory::MODEL_TAGS_HEADER] = (string) $result['cache_tag'];
                }

                return $this->successResponse(
                    data: $result['data'] ?? [],
                    message: (string) ($result['message'] ?? 'Dropdown data retrieved successfully.'),
                    status: 200,
                    headers: $headers,
                );
            } catch (Throwable $e) {
                return $this->errorResponse(
                    message: config('app.debug') ? $e->getMessage() : 'Failed to retrieve dropdown data.',
                    status: 500,
                );
            }
        };

        // Virtual sources (modules, permissions, settings) are already in-memory or cached by their own service
        if (
            !$isVirtual
            && config('asasflow.dropdown.cache.enabled', true)
            && config('asasflow-cache.enabled', true)
            && app()->bound(CacheManager::class)
            && app()->bound('request')
        ) {
            $request = app(Request::class);
            $aliases = (array) config('asasflow.dropdown.table_aliases', []);
            $resolvedTable = (string) ($aliases[$table] ?? $table);
            $ttl = (int) config('asasflow.dropdown.cache.ttl', 300);

            $response = app(CacheManager::class)->remember(
                request: $request,
                callback: $executeResolution,
                ttl: $ttl,
                tags: ['dropdown:' . $resolvedTable],
            );

            if ($response instanceof JsonResponse) {
                return $response;
            }

            $decoded = json_decode((string) $response->getContent(), true);

            return new JsonResponse(
                data: is_array($decoded) ? $decoded : $response->getContent(),
                status: $response->getStatusCode(),
                headers: $response->headers->all(),
                json: !is_array($decoded),
            );
        }

        $response = $executeResolution();
        $response->headers->remove(ApiResponseFactory::MODEL_TAGS_HEADER);

        return $response;
    }
}
