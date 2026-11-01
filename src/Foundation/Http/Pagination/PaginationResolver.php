<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Http\Pagination;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilderContract;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PaginationResolver
{
    public function paginate(
        EloquentBuilderContract|QueryBuilderContract|Relation $query,
        ?int $defaultPerPage = null,
        bool $allowUnpaginated = false,
        ?Request $request = null,
    ): LengthAwarePaginator|Collection {
        $request ??= $this->currentRequest();

        if ($this->shouldReturnUnpaginated($request, $allowUnpaginated)) {
            $maxUnpaginated = $this->maxUnpaginatedLimit();

            return $query->limit($maxUnpaginated)->get();
        }

        $perPage = $this->resolvePerPage($request, $defaultPerPage);
        $page = $this->resolvePage($request);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function resolvePerPage(?Request $request = null, ?int $defaultPerPage = null): int
    {
        $request ??= $this->currentRequest();
        $default = $this->normalizePositiveInt($defaultPerPage, $this->defaultPerPage());
        $max = $this->maxPerPage();

        $rawPerPage = $request->query('per_page');

        if ($rawPerPage === null || $rawPerPage === '') {
            return min($default, $max);
        }

        if (is_bool($rawPerPage) || is_array($rawPerPage)) {
            return min($default, $max);
        }

        $validated = filter_var($rawPerPage, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($validated === false) {
            return min($default, $max);
        }

        return min((int) $validated, $max);
    }

    public function resolvePage(?Request $request = null): int
    {
        $request ??= $this->currentRequest();
        $rawPage = $request->query('page');

        if ($rawPage === null || is_bool($rawPage) || is_array($rawPage)) {
            return 1;
        }

        $validated = filter_var($rawPage, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($validated === false) {
            return 1;
        }

        return (int) $validated;
    }

    public function shouldReturnUnpaginated(?Request $request = null, bool $allowUnpaginated = false): bool
    {
        $request ??= $this->currentRequest();
        $configAllowed = (bool) config(
            'asasflow.generators.controller.features.pagination.allow_unpaginated',
            false,
        );

        if (!$allowUnpaginated && !$configAllowed) {
            return false;
        }

        $paginateParam = $request->query('paginate');

        if ($paginateParam === null || is_array($paginateParam)) {
            return false;
        }

        return filter_var($paginateParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false;
    }

    public function extractMeta(LengthAwarePaginator|Paginator|Collection|array $items): array
    {
        if ($items instanceof LengthAwarePaginator) {
            return [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'has_more_pages' => $items->hasMorePages(),
            ];
        }

        if ($items instanceof Paginator) {
            return [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => null,
                'last_page' => null,
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'has_more_pages' => $items->hasMorePages(),
            ];
        }

        $count = $items instanceof Collection ? $items->count() : count($items);

        return [
            'current_page' => 1,
            'per_page' => $count,
            'total' => $count,
            'last_page' => 1,
            'from' => $count > 0 ? 1 : null,
            'to' => $count > 0 ? $count : null,
            'has_more_pages' => false,
        ];
    }

    protected function defaultPerPage(): int
    {
        return $this->normalizePositiveInt(
            config('asasflow.generators.controller.features.pagination.per_page', 15),
            15,
        );
    }

    protected function maxPerPage(): int
    {
        return $this->normalizePositiveInt(
            config('asasflow.generators.controller.features.pagination.max_per_page', 100),
            100,
        );
    }

    protected function maxUnpaginatedLimit(): int
    {
        return $this->normalizePositiveInt(
            config('asasflow.generators.controller.features.pagination.max_unpaginated', 500),
            500,
        );
    }

    protected function normalizePositiveInt(mixed $value, int $fallback): int
    {
        if ($value === null || is_bool($value) || is_array($value)) {
            return $fallback;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $validated !== false ? (int) $validated : $fallback;
    }

    protected function currentRequest(): Request
    {
        return app()->bound('request') ? app('request') : Request::create('/');
    }
}
