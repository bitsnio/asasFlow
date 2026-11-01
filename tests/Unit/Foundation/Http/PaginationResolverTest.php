<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Http\Pagination\PaginationResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('test_items');
    Schema::create('test_items', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    for ($i = 1; $i <= 35; $i++) {
        DB::table('test_items')->insert(['name' => "Item {$i}"]);
    }

    config([
        'asasflow.generators.controller.features.pagination.per_page' => 15,
        'asasflow.generators.controller.features.pagination.max_per_page' => 100,
        'asasflow.generators.controller.features.pagination.allow_unpaginated' => false,
        'asasflow.generators.controller.features.pagination.max_unpaginated' => 25,
    ]);
});

it('uses default per_page of 15 and clamps to max_per_page of 100', function () {
    $resolver = app(PaginationResolver::class);

    $defaultReq = Request::create('/api/items', 'GET');
    expect($resolver->resolvePerPage($defaultReq))->toBe(15);

    $customReq = Request::create('/api/items', 'GET', ['per_page' => 25]);
    expect($resolver->resolvePerPage($customReq))->toBe(25);

    $overMaxReq = Request::create('/api/items', 'GET', ['per_page' => 500]);
    expect($resolver->resolvePerPage($overMaxReq))->toBe(100);
});

it('normalizes invalid, negative, zero, and "all" per_page values to default', function (mixed $invalidPerPage) {
    $resolver = app(PaginationResolver::class);
    $request = Request::create('/api/items', 'GET', ['per_page' => $invalidPerPage]);

    expect($resolver->resolvePerPage($request, 20))->toBe(20);
})->with([
    'string all' => ['all'],
    'alphabetic' => ['abc'],
    'zero' => [0],
    'negative' => [-10],
    'float string' => ['12.5'],
    'array' => [['10']],
]);

it('normalizes negative, zero, or invalid page numbers to page 1', function (mixed $invalidPage) {
    $resolver = app(PaginationResolver::class);
    $request = Request::create('/api/items', 'GET', ['page' => $invalidPage]);

    expect($resolver->resolvePage($request))->toBe(1);
})->with([
    'negative page' => [-2],
    'zero page' => [0],
    'non-numeric' => ['first'],
    'array' => [[1]],
]);

it('disables ?paginate=false by default unless explicitly opted in and enforces max_unpaginated cap', function () {
    $resolver = app(PaginationResolver::class);
    $request = Request::create('/api/items', 'GET', ['paginate' => 'false']);

    // Default: allowUnpaginated is false -> still returns LengthAwarePaginator
    $paginated = $resolver->paginate(DB::table('test_items'), 15, false, $request);
    expect($paginated)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginated->perPage())->toBe(15)
        ->and($paginated->total())->toBe(35);

    // Explicit opt-in: allowUnpaginated is true -> returns Collection bounded by max_unpaginated (25)
    $unpaginated = $resolver->paginate(DB::table('test_items'), 15, true, $request);
    expect($unpaginated)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($unpaginated->count())->toBe(25);
});

it('extracts consistent metadata for empty results, out-of-range pages, simple paginators, and collections', function () {
    $resolver = app(PaginationResolver::class);

    // Empty paginator
    $emptyPaginator = new LengthAwarePaginator([], 0, 15, 1);
    expect($resolver->extractMeta($emptyPaginator))->toBe([
        'current_page' => 1,
        'per_page' => 15,
        'total' => 0,
        'last_page' => 1,
        'from' => null,
        'to' => null,
        'has_more_pages' => false,
    ]);

    // Page beyond last_page
    $outOfBoundsRequest = Request::create('/api/items', 'GET', ['page' => 99, 'per_page' => 15]);
    $outOfBoundsPaginator = $resolver->paginate(DB::table('test_items'), 15, false, $outOfBoundsRequest);
    expect($outOfBoundsPaginator->items())->toBeEmpty()
        ->and($resolver->extractMeta($outOfBoundsPaginator))->toBe([
            'current_page' => 99,
            'per_page' => 15,
            'total' => 35,
            'last_page' => 3,
            'from' => null,
            'to' => null,
            'has_more_pages' => false,
        ]);

    // Simple Paginator
    $simple = new Paginator([['id' => 1], ['id' => 2]], 2, 1);
    $simpleMeta = $resolver->extractMeta($simple);
    expect($simpleMeta['current_page'])->toBe(1)
        ->and($simpleMeta['per_page'])->toBe(2)
        ->and($simpleMeta['total'])->toBeNull();

    // Unpaginated collection
    $collectionMeta = $resolver->extractMeta(collect([['id' => 1], ['id' => 2], ['id' => 3]]));
    expect($collectionMeta)->toBe([
        'current_page' => 1,
        'per_page' => 3,
        'total' => 3,
        'last_page' => 1,
        'from' => 1,
        'to' => 3,
        'has_more_pages' => false,
    ]);
});
