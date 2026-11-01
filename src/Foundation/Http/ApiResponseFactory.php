<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Http;

use Bitsnio\AsasFlow\Foundation\Http\Pagination\PaginationResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ApiResponseFactory
{
    public const MODEL_TAGS_HEADER = 'X-AsasFlow-Model-Tags';

    public function __construct(
        protected PaginationResolver $paginationResolver,
    ) {}

    public function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        array $headers = [],
        array $meta = [],
    ): JsonResponse {
        $modelTags = $this->extractModelTags($data);
        [$normalizedData, $resourceMeta] = $this->normalizeData($data);
        $mergedMeta = array_merge($resourceMeta, $meta);

        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $normalizedData,
        ];

        if ($mergedMeta !== []) {
            $payload['meta'] = $mergedMeta;
        }

        $response = response()->json($payload, $status, $headers);

        return $this->attachModelTags($response, $modelTags);
    }

    public function created(
        mixed $data = null,
        string $message = 'Created successfully',
        array $headers = [],
        array $meta = [],
    ): JsonResponse {
        return $this->success($data, $message, 201, $headers, $meta);
    }

    public function paginated(
        LengthAwarePaginator|Paginator|ResourceCollection|Collection|array $items,
        string $message = 'Success',
        int $status = 200,
        array $headers = [],
        array $meta = [],
    ): JsonResponse {
        $modelTags = $this->extractModelTags($items);
        [$data, $paginationMeta, $resourceMeta] = $this->extractPaginatedPayload($items);

        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge($paginationMeta, $resourceMeta, $meta),
        ];

        $response = response()->json($payload, $status, $headers);

        return $this->attachModelTags($response, $modelTags);
    }

    public function error(
        string $message = 'Error',
        int $status = 400,
        mixed $errors = null,
        array $headers = [],
    ): JsonResponse {
        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException(
                "Error response status code must be between 400 and 599, [{$status}] given."
            );
        }

        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, $headers);
    }

    public function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return $this->error($message, 404);
    }

    public function validationError(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    public function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return $this->error($message, 401);
    }

    public function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return $this->error($message, 403);
    }

    public function notImplemented(string $message = 'Not implemented'): JsonResponse
    {
        return $this->error($message, 501);
    }

    /**
     * Normalize single JsonResource, non-paginated ResourceCollection, or plain payload
     * without producing nested `data.data`.
     *
     * @return array{0: mixed, 1: array<string, mixed>}
     */
    protected function normalizeData(mixed $data): array
    {
        $request = $this->currentRequest();

        if ($data instanceof ResourceCollection) {
            $resolved = $this->resolveResourceItems($data, $request);
            $customMeta = $this->extractResourceMeta($data, $request);

            return [$resolved, $customMeta];
        }

        if ($data instanceof JsonResource) {
            $resolved = $data->resolve($request);
            $customMeta = $this->extractResourceMeta($data, $request);

            return [$resolved, $customMeta];
        }

        if ($data instanceof Collection) {
            return [array_values($data->map(fn ($item) => $this->normalizeScalarOrObject($item, $request))->all()), []];
        }

        if ($data instanceof Model) {
            return [$data->toArray(), []];
        }

        return [$data, []];
    }

    /**
     * Extract items array, pagination metadata, and any custom resource metadata.
     *
     * @return array{0: array<int, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    protected function extractPaginatedPayload(
        LengthAwarePaginator|Paginator|ResourceCollection|Collection|array $items,
    ): array {
        $request = $this->currentRequest();

        if ($items instanceof ResourceCollection) {
            $underlying = $items->resource;
            $resolvedItems = $this->resolveResourceItems($items, $request);
            $customMeta = $this->extractResourceMeta($items, $request);

            if ($underlying instanceof LengthAwarePaginator || $underlying instanceof Paginator) {
                return [
                    $resolvedItems,
                    $this->paginationResolver->extractMeta($underlying),
                    $customMeta,
                ];
            }

            return [
                $resolvedItems,
                $this->paginationResolver->extractMeta($resolvedItems),
                $customMeta,
            ];
        }

        if ($items instanceof LengthAwarePaginator || $items instanceof Paginator) {
            $normalizedItems = array_map(
                fn ($item) => $this->normalizeScalarOrObject($item, $request),
                array_values($items->items()),
            );

            return [
                $normalizedItems,
                $this->paginationResolver->extractMeta($items),
                [],
            ];
        }

        $rawItems = $items instanceof Collection ? array_values($items->all()) : array_values($items);
        $normalizedItems = array_map(
            fn ($item) => $this->normalizeScalarOrObject($item, $request),
            $rawItems,
        );

        return [
            $normalizedItems,
            $this->paginationResolver->extractMeta($normalizedItems),
            [],
        ];
    }

    protected function resolveResourceItems(ResourceCollection $collection, Request $request): array
    {
        $resolved = $collection->resolve($request);

        return array_values(array_map(
            fn ($item) => $this->normalizeScalarOrObject($item, $request),
            $resolved,
        ));
    }

    protected function normalizeScalarOrObject(mixed $item, Request $request): mixed
    {
        if ($item instanceof JsonResource) {
            return $item->resolve($request);
        }

        if ($item instanceof Model) {
            return $item->toArray();
        }

        return $item;
    }

    protected function extractResourceMeta(JsonResource $resource, Request $request): array
    {
        $with = $resource->with($request);
        $additional = $resource->additional;

        $combined = array_merge(
            is_array($with) ? $with : [],
            is_array($additional) ? $additional : [],
        );

        if (isset($combined['meta']) && is_array($combined['meta'])) {
            $nestedMeta = $combined['meta'];
            unset($combined['meta']);
            $combined = array_merge($nestedMeta, $combined);
        }

        return $combined;
    }

    /**
     * Extract model cache tags from models, resources, collections, or paginators
     * so AutoCacheMiddleware / CacheManager can auto-tag responses accurately.
     *
     * @return array<int, string>
     */
    public function extractModelTags(mixed $data): array
    {
        if (!config('asasflow-cache.tagging.enabled', true) || !config('asasflow-cache.tagging.auto_tag_models', true)) {
            return [];
        }

        $tags = [];
        $this->collectTagsFromValue($data, $tags);

        return array_values(array_unique(array_filter($tags)));
    }

    protected function collectTagsFromValue(mixed $value, array &$tags): void
    {
        if ($value instanceof Model) {
            $class = get_class($value);
            $normalizedClass = str_replace('\\', '_', $class);
            $tags[] = "model:{$normalizedClass}";

            $key = $value->getKey();
            if ($key !== null && $key !== '') {
                $tags[] = "model:{$normalizedClass}:{$key}";
            }

            if (method_exists($value, 'getCacheTags')) {
                foreach ((array) $value->getCacheTags() as $customTag) {
                    if (is_string($customTag) && $customTag !== '') {
                        $tags[] = $customTag;
                    }
                }
            }

            return;
        }

        if ($value instanceof ResourceCollection) {
            $this->collectTagsFromValue($value->resource, $tags);
            if ($value->collection instanceof Collection) {
                foreach ($value->collection as $item) {
                    $this->collectTagsFromValue($item, $tags);
                }
            }

            return;
        }

        if ($value instanceof JsonResource) {
            $this->collectTagsFromValue($value->resource, $tags);

            return;
        }

        if ($value instanceof LengthAwarePaginator || $value instanceof Paginator) {
            foreach ($value->items() as $item) {
                $this->collectTagsFromValue($item, $tags);
            }

            return;
        }

        if ($value instanceof Collection || is_array($value)) {
            foreach ($value as $item) {
                $this->collectTagsFromValue($item, $tags);
            }
        }
    }

    protected function attachModelTags(JsonResponse $response, array $tags): JsonResponse
    {
        if ($tags !== []) {
            $response->headers->set(self::MODEL_TAGS_HEADER, implode(',', $tags));
        }

        return $response;
    }

    protected function currentRequest(): Request
    {
        return app()->bound('request') ? app('request') : Request::create('/');
    }
}
