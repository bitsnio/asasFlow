 Here is the complete documentation for both features. You can copy-paste each into separate `.md` files.

---

# ASASFLOW Cache Feature — Complete Documentation

## Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Installation & Setup](#installation--setup)
4. [Configuration](#configuration)
5. [Core Concepts](#core-concepts)
6. [Usage Patterns](#usage-patterns)
7. [Route Caching](#route-caching)
8. [Manual Cache Operations](#manual-cache-operations)
9. [Model Observer Integration](#model-observer-integration)
10. [CLI Commands](#cli-commands)
11. [Facade Reference](#facade-reference)
12. [Advanced Patterns](#advanced-patterns)
13. [Troubleshooting](#troubleshooting)

---

## Overview

The ASASFLOW Cache Feature provides a **tag-based cache invalidation system** designed specifically for modular Laravel applications. It solves the classic cache invalidation problem by:

- **Tagging cache entries** with module identifiers
- **Auto-invalidating** cache when models change via observers
- **Supporting both Redis (tag-aware)** and non-tag drivers (file/database) via a fallback registry
- **Integrating seamlessly** with your existing CLI-generated module structure

### Why Not Use Laravel's Built-in Cache?

| Problem | Laravel Default | ASASFLOW Cache |
|---------|--------------|----------------|
| Invalidate all "user" cache | Must track keys manually | `invalidateTags(['module:users'])` |
| Model change → cache clear | Manual implementation | Auto via generated observers |
| Multi-driver support | Tags only on Redis/Memcached | Fallback registry for file/database |
| Route-level HTTP caching | No built-in solution | `ModuleRouteCache` middleware |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Application                           │
├─────────────────────────────────────────────────────────────┤
│  Routes ──► ModuleRouteCache Middleware ──► Cache Store    │
│                              │                              │
│                              ▼                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              ModuleCacheManager                      │   │
│  │  • rememberRoute()                                  │   │
│  │  • invalidateTags()                                 │   │
│  │  • invalidateModule()                               │   │
│  │  • getTenantTags()                                  │   │
│  └─────────────────────────────────────────────────────┘   │
│                              │                              │
│              ┌───────────────┴───────────────┐             │
│              ▼                               ▼              │
│     ┌─────────────┐                ┌─────────────────┐     │
│     │  Tag-aware  │                │  Fallback       │     │
│     │  (Redis/     │                │  Registry       │     │
│     │  Memcached)  │                │  (Database)     │     │
│     └─────────────┘                └─────────────────┘     │
│                                                              │
│  Models ──► *CacheObserver ──► CacheObserverManager        │
│                                     │                       │
│                                     ▼                       │
│                          invalidateTags()                   │
└─────────────────────────────────────────────────────────────┘
```

---

## Installation & Setup

### Step 1: Run the Installation Script

Place `install-cache-feature.sh` in your package root and execute:

```bash
cd /path/to/your/package
bash install-cache-feature.sh
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=asasflow-config
```

### Step 3: Run Migrations

```bash
php artisan migrate
```

This creates:
- `asasflow_tenants` — Tenant records
- `asasflow_tenant_domains` — Domain mappings
- `asasflow_cache_registry` — Fallback key registry

### Step 4: Configure Environment

Add to your `.env`:

```env
# Cache Settings
ASASFLOW_CACHE_ENABLED=true
ASASFLOW_CACHE_STORE=redis
ASASFLOW_CACHE_PREFIX=asasflow
ASASFLOW_CACHE_TTL=3600
ASASFLOW_CACHE_STRICT_ISOLATION=true
ASASFLOW_CACHE_AUTH=false
ASASFLOW_CACHE_DEBUG=false

# Optional: If using tenancy
ASASFLOW_TENANCY_ENABLED=false
```

### Step 5: Register Service Provider

Ensure your `config/app.php` (or auto-discovery) includes:

```php
'providers' => [
    // ...
    \AsasFlow\AsasFlowServiceProvider::class,
],
```

---

## Configuration

Full configuration reference (`config/asasflow.php`):

```php
<?php

return [
    'cache' => [
        'enabled'              => env('ASASFLOW_CACHE_ENABLED', true),
        'store'                => env('ASASFLOW_CACHE_STORE', 'redis'),
        'prefix'               => env('ASASFLOW_CACHE_PREFIX', 'asasflow'),
        'default_ttl'          => env('ASASFLOW_CACHE_TTL', 3600),
        'strict_isolation'     => env('ASASFLOW_CACHE_STRICT_ISOLATION', true),
        'cache_authenticated'  => env('ASASFLOW_CACHE_AUTH', false),
        'warm_via_queue'       => env('ASASFLOW_CACHE_QUEUE', true),
        'fallback_registry'    => true,
        'debug_headers'        => env('ASASFLOW_CACHE_DEBUG', false),
        'http_max_age'         => env('ASASFLOW_CACHE_HTTP_MAX_AGE', 0),
    ],
];
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `true` | Master switch for cache feature |
| `store` | string | `redis` | Cache store from `config/cache.php` |
| `prefix` | string | `asasflow` | Prefix for all cache keys |
| `default_ttl` | int | `3600` | Default expiration in seconds |
| `strict_isolation` | bool | `true` | Prefix keys with tenant ID when tenancy active |
| `cache_authenticated` | bool | `false` | Whether to cache authenticated user responses |
| `warm_via_queue` | bool | `true` | Dispatch warming jobs to queue |
| `fallback_registry` | bool | `true` | Track keys in DB for non-tag drivers |
| `debug_headers` | bool | `false` | Add `X-ASASFLOW-Cache` header (local only) |
| `http_max_age` | int | `0` | HTTP Cache-Control max-age |

---

## Core Concepts

### Cache Tags

Tags are the foundation of the invalidation system. A tag is a string identifier:

```php
// Module-level tag
'module:users'           // All user-related cache

// Resource-level tag
'module:users:list'      // User list/index endpoints
'module:users:42'        // Specific user record

// Relationship tag
'module:departments:5:users'  // Users in department 5

// Tenant tag (auto-injected)
't:123:module:users'     // Tenant 123's user cache
```

### Tag Hierarchy

```
module:users
├── module:users:list
├── module:users:count
├── module:users:42
├── module:users:43
└── module:users:search
```

**Invalidating `module:users`** clears **all** child entries.

---

## Usage Patterns

### Pattern 1: Route-Level Caching (Recommended)

Apply middleware to routes for automatic HTTP response caching:

```php
// routes/api.php (inside a module)

use Illuminate\Support\Facades\Route;

Route::prefix('users')->group(function () {
    
    // Cache user list for 1 hour
    Route::get('/', [UserController::class, 'index'])
        ->middleware('asasflow.cache:module:users,users:list')
        ->name('module.users.index');
    
    // Cache specific user forever (until model changes)
    Route::get('/{user}', [UserController::class, 'show'])
        ->middleware('asasflow.cache:module:users,users:record')
        ->name('module.users.show');
    
    // Cache search results for 10 minutes
    Route::get('/search', [UserController::class, 'search'])
        ->middleware('asasflow.cache:module:users,users:search,600')
        ->name('module.users.search');
});
```

**Middleware Syntax:**
```php
'asasflow.cache:tag1,tag2,ttl'
```

- Tags are comma-separated
- Last numeric value is treated as TTL (optional)
- `module:users` is auto-injected based on route name

### Pattern 2: Controller-Level Caching

For complex logic where middleware isn't sufficient:

```php
<?php

namespace Modules\Users\Http\Controllers;

use AsasFlow\Features\Cache\Facades\ModuleCache;
use App\Http\Controllers\Controller;
use Modules\Users\Models\User;

class UserController extends Controller
{
    public function index()
    {
        // Cache the paginated response
        return ModuleCache::rememberRoute(
            'users.index.' . request('page', 1),
            ['module:users', 'module:users:list'],
            function () {
                return User::with('department')->paginate(20);
            },
            1800 // 30 minutes
        );
    }

    public function show(User $user)
    {
        // Cache specific user with ID-based tag
        return ModuleCache::rememberRoute(
            "users.show.{$user->id}",
            ['module:users', "module:users:{$user->id}"],
            function () use ($user) {
                return $user->load('department', 'roles');
            }
        );
    }

    public function dashboardStats()
    {
        // Multiple independent caches
        $totalUsers = ModuleCache::remember(
            'users.stats.total',
            ['module:users', 'module:users:stats'],
            fn() => User::count(),
            3600
        );

        $activeUsers = ModuleCache::remember(
            'users.stats.active',
            ['module:users', 'module:users:stats'],
            fn() => User::where('is_active', true)->count(),
            3600
        );

        return response()->json([
            'total' => $totalUsers,
            'active' => $activeUsers,
        ]);
    }
}
```

### Pattern 3: Repository Pattern Caching

Best for data access layers:

```php
<?php

namespace Modules\Users\Repositories;

use AsasFlow\Features\Cache\Facades\ModuleCache;
use Modules\Users\Models\User;

class UserRepository
{
    public function find(int $id): ?User
    {
        return ModuleCache::remember(
            "users.find.{$id}",
            ['module:users', "module:users:{$id}"],
            fn() => User::find($id)
        );
    }

    public function findByEmail(string $email): ?User
    {
        return ModuleCache::remember(
            "users.email.{$email}",
            ['module:users', 'module:users:by-email'],
            fn() => User::where('email', $email)->first()
        );
    }

    public function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        return ModuleCache::remember(
            'users.active',
            ['module:users', 'module:users:active'],
            fn() => User::where('is_active', true)->get(),
            600 // 10 min
        );
    }

    public function create(array $data): User
    {
        $user = User::create($data);
        
        // Observer will auto-invalidate, but we can force it
        ModuleCache::invalidateTags(['module:users:list', 'module:users:count']);
        
        return $user;
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        
        // Specific record + list caches cleared
        ModuleCache::invalidateTags([
            "module:users:{$user->id}",
            'module:users:list',
        ]);
        
        return $user->fresh();
    }

    public function delete(User $user): bool
    {
        $deleted = $user->delete();
        
        // Full module invalidation on delete
        ModuleCache::invalidateModule('users');
        
        return $deleted;
    }
}
```

---

## Route Caching

### Basic Route Cache

```php
Route::get('/api/users', [UserController::class, 'index'])
    ->middleware('asasflow.cache')
    ->name('module.users.index');
```

Without parameters, it auto-detects:
- Module from route name (`module.users.index` → `users`)
- Cache key from URL + query params
- Default TTL from config

### Advanced Route Cache

```php
Route::get('/api/users', [UserController::class, 'index'])
    ->middleware('asasflow.cache:module:users,users:list,department:all,1800')
    ->middleware('asasflow.cache.headers:public,300');
```

**Parameter breakdown:**
| Parameter | Example | Purpose |
|-----------|---------|---------|
| `module:users` | `module:users` | Module tag for grouping |
| `users:list` | `users:list` | Resource-specific tag |
| `department:all` | `department:all` | Custom tag for cross-module invalidation |
| `1800` | `1800` | TTL override (seconds) |

### Combining with Other Middleware

```php
Route::middleware([
    'auth:sanctum',
    'asasflow.tenancy',      // Initialize tenant first
    'asasflow.cache:module:users,users:list',  // Then cache
    'throttle:60,1',
])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});
```

**Order matters:** Tenant must be initialized before cache middleware runs.

---

## Manual Cache Operations

### Remember (Get or Store)

```php
use AsasFlow\Features\Cache\Facades\ModuleCache;

$value = ModuleCache::remember(
    'key.name',              // Cache key
    ['module:users'],        // Tags for invalidation
    function () {            // Callback when cache miss
        return User::all();
    },
    3600                     // TTL (optional)
);
```

### Get Existing Value

```php
$users = ModuleCache::get('users.all');

// With default
$users = ModuleCache::get('users.all', collect());
```

### Store Value

```php
ModuleCache::put(
    'users.stats',
    ['total' => 150, 'active' => 120],
    ['module:users', 'module:users:stats'],
    1800
);
```

### Invalidate by Tags

```php
// Single tag
ModuleCache::invalidateTags(['module:users']);

// Multiple tags
ModuleCache::invalidateTags([
    'module:users',
    'module:users:list',
    'module:users:count',
]);

// With explicit tenant (for cross-tenant operations)
ModuleCache::invalidateTags(['module:users'], 'tenant-123');
```

### Invalidate by Module

```php
// Clear ALL cache for users module
ModuleCache::invalidateModule('users');

// For specific tenant
ModuleCache::invalidateModule('users', 'tenant-456');
```

### Invalidate Specific Route

```php
ModuleCache::invalidateRoute('module.users.index');
ModuleCache::invalidateRoute('module.users.show', ['user' => 42]);
```

### Flush Everything

```php
ModuleCache::flushAll();
```

---

## Model Observer Integration

### Auto-Generated Observer (via CLI)

When you generate a module, the cache observer is auto-created:

```php
<?php

namespace Modules\Users\Observers;

use Modules\Users\Models\User;
use AsasFlow\Features\Cache\Services\CacheObserverManager;
use AsasFlow\Features\Cache\Services\ModuleCacheManager;

class UserCacheObserver
{
    public function __construct(
        protected ModuleCacheManager $cacheManager,
        protected CacheObserverManager $observerManager
    ) {}

    public function created(User $user): void
    {
        $this->observerManager->handleCreated($user, [
            'module:users',
            'module:users:list',
            'module:users:count',
        ]);
    }

    public function updated(User $user): void
    {
        $relationshipTags = [];

        if ($user->isDirty('department_id')) {
            $relationshipTags[] = "module:departments:{$user->getOriginal('department_id')}:users";
            $relationshipTags[] = "module:departments:{$user->department_id}:users";
        }

        $this->observerManager->handleUpdated($user, [
            'module:users',
            "module:users:{$user->id}",
            'module:users:list',
        ], $relationshipTags);
    }

    public function deleted(User $user): void
    {
        $this->observerManager->handleDeleted($user, [
            'module:users',
            "module:users:{$user->id}",
            'module:users:list',
            'module:users:count',
        ]);
    }
}
```

### Manual Observer Registration

In your module's service provider:

```php
<?php

namespace Modules\Users;

use Illuminate\Support\ServiceProvider;
use Modules\Users\Models\User;
use Modules\Users\Observers\UserCacheObserver;

class UsersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::observe(UserCacheObserver::class);
    }
}
```

### Custom Observer with Complex Logic

```php
<?php

namespace Modules\Orders\Observers;

use Modules\Orders\Models\Order;
use AsasFlow\Features\Cache\Services\CacheObserverManager;

class OrderCacheObserver
{
    public function __construct(
        protected CacheObserverManager $observerManager
    ) {}

    public function created(Order $order): void
    {
        // Invalidate order lists
        $this->observerManager->handleCreated($order, [
            'module:orders',
            'module:orders:list',
            'module:orders:pending',
            'module:orders:count',
        ]);
        
        // Invalidate customer cache (customer now has new order)
        $this->observerManager->handleCreated($order, [
            "module:customers:{$order->customer_id}:orders",
        ]);
    }

    public function updated(Order $order): void
    {
        $tags = [
            'module:orders',
            "module:orders:{$order->id}",
            'module:orders:list',
        ];

        $relationshipTags = [];

        // Status change affects filtered lists
        if ($order->isDirty('status')) {
            $oldStatus = $order->getOriginal('status');
            $newStatus = $order->status;
            
            $relationshipTags[] = "module:orders:status:{$oldStatus}";
            $relationshipTags[] = "module:orders:status:{$newStatus}";
            
            // If completed, invalidate revenue stats
            if ($newStatus === 'completed') {
                $relationshipTags[] = 'module:reports:revenue';
                $relationshipTags[] = 'module:reports:sales';
            }
        }

        $this->observerManager->handleUpdated($order, $tags, $relationshipTags);
    }

    public function deleted(Order $order): void
    {
        $this->observerManager->handleDeleted($order, [
            'module:orders',
            "module:orders:{$order->id}",
            'module:orders:list',
            'module:orders:pending',
            'module:orders:count',
            "module:customers:{$order->customer_id}:orders",
        ]);
    }
}
```

---

## CLI Commands

### Check Cache Status

```bash
$ php artisan asasflow:cache:status

=== ASASFLOW Cache Status ===
+---------------+----------+
| Property      | Value    |
+---------------+----------+
| Enabled       | ✅ Yes   |
| Store         | redis    |
| Driver        | redis    |
| Tag Support   | ✅ Yes   |
| Prefix        | asasflow |
| Default TTL   | 3600s    |
| Strict Iso.   | ✅ Yes   |
| Fallback Reg. | ✅ Yes   |
+---------------+----------+
```

### Flush Cache

```bash
# Flush specific module
php artisan asasflow:cache:flush users

# Flush module for specific tenant
php artisan asasflow:cache:flush users --tenant=acme-corp

# Flush all cache
php artisan asasflow:cache:flush --all

# Flush with confirmation (production)
php artisan asasflow:cache:flush --all --force
```

### Warm Cache

```bash
# Warm specific module
php artisan asasflow:cache:warm users

# Warm for tenant
php artisan asasflow:cache:warm users --tenant=acme-corp

# Warm multiple modules
php artisan asasflow:cache:warm users
php artisan asasflow:cache:warm orders
php artisan asasflow:cache:warm products
```

---

## Facade Reference

### `ModuleCache` Facade Methods

```php
use AsasFlow\Features\Cache\Facades\ModuleCache;
```

| Method | Signature | Description |
|--------|-----------|-------------|
| `rememberRoute` | `rememberRoute(string $key, array $tags, \Closure $callback, ?int $ttl = null): mixed` | Cache HTTP route response |
| `remember` | `remember(string $key, array $tags, \Closure $callback, ?int $ttl = null): mixed` | General cache remember |
| `get` | `get(string $key, mixed $default = null): mixed` | Get cached value |
| `put` | `put(string $key, mixed $value, array $tags = [], ?int $ttl = null): bool` | Store value |
| `invalidateTags` | `invalidateTags(array $tags, ?string $tenantId = null): void` | Clear by tags |
| `invalidateRoute` | `invalidateRoute(string $routeName, array $params = [], ?string $tenantId = null): void` | Clear specific route |
| `invalidateModule` | `invalidateModule(string $module, ?string $tenantId = null): void` | Clear module cache |
| `invalidateTenantCache` | `invalidateTenantCache(string $tenantId): void` | Clear all tenant cache |
| `invalidateGlobalModuleCache` | `invalidateGlobalModuleCache(string $module): void` | Clear module across all tenants |
| `warmModuleCache` | `warmModuleCache(string $module, ?string $tenantId = null): void` | Warm module cache |
| `flushAll` | `flushAll(): void` | Clear everything |
| `supportsTags` | `supportsTags(): bool` | Check if driver supports tags |

---

## Advanced Patterns

### Pattern: Cache-Aside with Repository

```php
class ProductRepository
{
    public function findWithCache(int $id): Product
    {
        // Try cache first
        $cached = ModuleCache::get("products.{$id}");
        
        if ($cached) {
            return $cached;
        }
        
        // Load from DB
        $product = Product::findOrFail($id);
        
        // Store in cache
        ModuleCache::put(
            "products.{$id}",
            $product,
            ['module:products', "module:products:{$id}"],
            7200
        );
        
        return $product;
    }
}
```

### Pattern: Conditional Cache Invalidation

```php
class InventoryService
{
    public function adjustStock(Product $product, int $quantity): void
    {
        $product->increment('stock', $quantity);
        
        // Only invalidate if stock crossed threshold
        if ($product->wasChanged('stock')) {
            $oldStock = $product->getOriginal('stock');
            $newStock = $product->stock;
            
            // Invalidate if went from out-of-stock to in-stock
            if ($oldStock <= 0 && $newStock > 0) {
                ModuleCache::invalidateTags([
                    'module:products:available',
                    'module:products:in-stock',
                ]);
            }
            
            // Always invalidate specific product
            ModuleCache::invalidateTags([
                "module:products:{$product->id}",
            ]);
        }
    }
}
```

### Pattern: Multi-Step Transaction with Deferred Invalidation

```php
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function processOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Step 1: Reserve inventory
            foreach ($order->items as $item) {
                $item->product->decrement('stock', $item->quantity);
            }
            
            // Step 2: Charge payment
            $order->payment->process();
            
            // Step 3: Update status
            $order->update(['status' => 'processing']);
            
            // Defer cache invalidation until after commit
            DB::afterCommit(function () use ($order) {
                ModuleCache::invalidateTags([
                    'module:orders',
                    "module:orders:{$order->id}",
                    'module:products:stock',
                ]);
            });
        });
    }
}
```

### Pattern: Bulk Operation Optimization

```php
class UserImportService
{
    public function import(array $usersData): void
    {
        // Disable observers during bulk import
        User::withoutEvents(function () use ($usersData) {
            foreach (array_chunk($usersData, 1000) as $chunk) {
                User::insert($chunk);
            }
        });
        
        // Single invalidation after bulk insert
        ModuleCache::invalidateModule('users');
        
        // Warm cache if needed
        ModuleCache::warmModuleCache('users');
    }
}
```

### Pattern: Cache Warming Job

```php
<?php

namespace Modules\Products\Jobs;

use AsasFlow\Features\Cache\Facades\ModuleCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Products\Models\Product;

class WarmProductCache implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        // Warm featured products
        $featured = Product::featured()->limit(20)->get();
        ModuleCache::put(
            'products.featured',
            $featured,
            ['module:products', 'module:products:featured'],
            3600
        );

        // Warm categories list
        $categories = Product::select('category')->distinct()->pluck('category');
        ModuleCache::put(
            'products.categories',
            $categories,
            ['module:products', 'module:products:categories'],
            7200
        );

        // Warm top sellers
        $topSellers = Product::topSellers()->limit(10)->get();
        ModuleCache::put(
            'products.top-sellers',
            $topSellers,
            ['module:products', 'module:products:top-sellers'],
            1800
        );
    }
}
```

---

## Troubleshooting

### Issue: Cache not invalidating on model changes

**Cause:** Observer not registered.

**Fix:**
```php
// In your module service provider
User::observe(UserCacheObserver::class);

// Or check auto-discovery in AsasFlowServiceProvider
```

### Issue: `BadMethodCallException: This cache store does not support tagging`

**Cause:** Using file/database driver without fallback registry.

**Fix:**
```env
ASASFLOW_CACHE_STORE=redis
```

Or enable fallback:
```php
'fallback_registry' => true,  // In config
```

### Issue: Tenant cache leaking between tenants

**Cause:** `strict_isolation` disabled or tenant context not initialized.

**Fix:**
```env
ASASFLOW_CACHE_STRICT_ISOLATION=true
```

Ensure `InitializeTenancy` middleware runs before cache middleware.

### Issue: Cache keys growing unbounded

**Cause:** Cache registry not being cleaned.

**Fix:** Add to scheduler:
```php
// app/Console/Kernel.php
$schedule->command('model:prune', [
    '--model' => [\AsasFlow\Features\Cache\Models\CacheRegistry::class],
])->daily();
```

### Issue: Observer firing too many times in loops

**Fix:** Use `withoutEvents` for bulk operations:
```php
User::withoutEvents(function () {
    foreach ($users as $user) {
        $user->update(['status' => 'active']);
    }
});

// Manual invalidation after loop
ModuleCache::invalidateModule('users');
```

---

# ASASFLOW Tenancy Feature — Complete Documentation

## Table of Contents
1. [Overview](#overview-1)
2. [Architecture](#architecture-1)
3. [Database Strategies](#database-strategies)
4. [Installation & Setup](#installation--setup-1)
5. [Configuration](#configuration-1)
6. [Tenant Resolution](#tenant-resolution)
7. [Usage Patterns](#usage-patterns-1)
8. [Cache Integration](#cache-integration)
9. [Queue Jobs](#queue-jobs)
10. [Advanced Patterns](#advanced-patterns-1)
11. [Migration Guide](#migration-guide)
12. [Troubleshooting](#troubleshooting-1)

---

## Overview

The ASASFLOW Tenancy Feature provides **built-in multi-tenancy** for your modular microservices package. It supports:

- **Single Database** (`tenant_id` column) — Simple, shared tables
- **Separate Database** (per-tenant DB) — Full isolation, compliance-friendly
- **Hybrid approach** — Start with single DB, migrate to separate later

### Key Features

| Feature | Description |
|---------|-------------|
| **Domain-based resolution** | `acme.yourapp.com` → Tenant `acme` |
| **Header-based resolution** | `X-Tenant-ID: 123` or `X-Tenant-Slug: acme` |
| **Cache isolation** | Automatic tenant prefixing of cache keys |
| **Database switching** | Dynamic connection switching for separate DB strategy |
| **Central routes** | Admin/landlord routes bypass tenant resolution |
| **Queue context** | Tenant ID carried through background jobs |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Request                               │
│                         │                                    │
│              ┌──────────┴──────────┐                        │
│              ▼                      ▼                        │
│     Central Domain?         Tenant Domain?                   │
│     (admin.yourapp.com)     (acme.yourapp.com)             │
│              │                      │                        │
│              ▼                      ▼                        │
│     Skip Tenancy          InitializeTenancy Middleware       │
│                                  │                         │
│                                  ▼                         │
│                        TenantContext::initialize()           │
│                                  │                         │
│                    ┌─────────────┴─────────────┐           │
│                    ▼                             ▼           │
│            Single DB Strategy            Separate DB Strategy │
│            (tenant_id column)            (switch connection)  │
│                    │                             │           │
│                    ▼                             ▼           │
│            Cache prefix:           Cache prefix:             │
│            t:123:module:users      t:123:module:users       │
│                    │                             │           │
│                    └─────────────┬───────────────┘           │
│                                  ▼                         │
│                         Application Code                     │
│                         (unchanged)                          │
└─────────────────────────────────────────────────────────────┘
```

---

## Database Strategies

### Strategy 1: Single Database (`tenant_id` column)

**Best for:** MVPs, rapid scaling, shared resources

```
Database: yourapp_db
├── asasflow_tenants (central)
├── asasflow_tenant_domains (central)
├── users (has tenant_id)
├── orders (has tenant_id)
├── products (has tenant_id)
└── ...
```

**Pros:**
- Simple backups
- Easy cross-tenant analytics
- Lower infrastructure cost
- Faster tenant provisioning

**Cons:**
- Data isolation is application-level
- Risk of cross-tenant leaks via bugs
- Harder compliance certification

### Strategy 2: Separate Database (per tenant)

**Best for:** Enterprise, compliance (HIPAA, SOC2), data isolation requirements

```
Database: yourapp_db (central)
├── asasflow_tenants
├── asasflow_tenant_domains
└── ...

Database: tenant_acme (tenant)
├── users
├── orders
├── products
└── ...

Database: tenant_corp (tenant)
├── users
├── orders
├── products
└── ...
```

**Pros:**
- True data isolation
- Easier compliance
- Independent scaling per tenant
- Tenant-specific migrations

**Cons:**
- Complex backups
- Harder cross-tenant queries
- Higher infrastructure cost
- Connection pooling considerations

### Strategy Comparison

| Aspect | Single DB | Separate DB |
|--------|-----------|-------------|
| Setup complexity | Low | Medium |
| Data isolation | Logical | Physical |
| Backup/Restore | One command per DB | Per-tenant scripts |
| Cross-tenant queries | Easy (same connection) | Requires central DB |
| Migration management | Single run | Per-tenant run |
| Connection pooling | Standard | Needs tuning |
| Compliance | Harder | Easier |

---

## Installation & Setup

### Step 1: Enable Tenancy

```env
ASASFLOW_TENANCY_ENABLED=true
ASASFLOW_TENANCY_DB_STRATEGY=single  # or 'separate'
ASASFLOW_CENTRAL_DOMAINS=localhost,admin.yourapp.com,127.0.0.1
```

### Step 2: Run Migrations

```bash
php artisan migrate
```

Creates:
- `asasflow_tenants` — Tenant records
- `asasflow_tenant_domains` — Domain-to-tenant mappings

### Step 3: Create Your First Tenant

```php
use AsasFlow\Features\Tenancy\Models\Tenant;
use AsasFlow\Features\Tenancy\Models\TenantDomain;

$tenant = Tenant::create([
    'slug' => 'acme-corp',
    'name' => 'Acme Corporation',
    'domain' => 'acme.yourapp.com',
    'plan' => 'enterprise',
    'settings' => [
        'theme' => 'dark',
        'currency' => 'USD',
        'timezone' => 'America/New_York',
    ],
]);

// Add additional domains
$tenant->domains()->create([
    'domain' => 'app.acme.com',
    'is_primary' => false,
    'is_verified' => true,
]);
```

### Step 4: Configure Web Server

Point wildcard subdomain to your application:

```nginx
# Nginx
server {
    server_name ~^(?<tenant>.+)\.yourapp\.com$;
    root /var/www/yourapp/public;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

Or for local testing, add to `/etc/hosts`:

```
127.0.0.1  acme.yourapp.local
127.0.0.1  corp.yourapp.local
```

---

## Configuration

Full tenancy configuration:

```php
<?php

return [
    'tenancy' => [
        // Enable multi-tenancy
        'enabled' => env('ASASFLOW_TENANCY_ENABLED', false),

        // Database strategy: 'single' or 'separate'
        'database_strategy' => env('ASASFLOW_TENANCY_DB_STRATEGY', 'single'),

        // Central domains (landlord access)
        'central_domains' => explode(',', env('ASASFLOW_CENTRAL_DOMAINS', 'localhost,127.0.0.1')),

        // Routes that skip tenant resolution
        'central_routes' => [
            'api/central/*',    // Central admin API
            'admin/*',          // Admin dashboard
            'health',           // Health checks
            'tenants/*',        // Tenant management
            'asasflow/*',       // Package internal routes
        ],

        // Tenant model class
        'tenant_model' => \AsasFlow\Features\Tenancy\Models\Tenant::class,

        // Connection name for tenant databases (separate strategy)
        'tenant_connection' => 'tenant',

        // Central connection name
        'central_connection' => config('database.default', 'mysql'),

        // Auto-create tenant database on creation
        'auto_create_db' => true,

        // Auto-run tenant migrations
        'auto_migrate' => true,

        // Cache tenant resolution
        'cache_resolution' => true,
        'cache_resolution_ttl' => 3600,
    ],
];
```

### Database Connections (for Separate DB Strategy)

Add to `config/database.php`:

```php
'connections' => [
    // ... existing connections

    'tenant' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => null,  // Set dynamically by TenantContext
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ],
],
```

---

## Tenant Resolution

### Resolution Priority

1. **Explicit identifier** (CLI, queue jobs, tests)
2. **`X-Tenant-ID` header** (API clients)
3. **`X-Tenant-Slug` header** (API clients)
4. **Domain-based** (web requests)

### Resolution Examples

```php
use AsasFlow\Features\Tenancy\Services\TenantContext;

// 1. From domain (auto)
// Request: https://acme.yourapp.com/api/users
TenantContext::initialize();  // Resolves 'acme' tenant

// 2. From header
// Request with header: X-Tenant-ID: 42
TenantContext::initialize();  // Resolves tenant ID 42

// 3. Explicit (for CLI, queues)
TenantContext::initialize('acme-corp');

// 4. Check if resolved
if (TenantContext::isTenantInitialized()) {
    $tenant = TenantContext::getCurrentTenant();
    echo "Current tenant: {$tenant->name}";
}

// 5. Get tenant info
$tenantId = TenantContext::getTenantId();      // "42"
$tenantSlug = TenantContext::getTenantSlug();  // "acme-corp"
```

### Middleware Application

The `InitializeTenancy` middleware is **auto-applied** to all module routes:

```php
// This happens automatically in TenancyServiceProvider
$router->matched(function ($route, $request) {
    if (str_starts_with($route->getName() ?? '', 'module.')) {
        $route->middleware('asasflow.tenancy');
    }
});
```

**Manual application** (if auto-apply is disabled):

```php
Route::middleware(['asasflow.tenancy'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});
```

---

## Usage Patterns

### Pattern 1: Single DB — Scoped Queries

```php
<?php

namespace Modules\Users\Models;

use AsasFlow\Features\Tenancy\Contracts\TenantAware;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements TenantAware
{
    protected $fillable = ['name', 'email', 'tenant_id'];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-scope all queries to current tenant
        static::addGlobalScope('tenant', function ($query) {
            if (TenantContext::isTenantInitialized()) {
                $query->where('tenant_id', TenantContext::getTenantId());
            }
        });

        // Auto-set tenant_id on create
        static::creating(function ($model) {
            if (!$model->tenant_id && TenantContext::isTenantInitialized()) {
                $model->tenant_id = TenantContext::getTenantId();
            }
        });
    }

    public function getTenantId(): ?string
    {
        return $this->tenant_id;
    }

    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', TenantContext::getTenantId());
    }
}
```

**Query examples:**

```php
// Automatically scoped to current tenant
$users = User::all();  // SELECT * FROM users WHERE tenant_id = 42

// Override scope (admin only)
$allUsers = User::withoutGlobalScope('tenant')->get();

// Cross-tenant query (central admin)
$acmeUsers = User::where('tenant_id', $acmeTenant->id)->get();
```

### Pattern 2: Separate DB — Transparent Switching

With separate DB strategy, your code **doesn't change**:

```php
// Same code works for both strategies
$users = User::all();

// Behind the scenes (separate DB):
// 1. TenantContext switches to 'tenant_acme' connection
// 2. Query runs on tenant's database
// 3. Results returned
```

**Tenant-specific migrations:**

```php
// app/Console/Commands/TenantMigrate.php
use AsasFlow\Features\Tenancy\Models\Tenant;
use AsasFlow\Features\Tenancy\Services\TenantContext;

class TenantMigrate extends Command
{
    protected $signature = 'tenant:migrate {--tenant=} {--fresh}';

    public function handle(): void
    {
        $tenants = $this->option('tenant') 
            ? Tenant::where('slug', $this->option('tenant'))->get()
            : Tenant::all();

        foreach ($tenants as $tenant) {
            TenantContext::runForTenant($tenant, function () {
                $this->call('migrate', [
                    '--path' => 'database/migrations/tenant',
                    '--database' => 'tenant',
                    '--force' => true,
                ]);
            });
            
            $this->info("Migrated tenant: {$tenant->name}");
        }
    }
}
```

### Pattern 3: Tenant-Aware Resources

```php
<?php

namespace Modules\Products\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $tenant = TenantContext::getCurrentTenant();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            
            // Tenant-specific formatting
            'price_formatted' => $this->formatPrice($tenant),
            
            // Tenant-specific features
            'features' => $tenant->settings['product_features'] ?? [],
            
            // Tenant branding
            'brand_color' => $tenant->settings['brand_color'] ?? '#000000',
        ];
    }

    protected function formatPrice(Tenant $tenant): string
    {
        $currency = $tenant->settings['currency'] ?? 'USD';
        $symbol = match($currency) {
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };
        
        return $symbol . number_format($this->price, 2);
    }
}
```

### Pattern 4: Tenant Settings Integration

```php
<?php

namespace Modules\Settings\Services;

use AsasFlow\Features\Tenancy\Services\TenantContext;

class TenantSettingsService
{
    /**
     * Get setting with tenant override.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $tenant = TenantContext::getCurrentTenant();
        
        // Check tenant-specific setting first
        if ($tenant && isset($tenant->settings[$key])) {
            return $tenant->settings[$key];
        }
        
        // Fall back to global setting
        return config("asasflow.settings.{$key}", $default);
    }

    /**
     * Set tenant-specific setting.
     */
    public function set(string $key, mixed $value): void
    {
        $tenant = TenantContext::getCurrentTenant();
        
        if (!$tenant) {
            throw new \RuntimeException('No active tenant');
        }
        
        $settings = $tenant->settings ?? [];
        $settings[$key] = $value;
        
        $tenant->update(['settings' => $settings]);
        
        // Clear tenant settings cache
        \AsasFlow\Features\Cache\Facades\ModuleCache::invalidateTags(
            ["tenant:{$tenant->id}:settings"]
        );
    }
}
```

---

## Cache Integration

### Automatic Cache Isolation

When tenancy is active, **all cache operations are automatically scoped**:

```php
use AsasFlow\Features\Cache\Facades\ModuleCache;

// Request from acme.yourapp.com (Tenant ID: 42)
ModuleCache::remember('users.all', ['module:users'], function () {
    return User::all();
});

// Actual cache key: asasflow:t:42:users.all
// Actual tags: [asasflow:t:42:module:users]
```

### Manual Tenant Cache Operations

```php
// Invalidate for specific tenant
ModuleCache::invalidateModule('users', 'tenant-42');

// Warm cache for specific tenant
ModuleCache::warmModuleCache('users', 'tenant-42');

// Get cache for different tenant (admin operation)
TenantContext::runForTenant('acme-corp', function () {
    $users = ModuleCache::get('users.all');
    return $users;
});
```

### Cross-Tenant Cache Clearing

```php
// Admin needs to clear "users" module for ALL tenants
ModuleCache::invalidateGlobalModuleCache('users');

// This uses Redis SCAN to find and delete:
// asasflow:t:*:module:users*
```

---

## Queue Jobs

### Tenant-Aware Jobs

```php
<?php

namespace Modules\Orders\Jobs;

use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Orders\Models\Order;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected Order $order;
    protected ?string $tenantId;

    public function __construct(Order $order)
    {
        $this->order = $order;
        // Capture tenant at dispatch time
        $this->tenantId = TenantContext::getTenantId();
    }

    public function handle(): void
    {
        // Restore tenant context
        if ($this->tenantId) {
            $tenant = TenantContext::findTenant($this->tenantId);
            if ($tenant) {
                TenantContext::setTenant($tenant);
            }
        }

        // Now all operations are tenant-scoped
        $this->processPayment();
        $this->updateInventory();
        $this->sendNotification();
    }

    protected function processPayment(): void
    {
        // Uses tenant's payment gateway settings
        $gateway = TenantContext::getCurrentTenant()->settings['payment_gateway'];
        // ...
    }
}
```

### Dispatching with Tenant Context

```php
// In controller
ProcessOrder::dispatch($order);  // Tenant auto-captured

// Or explicit
TenantContext::runForTenant('acme-corp', function () use ($order) {
    ProcessOrder::dispatch($order);
});
```

### Base Tenant-Aware Job Class

```php
<?php

namespace AsasFlow\Foundation\Jobs;

use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected ?string $tenantId;

    public function __construct()
    {
        $this->tenantId = TenantContext::getTenantId();
    }

    public function handle(): void
    {
        $this->initializeTenant();
        $this->tenantHandle();
    }

    abstract protected function tenantHandle(): void;

    protected function initializeTenant(): void
    {
        if (!$this->tenantId) {
            return;
        }

        $tenant = TenantContext::findTenant($this->tenantId);
        if ($tenant) {
            TenantContext::setTenant($tenant);
        }
    }
}
```

**Usage:**

```php
class SendInvoiceEmail extends TenantAwareJob
{
    protected Invoice $invoice;

    public function __construct(Invoice $invoice)
    {
        parent::__construct();
        $this->invoice = $invoice;
    }

    protected function tenantHandle(): void
    {
        // Tenant context is already initialized
        Mail::to($this->invoice->customer->email)->send(
            new InvoiceMail($this->invoice)
        );
    }
}
```

---

## Advanced Patterns

### Pattern: Tenant Onboarding Flow

```php
<?php

namespace App\Services;

use AsasFlow\Features\Tenancy\Models\Tenant;
use AsasFlow\Features\Tenancy\Models\TenantDomain;
use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantProvisioningService
{
    public function provision(string $slug, string $name, string $domain): Tenant
    {
        // 1. Create tenant record
        $tenant = Tenant::create([
            'slug' => $slug,
            'name' => $name,
            'domain' => $domain,
            'database' => "tenant_{$slug}",
            'plan' => 'trial',
            'settings' => $this->getDefaultSettings(),
        ]);

        // 2. Create domain mapping
        $tenant->domains()->create([
            'domain' => $domain,
            'is_primary' => true,
            'is_verified' => true,
        ]);

        // 3. Create database (separate strategy)
        if (config('asasflow.tenancy.database_strategy') === 'separate') {
            $this->createTenantDatabase($tenant);
            $this->runTenantMigrations($tenant);
        }

        // 4. Seed default data
        $this->seedTenantData($tenant);

        // 5. Warm cache
        TenantContext::runForTenant($tenant, function () {
            \AsasFlow\Features\Cache\Facades\ModuleCache::warmModuleCache('settings');
            \AsasFlow\Features\Cache\Facades\ModuleCache::warmModuleCache('users');
        });

        return $tenant;
    }

    protected function createTenantDatabase(Tenant $tenant): void
    {
        DB::statement("CREATE DATABASE IF NOT EXISTS {$tenant->getDatabaseName()}");
    }

    protected function runTenantMigrations(Tenant $tenant): void
    {
        TenantContext::runForTenant($tenant, function () {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--database' => 'tenant',
                '--force' => true,
            ]);
        });
    }

    protected function seedTenantData(Tenant $tenant): void
    {
        TenantContext::runForTenant($tenant, function () {
            // Create admin user
            \Modules\Users\Models\User::create([
                'name' => 'Admin',
                'email' => 'admin@tenant.com',
                'role' => 'admin',
            ]);

            // Create default settings
            \Modules\Settings\Models\Setting::insert([
                ['key' => 'theme', 'value' => 'light'],
                ['key' => 'language', 'value' => 'en'],
            ]);
        });
    }

    protected function getDefaultSettings(): array
    {
        return [
            'theme' => 'light',
            'language' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d',
        ];
    }
}
```

### Pattern: Cross-Tenant Analytics (Central Admin)

```php
<?php

namespace App\Http\Controllers\Admin;

use AsasFlow\Features\Tenancy\Models\Tenant;
use AsasFlow\Features\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;

class AnalyticsController extends Controller
{
    public function dashboard()
    {
        $tenants = Tenant::all();
        $stats = [];

        foreach ($tenants as $tenant) {
            $stats[$tenant->slug] = TenantContext::runForTenant($tenant, function () {
                return [
                    'users' => \Modules\Users\Models\User::count(),
                    'orders' => \Modules\Orders\Models\Order::count(),
                    'revenue' => \Modules\Orders\Models\Order::sum('total'),
                    'last_order' => \Modules\Orders\Models\Order::latest()->first()?->created_at,
                ];
            });
        }

        return view('admin.analytics', compact('stats'));
    }
}
```

### Pattern: Tenant-Specific Feature Flags

```php
<?php

namespace App\Services;

use AsasFlow\Features\Tenancy\Services\TenantContext;

class FeatureFlagService
{
    protected array $defaultFeatures = [
        'advanced_reporting' => false,
        'api_access' => false,
        'custom_branding' => false,
        'sso' => false,
    ];

    public function isEnabled(string $feature): bool
    {
        $tenant = TenantContext::getCurrentTenant();
        
        if (!$tenant) {
            return false;
        }

        // Plan-based features
        $planFeatures = match($tenant->plan) {
            'enterprise' => ['advanced_reporting', 'api_access', 'custom_branding', 'sso'],
            'pro' => ['advanced_reporting', 'api_access'],
            default => [],
        };

        // Tenant override
        $tenantFeatures = $tenant->settings['features'] ?? [];
        
        return $tenantFeatures[$feature] 
            ?? in_array($feature, $planFeatures);
    }

    public function requireFeature(string $feature): void
    {
        if (!$this->isEnabled($feature)) {
            abort(403, "Feature '{$feature}' not available on your plan");
        }
    }
}
```

**Usage in controller:**

```php
class ReportController extends Controller
{
    public function advancedReport()
    {
        app(FeatureFlagService::class)->requireFeature('advanced_reporting');
        
        // Generate advanced report...
    }
}
```

### Pattern: Tenant-Aware File Storage

```php
<?php

namespace App\Services;

use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\Storage;

class TenantStorageService
{
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        $tenant = TenantContext::getCurrentTenant();
        
        if (!$tenant) {
            return Storage::disk('public');
        }

        // Tenant-isolated directory
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path("app/tenants/{$tenant->id}"),
            'url' => env('APP_URL') . "/storage/tenants/{$tenant->id}",
            'visibility' => 'public',
        ]);
    }

    public function store(string $path, $file): string
    {
        $disk = $this->disk();
        $disk->put($path, $file);
        
        return $disk->url($path);
    }

    public function delete(string $path): bool
    {
        return $this->disk()->delete($path);
    }
}
```

---

## Migration Guide

### Migrating from Single DB to Separate DB

```php
<?php

namespace App\Console\Commands;

use AsasFlow\Features\Tenancy\Models\Tenant;
use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateToSeparateDatabase extends Command
{
    protected $signature = 'tenant:migrate-to-separate {tenant}';

    public function handle(): void
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();
        
        // 1. Create new database
        DB::statement("CREATE DATABASE IF NOT EXISTS {$tenant->getDatabaseName()}");
        
        // 2. Run migrations on new DB
        TenantContext::runForTenant($tenant, function () {
            $this->call('migrate', [
                '--database' => 'tenant',
                '--force' => true,
            ]);
        });
        
        // 3. Copy data (custom logic per table)
        $this->migrateTable($tenant, 'users');
        $this->migrateTable($tenant, 'orders');
        $this->migrateTable($tenant, 'products');
        
        // 4. Update tenant record
        $tenant->update(['database' => $tenant->getDatabaseName()]);
        
        // 5. Clear all cache
        \AsasFlow\Features\Cache\Facades\ModuleCache::invalidateTenantCache($tenant->id);
        
        $this->info("Migration complete for {$tenant->name}");
    }

    protected function migrateTable(Tenant $tenant, string $table): void
    {
        $rows = DB::table($table)
            ->where('tenant_id', $tenant->id)
            ->get();
        
        TenantContext::runForTenant($tenant, function () use ($table, $rows) {
            foreach ($rows->chunk(1000) as $chunk) {
                DB::connection('tenant')->table($table)->insert(
                    $chunk->map(fn($row) => (array) $row)->toArray()
                );
            }
        });
        
        // Optional: Remove from central DB
        // DB::table($table)->where('tenant_id', $tenant->id)->delete();
    }
}
```

---

## Troubleshooting

### Issue: "Tenant not found" on every request

**Causes:**
1. Domain not in `asasflow_tenant_domains`
2. `is_verified = false`
3. `is_active = false`

**Debug:**
```php
// Add temporary debug to InitializeTenancy middleware
\Log::info('Resolving tenant', [
    'host' => request()->getHost(),
    'header_id' => request()->header('X-Tenant-ID'),
    'header_slug' => request()->header('X-Tenant-Slug'),
]);
```

### Issue: Database "tenant" not configured

**Fix:** Add to `config/database.php`:
```php
'tenant' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT'),
    'database' => null,  // Dynamic
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    // ...
],
```

### Issue: Cache not isolating between tenants

**Check:**
1. `ASASFLOW_CACHE_STRICT_ISOLATION=true`
2. `InitializeTenancy` middleware runs **before** cache middleware
3. Redis connection is working

### Issue: Queue jobs using wrong tenant

**Fix:** Always capture tenant in job constructor:
```php
public function __construct()
{
    $this->tenantId = TenantContext::getTenantId();
}
```

### Issue: Central admin routes hitting tenant resolution

**Fix:** Add route to central_routes config:
```php
'central_routes' => [
    // ... existing
    'api/admin/*',  // Your admin API
],
```

Or use central domain:
```env
ASASFLOW_CENTRAL_DOMAINS=admin.yourapp.com
```

### Issue: Tenant database creation fails

**Common causes:**
- MySQL user lacks `CREATE DATABASE` privilege
- Database name contains invalid characters
- Connection timeout

**Fix:**
```sql
-- Grant privileges
GRANT ALL PRIVILEGES ON `tenant_%`.* TO 'your_user'@'%';
FLUSH PRIVILEGES;
```