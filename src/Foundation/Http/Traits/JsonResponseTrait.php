<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Http\Traits;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Bitsnio\AsasFlow\Foundation\Http\ApiResponseFactory;
use Bitsnio\AsasFlow\Foundation\Http\Pagination\PaginationResolver;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilderContract;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

trait JsonResponseTrait
{
    protected function paginateQuery(
        EloquentBuilderContract|QueryBuilderContract|Relation $query,
        ?int $defaultPerPage = null,
        bool $allowUnpaginated = false,
        ?Request $request = null,
    ): LengthAwarePaginator|Collection {
        return app(PaginationResolver::class)->paginate(
            $query,
            $defaultPerPage,
            $allowUnpaginated,
            $request,
        );
    }

    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        array $headers = [],
        array $meta = [],
    ): JsonResponse {
        return app(ApiResponseFactory::class)->success(
            $data,
            $message,
            $status,
            $headers,
            $meta,
        );
    }

    protected function createdResponse(
        mixed $data = null,
        string $message = 'Created successfully',
        array $headers = [],
        array $meta = [],
    ): JsonResponse {
        return app(ApiResponseFactory::class)->created(
            $data,
            $message,
            $headers,
            $meta,
        );
    }

    protected function paginatedResponse(
        LengthAwarePaginator|Paginator|ResourceCollection|Collection|array $items,
        string $message = 'Success',
        int $status = 200,
        array $headers = [],
        array $meta = [],
    ): JsonResponse {
        return app(ApiResponseFactory::class)->paginated(
            $items,
            $message,
            $status,
            $headers,
            $meta,
        );
    }

    protected function cachedResponse(
        callable $callback,
        ?int $ttl = null,
        array $tags = [],
        ?Request $request = null,
    ): SymfonyResponse {
        $request ??= app()->bound('request') ? app('request') : Request::create('/');

        return app(CacheManager::class)->remember(
            $request,
            $callback,
            $ttl,
            $tags,
        );
    }

    protected function errorResponse(
        string $message = 'Error',
        int $status = 400,
        mixed $errors = null,
        array $headers = [],
    ): JsonResponse {
        return app(ApiResponseFactory::class)->error(
            $message,
            $status,
            $errors,
            $headers,
        );
    }

    protected function notFoundResponse(string $message = 'Resource not found.'): JsonResponse
    {
        return app(ApiResponseFactory::class)->notFound($message);
    }

    protected function validationErrorResponse(
        mixed $errors,
        string $message = 'Validation failed',
    ): JsonResponse {
        return app(ApiResponseFactory::class)->validationError($errors, $message);
    }

    protected function unauthorizedResponse(string $message = 'Unauthenticated.'): JsonResponse
    {
        return app(ApiResponseFactory::class)->unauthorized($message);
    }

    protected function forbiddenResponse(string $message = 'Forbidden.'): JsonResponse
    {
        return app(ApiResponseFactory::class)->forbidden($message);
    }

    protected function notImplementedResponse(string $message = 'Not implemented'): JsonResponse
    {
        return app(ApiResponseFactory::class)->notImplemented($message);
    }
}
