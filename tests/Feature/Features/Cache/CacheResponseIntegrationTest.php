<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Cache\Attributes\AutoCache;
use Bitsnio\AsasFlow\Features\Cache\Attributes\NoCache;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Bitsnio\AsasFlow\Features\Cache\Traits\CacheAware;
use Bitsnio\AsasFlow\Foundation\Http\Traits\JsonResponseTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class CachedProductModel extends Model
{
    use CacheAware;

    protected $table = 'cached_products';
    protected $guarded = [];

    protected array $cacheTags = [
        'catalog-products',
    ];
}

class CachedProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (int) $this->price,
        ];
    }
}

class CachedProductController extends Controller
{
    use JsonResponseTrait;

    public static int $indexExecutions = 0;
    public static int $liveExecutions = 0;

    #[AutoCache(ttl: 300, tags: 'product-list')]
    public function index(): JsonResponse
    {
        self::$indexExecutions++;
        $items = $this->paginateQuery(CachedProductModel::query(), 10);

        return $this->paginatedResponse(
            CachedProductResource::collection($items),
            'Products retrieved',
        );
    }

    #[NoCache(reason: 'Real-time stock count')]
    public function liveCount(): JsonResponse
    {
        self::$liveExecutions++;

        return $this->successResponse(['count' => CachedProductModel::count()]);
    }

    public function failingEndpoint(): JsonResponse
    {
        self::$liveExecutions++;

        return $this->errorResponse('Temporary failure', 503);
    }
}

beforeEach(function () {
    CachedProductController::$indexExecutions = 0;
    CachedProductController::$liveExecutions = 0;

    Cache::store('array')->flush();

    Schema::dropIfExists('cached_products');
    Schema::create('cached_products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->integer('price');
        $table->timestamps();
    });

    CachedProductModel::create(['name' => 'Keyboard', 'price' => 100]);
    CachedProductModel::create(['name' => 'Monitor', 'price' => 300]);

    Route::middleware(['asasflow.cache'])->group(function () {
        Route::get('/api/cached-products', [CachedProductController::class, 'index'])
            ->name('cached-products.index');
        Route::get('/api/cached-products/live', [CachedProductController::class, 'liveCount'])
            ->name('cached-products.live');
        Route::get('/api/cached-products/fail', [CachedProductController::class, 'failingEndpoint'])
            ->name('cached-products.fail');
    });
});

it('caches unified paginated responses, normalizes query param order, and supports ETag 304 Not Modified', function () {
    // 1. First request -> cache MISS
    $first = $this->getJson('/api/cached-products?page=1&per_page=10&utm_source=newsletter')
        ->assertOk()
        ->assertHeader('X-AsasFlow-Cache', 'MISS')
        ->assertJson([
            'success' => true,
            'message' => 'Products retrieved',
            'data' => [
                ['id' => 1, 'name' => 'Keyboard', 'price' => 100],
                ['id' => 2, 'name' => 'Monitor', 'price' => 300],
            ],
            'meta' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 2,
            ],
        ]);

    expect(CachedProductController::$indexExecutions)->toBe(1);
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeEmpty();

    // 2. Second request with reversed query params & different ignored tracking param -> cache HIT
    $this->getJson('/api/cached-products?per_page=10&page=1&utm_source=google')
        ->assertOk()
        ->assertHeader('X-AsasFlow-Cache', 'HIT')
        ->assertHeader('ETag', $etag)
        ->assertExactJson($first->json());

    expect(CachedProductController::$indexExecutions)->toBe(1);

    // 3. Request with matching If-None-Match header -> 304 Not Modified
    $this->withHeader('If-None-Match', $etag)
        ->getJson('/api/cached-products?page=1&per_page=10')
        ->assertStatus(304);

    expect(CachedProductController::$indexExecutions)->toBe(1);
});

it('automatically invalidates cached paginated responses when an observed Eloquent model changes', function () {
    // Prime the cache
    $this->getJson('/api/cached-products')
        ->assertOk()
        ->assertHeader('X-AsasFlow-Cache', 'MISS')
        ->assertJsonPath('meta.total', 2);

    expect(CachedProductController::$indexExecutions)->toBe(1);

    // Create a new product -> ModelCacheObserver invalidates model & custom tags
    CachedProductModel::create(['name' => 'Mouse', 'price' => 50]);

    // Next request must be a MISS and reflect the 3 products
    $this->getJson('/api/cached-products')
        ->assertOk()
        ->assertHeader('X-AsasFlow-Cache', 'MISS')
        ->assertJsonPath('meta.total', 3);

    expect(CachedProductController::$indexExecutions)->toBe(2);
});

it('respects #[NoCache] attribute and never caches error responses', function () {
    // #[NoCache] endpoint executes every time
    $this->getJson('/api/cached-products/live')->assertOk();
    $this->getJson('/api/cached-products/live')->assertOk();
    expect(CachedProductController::$liveExecutions)->toBe(2);

    // Error endpoint (503 / success: false) is never cached
    $this->getJson('/api/cached-products/fail')->assertStatus(503);
    $this->getJson('/api/cached-products/fail')->assertStatus(503);
    expect(CachedProductController::$liveExecutions)->toBe(4);
});

it('records cache hits, misses, and entry metadata in CacheManager stats', function () {
    $cacheManager = app(CacheManager::class);

    $this->getJson('/api/cached-products')->assertOk();
    $this->getJson('/api/cached-products')->assertOk();

    $stats = $cacheManager->getStats();
    $entries = $cacheManager->getEntries();

    expect($stats['hits'])->toBeGreaterThanOrEqual(1)
        ->and($stats['misses'])->toBeGreaterThanOrEqual(1)
        ->and($stats['entries_count'])->toBeGreaterThanOrEqual(1)
        ->and($entries)->not->toBeEmpty();
});
