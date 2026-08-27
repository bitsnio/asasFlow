[ASASFLOW Cache Feature - Complete Documentation](sandbox:///mnt/agents/output/ASASFLOW_CACHE_DOCUMENTATION.md)

---

# ASASFLOW Cache Feature — Complete Documentation

## Table of Contents
1. [Overview](#overview)
2. [How Cache Invalidation Works](#how-cache-invalidation-works)
   - [The Tag-Based Invalidation Engine](#the-tag-based-invalidation-engine)
   - [With Redis (Tag-Aware Drivers)](#with-redis-tag-aware-drivers)
   - [Without Redis (Non-Tag Drivers)](#without-redis-non-tag-drivers)
3. [Configuration Reference](#configuration-reference)
4. [Feature Usage Guide](#feature-usage-guide)
5. [Advanced Patterns](#advanced-patterns)
6. [Troubleshooting](#troubleshooting)

---

## Overview

The ASASFLOW Cache is a **model-aware HTTP caching system** designed for modular Laravel microservices. It automatically caches GET responses and invalidates them when related models change — without manual cache key tracking.

### Core Philosophy

| Traditional Caching | ASASFLOW Cache |
|---------------------|----------------|
| You track cache keys manually | System tracks via **tags** |
| Invalidate by exact key | Invalidate by **model class** or **tag** |
| Redis required for tags | Works with **any Laravel cache driver** |
| Code scattered in controllers | Centralized via **attributes & observers** |

---

## How Cache Invalidation Works

### The Tag-Based Invalidation Engine

When a response is cached, it receives **multiple tags**:

```
Cache Entry: "asasflow-service|get|users|a1b2c3d4"
├── Tags:
│   ├── "route:users.index"           ← Route name
│   ├── "model:Modules_Users_Models_User"  ← Auto-detected model
│   ├── "model:Modules_Users_Models_User:42"  ← Specific record
│   └── "user-service-users"          ← Custom tag from model
```

**When a User model changes**, the observer calls:
```php
$invalidator->invalidate(User::class, 42);
```

This invalidates **ALL cache entries** tagged with:
- `model:Modules_Users_Models_User` (all user-related cache)
- `model:Modules_Users_Models_User:42` (this specific user)

---

### With Redis (Tag-Aware Drivers)

**How it works:**
```php
// Store with tags
Redis::tags(['model:User', 'model:User:42'])->put($key, $data, 300);

// Invalidate by tag — Redis deletes ALL keys with this tag
Redis::tags(['model:User:42'])->flush();
```

**Redis stores tags as a SET:**
```
redis> SMEMBERS "laravel:tag:model:User:42"
1) "asasflow-service|get|users|a1b2c3d4"
2) "asasflow-service|get|users|e5f6g7h8"

redis> DEL "asasflow-service|get|users|a1b2c3d4"
redis> DEL "asasflow-service|get|users|e5f6g7h8"
```

**Impact:** O(1) invalidation regardless of how many keys exist.

---

### Without Redis (Non-Tag Drivers)

When using **file**, **database**, or **array** drivers, Laravel doesn't support `tags()`. ASASFLOW uses a **Cache Registry** fallback:

```php
// When caching, we ALSO store in registry table:
DB::table('asasflow_cache_registry')->insert([
    'cache_key' => 'asasflow-service|get|users|a1b2c3d4',
    'tags' => json_encode(['model:User', 'model:User:42']),
    'expires_at' => now()->addMinutes(5),
]);

// When invalidating, we QUERY the registry:
$keys = DB::table('asasflow_cache_registry')
    ->whereJsonContains('tags', 'model:User:42')
    ->pluck('cache_key');

// Then delete each key individually:
foreach ($keys as $key) {
    Cache::forget($key);
}
```

**Impact:** O(n) where n = matching registry entries. Slower than Redis but works everywhere.

**Registry table auto-cleanup:** Expired entries are pruned on each invalidation.

---

## Configuration Reference

### `enabled`

```php
'enabled' => env('ASASFLOW_CACHE_ENABLED', env('CACHE_ENABLED', true)),
```

| Value | Behavior |
|-------|----------|
| `true` | Cache middleware active, observers register |
| `false` | All cache operations bypassed, no overhead |

**Example:**
```env
# Production
ASASFLOW_CACHE_ENABLED=true

# Testing/CI
ASASFLOW_CACHE_ENABLED=false
```

**Impact:** When `false`, `AutoCacheMiddleware` returns `$next($request)` immediately without checking cache.

---

### `store`

```php
'store' => env('ASASFLOW_CACHE_STORE', env('CACHE_STORE', config('cache.default', 'redis'))),
```

| Driver | Tag Support | Invalidation Method | Best For |
|--------|-------------|---------------------|----------|
| `redis` | ✅ Yes | `Redis::tags()->flush()` | Production |
| `memcached` | ✅ Yes | `Memcached::tags()->flush()` | Production |
| `file` | ❌ No | Registry table scan | Development |
| `database` | ❌ No | Registry table scan | Shared hosting |
| `array` | ❌ No | Registry table scan | Testing |
| `dynamodb` | ❌ No | Registry table scan | AWS serverless |

**Resolution order:**
1. `ASASFLOW_CACHE_STORE` env var
2. `CACHE_STORE` env var (Laravel default)
3. `config('cache.default')`
4. Fallback to `redis`

**Example:**
```env
# Use Redis cluster
ASASFLOW_CACHE_STORE=redis

# Use file for local dev
ASASFLOW_CACHE_STORE=file
```

**Impact:** Determines invalidation speed and scalability. Redis recommended for >1000 cached entries.

---

### `ttl`

```php
'ttl' => env('ASASFLOW_CACHE_TTL', 300),
```

| Scenario | Recommended TTL | Reason |
|----------|----------------|--------|
| User profiles | `3600` (1 hour) | Rarely change |
| Product catalog | `1800` (30 min) | Periodic updates |
| Analytics dashboard | `60` (1 min) | Near real-time |
| Search results | `300` (5 min) | Balance freshness/perf |

**Override per route:**
```php
#[AutoCache(ttl: 1800)]  // 30 minutes for this endpoint
public function productCatalog() { }
```

**Impact:** Higher TTL = better performance but stale data risk. Lower TTL = fresher data but more DB hits.

---

### `key_strategy`

```php
'key_strategy' => [
    'driver' => 'url_context',
    'include_query_params' => true,
    'ignore_params' => ['utm_source', 'tracking_id', '_ga'],
    'include_headers' => ['X-Tenant-ID', 'Accept-Language'],
    'include_user' => true,
],
```

#### `driver`
- `url_context` — Default, hashes URL + query + headers + user
- Future: `custom` — Use your own strategy class

#### `include_query_params`
| Value | Cache Key For | Cache Key For |
|-------|-------------|-------------|
| `true` | `/users?page=1` → `...|users|hash(page=1)` | `/users?page=2` → `...|users|hash(page=2)` |
| `false` | Both use same key | Paginated results WRONG |

**Impact:** `true` required for pagination, search, filtering.

#### `ignore_params`
Query parameters excluded from cache key hashing:

```php
// Request: /users?page=1&utm_source=google&tracking_id=abc123
// Cache key includes: page=1
// Cache key ignores: utm_source, tracking_id
```

**Why?** Marketing params change per visitor but don't affect response content.

**Impact:** Prevents cache fragmentation from tracking parameters.

#### `include_headers`
Headers that differentiate cache entries:

```php
// Request 1: X-Tenant-ID: 42 → Key: ...|x-tenant-id:42|...
// Request 2: X-Tenant-ID: 99 → Key: ...|x-tenant-id:99|...
```

**Impact:** Essential for multi-tenant apps. Same URL, different data per tenant.

#### `include_user`
| Value | Behavior |
|-------|----------|
| `true` | `Auth::id()` appended to key — per-user cache |
| `false` | Same cache for all users |

**Impact:** `true` for personalized data (dashboards, profiles). `false` for public data (products, articles).

---

### `tagging`

```php
'tagging' => [
    'enabled' => true,
    'auto_tag_models' => true,
    'service_prefix' => env('APP_NAME', 'asasflow-service'),
],
```

#### `enabled`
Master switch for tag generation. When `false`, no tags stored — invalidation by tag won't work.

#### `auto_tag_models`
When `true`, the system inspects the JSON response and auto-detects model classes:

```json
// Response contains:
{
    "data": {
        "id": 42,
        "name": "John",
        "type": "Modules\\Users\\Models\\User"
    }
}

// Auto-generated tags:
// "model:Modules_Users_Models_User"
// "model:Modules_Users_Models_User:42"
```

**Impact:** Zero-config model invalidation. Disable if you want manual tag control only.

#### `service_prefix`
Prevents cache collisions when multiple services share Redis:

```php
// Service A: "user-service|get|users|..."
// Service B: "order-service|get|orders|..."
```

**Impact:** Required for microservices sharing cache infrastructure.

---

### `stampede_protection`

```php
'stampede_protection' => [
    'enabled' => true,
    'lock_ttl' => 10,
    'stale_while_revalidate' => true,
    'stale_ttl' => 60,
],
```

#### The Thundering Herd Problem

```
T+0: Cache expires
T+0: 1000 requests arrive simultaneously
T+0: All 1000 miss cache, hit database
T+0: Database overloads
```

#### How Stampede Protection Fixes It

```
T+0: Cache expires
T+0: Request 1 acquires lock, regenerates cache (10s)
T+0: Requests 2-1000 check stale cache → serve old data instantly
T+10: Request 1 stores fresh cache, releases lock
T+10+: All requests hit fresh cache
```

#### `enabled`
| Value | Behavior |
|-------|----------|
| `true` | Lock + stale-while-revalidate active |
| `false` | Standard Laravel behavior (herd risk) |

#### `lock_ttl`
Maximum time one request can hold the regeneration lock. Prevents deadlocks if regenerator crashes.

#### `stale_while_revalidate`
| Value | Behavior on Cache Miss |
|-------|------------------------|
| `true` | Serve stale data while regenerating |
| `false` | Wait for regeneration (blocking) |

#### `stale_ttl`
How long stale data remains servable after expiration:

```php
// Cache TTL: 300s
// Stale TTL: 60s
// Total servable lifetime: 360s (but refreshed at 300s)
```

**Impact:** Higher `stale_ttl` = more resilience, more staleness.

---

### `headers`

```php
'headers' => [
    'enabled' => true,
    'etag' => true,
    'last_modified' => true,
    'cache_control' => 'public, max-age=300',
],
```

#### `enabled`
When `true`, `CacheControlMiddleware` adds HTTP headers.

#### Response Headers Added

```http
HTTP/1.1 200 OK
Cache-Control: public, max-age=300
X-Cache-Service: user-service
X-Correlation-ID: abc-123-def
ETag: "a1b2c3d4e5f6"
Last-Modified: Wed, 26 Aug 2026 12:00:00 GMT
```

#### `etag` + `cache_control` = 304 Not Modified

```php
// Client request:
GET /users/42
If-None-Match: "a1b2c3d4e5f6"

// Server: ETag matches cached response
HTTP/1.1 304 Not Modified
// Body empty — saves bandwidth
```

**Impact:** 304 responses reduce bandwidth by ~90% for unchanged resources.

---

### `bypass`

```php
'bypass' => [
    'enabled' => env('ASASFLOW_CACHE_BYPASS_ENABLED', false),
    'header' => 'X-Bypass-Cache',
    'api_key' => env('ASASFLOW_CACHE_BYPASS_KEY'),
],
```

#### Debug Scenario

```bash
# Normal request (cached)
curl https://api.example.com/users/42

# Bypass cache (debug)
curl -H "X-Bypass-Cache: dev-secret-123" \
     https://api.example.com/users/42
```

#### `enabled`
| Value | Behavior |
|-------|----------|
| `true` | Check bypass header on every request |
| `false` | Ignore bypass header (production) |

#### `api_key`
If set, header value must match. If `null`, any value bypasses.

**Impact:** Enable only in development/staging. Never in production without API key.

---

### `distributed`

```php
'distributed' => [
    'enabled' => env('ASASFLOW_CACHE_DISTRIBUTED_ENABLED', false),
    'driver' => env('ASASFLOW_CACHE_DISTRIBUTED_DRIVER', 'redis'),
    'channel' => 'asasflow-cache-invalidation',
],
```

#### Microservice Invalidation Flow

```
┌─────────────┐     User Updated      ┌─────────────┐
│  User       │ ─────────────────────►│  Order      │
│  Service    │   PUBLISH invalidate  │  Service    │
│  (Cache A)  │   model:User:42       │  (Cache B)  │
└─────────────┘                       └─────────────┘
                                              │
                                              ▼
                                       Redis Pub/Sub
                                       channel: asasflow-cache-invalidation
                                              │
                                              ▼
                                       ┌─────────────┐
                                       │  Product    │
                                       │  Service    │
                                       │  (Cache C)  │
                                       └─────────────┘
```

#### Payload Published

```json
{
    "service": "user-service",
    "type": "model",
    "model": "Modules\\Users\\Models\\User",
    "id": "42",
    "timestamp": "2026-08-26T12:00:00Z"
}
```

#### `enabled`
| Value | Behavior |
|-------|----------|
| `true` | Publish invalidation events on model changes |
| `false` | Local invalidation only |

#### `driver`
- `redis` — Uses Redis Pub/Sub (fastest)
- `rabbitmq` — Queue-based (reliable delivery)
- `kafka` — Stream-based (audit trail)

**Impact:** Essential when multiple services cache data from each other.

---

### `dashboard`

```php
'dashboard' => [
    'enabled' => env('ASASFLOW_CACHE_DASHBOARD_ENABLED', true),
    'route_prefix' => '_cache',
    'middleware' => ['web', 'auth'],
],
```

#### Accessing Dashboard

```
https://yourapp.com/_cache
```

#### Dashboard Shows

| Section | Data |
|---------|------|
| Stats | Hit ratio, total requests, entries count |
| Entries | Live list of cached keys with metadata |
| Actions | Clear cache, warm endpoints |

#### `route_prefix`
URL path for dashboard. Change if conflicting:

```php
'route_prefix' => 'admin/cache-inspector'
// Access: https://yourapp.com/admin/cache-inspector
```

#### `middleware`
Protects dashboard from public access:

```php
'middleware' => ['web', 'auth', 'can:view-cache-dashboard']
```

**Impact:** Disable in production if not needed (`enabled => false`).

---

### `telemetry`

```php
'telemetry' => [
    'enabled' => true,
    'events' => true,
],
```

#### Events Fired

| Event | When | Payload |
|-------|------|---------|
| `CacheHit` | Response served from cache | `$key`, `$tags`, `$responseTime` |
| `CacheMiss` | Cache empty, regenerating | `$key`, `$reason` |
| `CacheInvalidated` | Cache cleared | `$type`, `$target`, `$modelClass`, `$modelId` |

#### Listening to Events

```php
// app/Providers/EventServiceProvider.php
use Bitsnio\AsasFlow\Features\Cache\Events\CacheHit;

Event::listen(CacheHit::class, function ($event) {
    // Send to Prometheus
    Prometheus::counter('cache_hits_total')->inc();
    
    // Log for debugging
    Log::info("Cache hit: {$event->key}");
});
```

**Impact:** Essential for monitoring cache effectiveness in production.

---

## Feature Usage Guide

### 1. Basic Route Caching with Attributes

```php
<?php

namespace Modules\Users\Http\Controllers;

use Bitsnio\AsasFlow\Features\Cache\Attributes\AutoCache;
use Bitsnio\AsasFlow\Features\Cache\Attributes\NoCache;
use App\Http\Controllers\Controller;
use Modules\Users\Models\User;

class UserController extends Controller
{
    #[AutoCache(ttl: 300)]  // Cache for 5 minutes
    public function index()
    {
        return User::paginate(20);
    }

    #[AutoCache(ttl: 600, tags: 'user-profiles')]  // Custom tag
    public function show(User $user)
    {
        return $user->load('department', 'roles');
    }

    #[NoCache(reason: 'Real-time data')]  // Never cache
    public function onlineStatus()
    {
        return ['count' => User::where('last_seen', '>', now()->subMinutes(5))->count()];
    }
}
```

**What happens:**
- `index()` — First request hits DB, stores response. Next 300s requests serve from cache.
- `show()` — Cached per-user for 10 minutes. Custom tag `user-profiles` allows bulk invalidation.
- `onlineStatus()` — Always fresh, no cache overhead.

---

### 2. Route-Level Middleware Control

```php
// routes/api.php
use Illuminate\Support\Facades\Route;

Route::middleware(['asasflow.cache', 'asasflow.cache.control'])->group(function () {
    
    // Uses default TTL from config
    Route::get('/users', [UserController::class, 'index']);
    
    // Override TTL via middleware param
    Route::get('/users/search', [UserController::class, 'search'])
        ->middleware('asasflow.cache:600');  // 10 minutes
    
    // Custom tags for bulk invalidation
    Route::get('/reports/sales', [ReportController::class, 'sales'])
        ->middleware('asasflow.cache:1800,sales-reports,daily-metrics');
});
```

**Middleware syntax:**
```php
'asasflow.cache:{ttl},{tag1},{tag2},...'
```

---

### 3. Model-Aware Invalidation

```php
<?php

namespace Modules\Users\Models;

use Bitsnio\AsasFlow\Features\Cache\Traits\CacheAware;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use CacheAware;

    protected array $cacheTags = [
        'user-service-users',
        'user-service-profiles',
    ];

    protected array $cacheInvalidationRelations = [
        'department' => ['on_update' => true, 'on_delete' => false],
        'roles' => ['on_update' => true, 'on_delete' => true],
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
```

**Invalidation triggers:**

| Action | Invalidated Tags |
|--------|----------------|
| User created | `model:User`, `user-service-users`, `user-service-profiles` |
| User updated | Above + `model:User:42` + `model:Department:5` (if dept changed) |
| User deleted | All above + `model:Role:1`, `model:Role:2` (all roles) |
| Role detached | `model:Role:3`, `model:User:42` |

---

### 4. Manual Cache Operations

```php
use Bitsnio\AsasFlow\Features\Cache\Facades\MicroCache;

// Invalidate by model class (all user cache)
MicroCache::invalidateByModel(User::class);

// Invalidate specific record
MicroCache::invalidateByModel(User::class, '42');

// Invalidate by custom tag
MicroCache::invalidateByTag('sales-reports');

// Multiple tags
MicroCache::invalidateByTags(['sales-reports', 'daily-metrics']);

// Flush everything
MicroCache::flush();

// Get statistics
$stats = MicroCache::getStats();
// ['hits' => 1500, 'misses' => 300, 'hit_ratio' => 83.33, ...]
```

---

### 5. Repository Pattern Integration

```php
<?php

namespace Modules\Products\Repositories;

use Bitsnio\AsasFlow\Features\Cache\Facades\MicroCache;
use Modules\Products\Models\Product;

class ProductRepository
{
    public function find(int $id): ?Product
    {
        return MicroCache::remember(
            request(),  // Current request for key generation
            fn() => Product::find($id),
            3600,
            ['model:Product', "model:Product:{$id}"]
        );
    }

    public function create(array $data): Product
    {
        $product = Product::create($data);
        
        // Manual invalidation (observer handles auto, but explicit is clearer)
        MicroCache::invalidateByTag('product-service-listings');
        
        return $product;
    }
}
```

---

### 6. CLI Commands

```bash
# View cache statistics
php artisan asasflow:cache:stats

# View with cached entries list
php artisan asasflow:cache:stats --entries

# Clear all cache
php artisan asasflow:cache:clear --all

# Clear by model
php artisan asasflow:cache:clear --model="Modules\Users\Models\User"

# Clear by tag
php artisan asasflow:cache:clear --tag=user-service-users

# Warm endpoint
php artisan asasflow:cache:warm https://api.example.com/users --times=5
```

---

## Advanced Patterns

### Pattern: Conditional Cache Invalidation

```php
class OrderService
{
    public function updateStatus(Order $order, string $status): void
    {
        $oldStatus = $order->status;
        $order->update(['status' => $status]);

        // Only invalidate relevant caches
        if ($oldStatus !== $status) {
            if ($status === 'shipped') {
                MicroCache::invalidateByTag('orders:pending');
                MicroCache::invalidateByTag('orders:shipped');
            }
            
            if ($status === 'delivered') {
                MicroCache::invalidateByTag('orders:shipped');
                MicroCache::invalidateByTag('reports:revenue');
            }
        }
    }
}
```

### Pattern: Multi-Tenant Cache Isolation

```php
// config/asasflow-cache.php
'key_strategy' => [
    'include_headers' => ['X-Tenant-ID'],
]

// Request from Tenant 42:
// Key: asasflow-service|get|users|x-tenant-id:42|...

// Request from Tenant 99:
// Key: asasflow-service|get|users|x-tenant-id:99|...
```

### Pattern: Cache Warming on Deploy

```php
// routes/console.php
use Illuminate\Support\Facades\Artisan;

Artisan::command('cache:warm-all', function () {
    $endpoints = [
        'https://api.example.com/products',
        'https://api.example.com/categories',
        'https://api.example.com/settings',
    ];
    
    foreach ($endpoints as $endpoint) {
        $this->call('asasflow:cache:warm', ['endpoint' => $endpoint, '--times' => 1]);
    }
})->daily();
```

---

## Troubleshooting

### Cache not invalidating on model change

**Checklist:**
1. Model uses `CacheAware` trait?
2. Observer file exists in `Modules/{Module}/Observers/`?
3. `asasflow-cache.enabled` is `true`?
4. For non-Redis: `asasflow_cache_registry` table exists?

### High memory usage with file driver

**Cause:** File driver stores each key as a separate file. Registry table adds overhead.

**Fix:** Switch to Redis or add cleanup:
```bash
php artisan cache:prune-stale
```

### 304 Not Modified not working

**Cause:** Client not sending `If-None-Match` header.

**Fix:** Ensure frontend sends ETag from previous response:
```javascript
fetch('/users/42', {
    headers: {
        'If-None-Match': localStorage.getItem('etag-users-42')
    }
});
```

---

## Quick Reference Card

| Want To... | Do This |
|------------|---------|
| Cache a route | Add `#[AutoCache]` attribute |
| Skip caching | Add `#[NoCache]` attribute |
| Change TTL | `#[AutoCache(ttl: 600)]` |
| Add custom tags | `#[AutoCache(tags: 'my-tag')]` |
| Invalidate manually | `MicroCache::invalidateByModel(User::class)` |
| Clear everything | `php artisan asasflow:cache:clear --all` |
| Check stats | `php artisan asasflow:cache:stats` |
| Bypass cache | `curl -H "X-Bypass-Cache: key"` |
| Multi-tenant keys | Add `X-Tenant-ID` to `include_headers` |
| Distributed invalidation | Enable `distributed.enabled` |