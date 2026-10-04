# Laravel Project Context

**Task:** available Docs

## Background and Purpose

building a package in laravel which provides modular approach of code generated through laravel-module package. this package generate basic strucure along with menu.php file which contain full information based on what routes and controller are generated. i have designe the package in a way that each function is created as feature.
---

## Directory Structure

```
docs
docs/1.0
docs/1.0/features
docs/1.0/getting_started
docs/1.0/how_to_use
```

---

## Project Files

### docs/1.0/features/cache.md

```markdown
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
```

### docs/1.0/features/settings.md

```markdown
# Module Settings Documentation

## Overview

The Module Settings system provides a centralized configuration management solution for your Laravel application. It allows modules to define, retrieve, override, and update settings with support for three hierarchical scopes:

- **Module** - Global settings shared across the entire application
- **Company** - Settings specific to individual companies
- **Site** - Settings specific to individual sites within a company

Settings are automatically discovered, cached, and made available through both a PHP facade and REST API.

---

## Table of Contents

1. [Defining Settings](#defining-settings)
2. [Setting Scopes](#setting-scopes)
3. [Value Resolution](#value-resolution)
4. [PHP Usage](#php-usage)
5. [API Usage](#api-usage)
6. [Frontend Integration](#frontend-integration)
7. [Caching](#caching)
8. [Complete Examples](#complete-examples)

---

## Defining Settings

### File Structure

Each module defines its settings in:

```
Modules/{ModuleName}/config/settings.php
```

### Simple Definitions

The simplest form uses a key-value pair:

```php
<?php

return [
    'inventory_enabled' => true,
    'low_stock_threshold' => 10,
    'default_currency' => 'USD',
];
```

**Note:** Simple definitions default to `scope: module`.

### Advanced Definitions

For full control, define settings as arrays:

```php
<?php

return [
    'costing_method' => [
        'label' => 'Costing Method',
        'description' => 'Select the inventory costing method.',
        'type' => 'string',
        'input' => 'select',
        'default' => 'fifo',
        'scope' => 'company',
        'options' => [
            ['label' => 'FIFO', 'value' => 'fifo'],
            ['label' => 'LIFO', 'value' => 'lifo'],
            ['label' => 'Weighted Average', 'value' => 'weighted_average'],
        ],
        'rules' => ['required', 'in:fifo,lifo,weighted_average'],
    ],
];
```

### Available Properties

| Property | Type | Description |
|----------|------|-------------|
| `label` | string | Human-readable setting name |
| `description` | string | Additional context or help text |
| `type` | string | Data type (string, boolean, integer, etc.) |
| `input` | string | Suggested frontend input component |
| `default` | mixed | Default value |
| `options` | array | Options for select-based inputs |
| `rules` | array | Validation rules |
| `scope` | string | `module`, `company`, or `site` |

### Default Values

When omitted, these defaults apply:

```php
[
    'label' => 'setting_key',
    'description' => null,
    'type' => null,
    'input' => null,
    'default' => null,
    'options' => [],
    'rules' => [],
    'scope' => 'module',
]
```

---

## Setting Scopes

### Module Scope

Settings at this level have one value for the entire application.

**Definition:**

```php
'maintenance_mode' => [
    'default' => false,
    'scope' => 'module',
],
```

**Update:**

```php
ModuleSettings::update('admin', ['maintenance_mode' => true]);
```

### Company Scope

Settings at this level can differ per company.

**Definition:**

```php
'default_currency' => [
    'default' => 'USD',
    'scope' => 'company',
],
```

**Update:**

```php
ModuleSettings::update('inventory', ['default_currency' => 'PKR'], $companyId);
```

### Site Scope

Settings at this level can differ per site within a company.

**Definition:**

```php
'low_stock_threshold' => [
    'default' => 10,
    'scope' => 'site',
],
```

**Update:**

```php
ModuleSettings::update('inventory', ['low_stock_threshold' => 25], $companyId, $siteId);
```

---

## Value Resolution

Settings inherit values from broader scopes when specific overrides don't exist.

### Module-Level Resolution

```
Module DB Value → Default Value
```

### Company-Level Resolution

```
Company Override → Module Override → Default Value
```

### Site-Level Resolution

```
Site Override → Company Override → Module Override → Default Value
```

### Example

Given these definitions:

```php
'default_currency' => [
    'default' => 'USD',
    'scope' => 'company',
],
```

**Scenario 1:** Company 1 has override 'PKR'
```
Result for Company 1: 'PKR'
Result for Company 2: 'USD' (fallback)
```

**Scenario 2:** Site 1 has override 'EUR'
```
Result for Site 1: 'EUR'
Result for Site 2: 'USD' (fallback)
```

---

## PHP Usage

### Facade Import

```php
use Bitsnio\AsasFlow\Features\Settings\Facades\ModuleSettings;
```

### Get a Single Setting

```php
$value = ModuleSettings::get('inventory', 'inventory_enabled');

// With fallback
$value = ModuleSettings::get('inventory', 'unknown_setting', false);

// Company-level
$currency = ModuleSettings::get('inventory', 'default_currency', null, $companyId);

// Site-level
$threshold = ModuleSettings::get('inventory', 'low_stock_threshold', null, $companyId, $siteId);
```

### Get All Settings

```php
// All settings for a module
$settings = ModuleSettings::all('inventory');

// All settings for a company
$settings = ModuleSettings::all('inventory', $companyId);

// All settings for a site
$settings = ModuleSettings::all('inventory', $companyId, $siteId);
```

### Update Settings

```php
// Module-level
ModuleSettings::update('admin', ['maintenance_mode' => true]);

// Company-level
ModuleSettings::update('inventory', ['default_currency' => 'PKR'], $companyId);

// Site-level
ModuleSettings::update('inventory', ['low_stock_threshold' => 25], $companyId, $siteId);

// Multiple settings
ModuleSettings::update('inventory', [
    'default_currency' => 'PKR',
    'allow_negative_stock' => true,
], $companyId);
```

### Get Settings Schema

Retrieve definitions with current values for frontend use:

```php
// Module-level schema
$schema = ModuleSettings::schema('inventory');

// Company-level schema
$schema = ModuleSettings::schema('inventory', $companyId);

// Site-level schema
$schema = ModuleSettings::schema('inventory', $companyId, $siteId);
```

### Cross-Module Access

Any module can access another module's settings:

```php
// Admin module accessing Inventory settings
$inventoryEnabled = ModuleSettings::get('inventory', 'inventory_enabled');

// Inventory module accessing Admin settings
$maintenanceMode = ModuleSettings::get('admin', 'maintenance_mode');
```

### Important Notes

**Method Signatures:**

```php
ModuleSettings::get(
    string $module,
    string $key,
    mixed $default = null,
    ?int $companyId = null,
    ?int $siteId = null
);

ModuleSettings::all(
    string $module,
    ?int $companyId = null,
    ?int $siteId = null
);

ModuleSettings::update(
    string $module,
    array $values,
    ?int $companyId = null,
    ?int $siteId = null
);
```

**Third Parameter Quirk:** For `get()`, the third parameter is the default value. Use `null` when passing company/site IDs:

```php
// ✅ Correct
$currency = ModuleSettings::get('inventory', 'default_currency', null, $companyId);

// ❌ Wrong - this interprets $companyId as the default
$currency = ModuleSettings::get('inventory', 'default_currency', $companyId);
```

**Scope Validation:** Settings must be updated with the correct scope. This will throw an exception:

```php
// ❌ Maintenance mode is module-level
ModuleSettings::update('admin', ['maintenance_mode' => true], $companyId);
```

---

## API Usage

### Authentication

All endpoints are protected with `auth:api`.

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/settings` | List all modules with settings |
| GET | `/settings/{module}` | Get schema and values for a module |
| PUT | `/settings/{module}` | Update settings for a module |

### Get All Modules

```http
GET /api/settings
```

**Response:**

```json
{
    "modules": ["inventory", "admin", "sales"]
}
```

### Get Module Settings Schema

```http
GET /api/settings/inventory
```

**Response:**

```json
{
    "module": "inventory",
    "settings": {
        "inventory_enabled": {
            "label": "Enable Inventory",
            "description": null,
            "type": "boolean",
            "input": "switch",
            "default": true,
            "options": [],
            "rules": [],
            "scope": "module",
            "key": "inventory_enabled",
            "value": true
        },
        "default_currency": {
            "label": "Default Currency",
            "type": "string",
            "input": "select",
            "default": "USD",
            "scope": "company",
            "options": [
                {"label": "US Dollar", "value": "USD"},
                {"label": "Pakistani Rupee", "value": "PKR"}
            ],
            "rules": [],
            "key": "default_currency",
            "value": "USD"
        }
    }
}
```

### Update Settings

**Module-level update:**

```http
PUT /api/settings/admin
```

```json
{
    "settings": {
        "maintenance_mode": true
    }
}
```

**Company-level update:**

```http
PUT /api/settings/inventory
```

```json
{
    "settings": {
        "default_currency": "PKR"
    },
    "company_id": 1
}
```

**Site-level update:**

```http
PUT /api/settings/inventory
```

```json
{
    "settings": {
        "low_stock_threshold": 25
    },
    "company_id": 1,
    "site_id": 5
}
```

**Response:**

```json
{
    "message": "Settings updated successfully.",
    "module": "inventory",
    "settings": {
        "inventory_enabled": true,
        "default_currency": "PKR",
        "low_stock_threshold": 10
    }
}
```

### Error Responses

**Invalid Setting:**

```json
{
    "message": "Unknown setting [inventory.invalid_setting]."
}
```

**Scope Mismatch:**

```json
{
    "message": "Setting [maintenance_mode] is module-level and cannot be overridden per company/site."
}
```

---

## Frontend Integration

### Recommended Workflow

```
1. GET /settings/{module} → Receive schema + current values
2. Build settings form dynamically from schema
3. User modifies values
4. PUT /settings/{module} → Update settings
5. Receive updated resolved values
```

### Angular Example

```typescript
import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

@Injectable({
    providedIn: 'root'
})
export class SettingsService {
    constructor(private http: HttpClient) {}

    getSettings(module: string): Observable<any> {
        return this.http.get(`/api/settings/${module}`);
    }

    updateSettings(
        module: string,
        settings: Record<string, any>,
        companyId?: number,
        siteId?: number
    ): Observable<any> {
        return this.http.put(`/api/settings/${module}`, {
            settings,
            company_id: companyId,
            site_id: siteId
        });
    }
}
```

**Usage:**

```typescript
// Load settings
this.settingsService.getSettings('inventory').subscribe(response => {
    this.schema = response.settings;
});

// Update settings
this.settingsService.updateSettings('inventory', {
    default_currency: 'PKR'
}, companyId).subscribe(response => {
    console.log('Settings updated:', response);
});
```

### Dynamic Form Rendering

```typescript
renderSetting(key: string, setting: any): string {
    switch (setting.input) {
        case 'select':
            return `
                <label>${setting.label}</label>
                <select name="${key}" value="${setting.value}">
                    ${setting.options.map(opt => `
                        <option value="${opt.value}">${opt.label}</option>
                    `).join('')}
                </select>
            `;
        case 'switch':
            return `
                <label>${setting.label}</label>
                <input type="checkbox" ${setting.value ? 'checked' : ''}>
            `;
        case 'number':
            return `
                <label>${setting.label}</label>
                <input type="number" value="${setting.value}">
            `;
        default:
            return `
                <label>${setting.label}</label>
                <input type="text" value="${setting.value}">
            `;
    }
}
```

### Input Component Mapping

| Input Value | Suggested Component |
|-------------|---------------------|
| `text` | Text input |
| `number` | Number input |
| `switch` | Toggle/Checkbox |
| `select` | Dropdown |
| `textarea` | Textarea |

---

## Caching

### How It Works

- Resolved settings are cached for **24 hours**
- Each module+scope combination has a unique cache key
- Cache is automatically cleared on update

### Cache Keys

```php
// Format
module-settings:{module}:{companyId}:{siteId}

// Examples
module-settings:inventory:global:global  // Module-level
module-settings:inventory:1:global       // Company 1
module-settings:inventory:1:5            // Site 5
```

### Clearing Cache

Cache is automatically cleared when updating settings:

```php
ModuleSettings::update('inventory', ['setting' => 'value'], $companyId);
// Cache for inventory:companyId:global is automatically cleared
```

### Manual Cache Management

```php
use Illuminate\Support\Facades\Cache;

// Clear specific cache
Cache::forget('module-settings:inventory:1:global');

// Clear all settings cache (if needed)
Cache::delete('module-settings:*');
```

---

## Complete Examples

### Full Setting Definition

```php
<?php

return [
    // Module-level setting
    'inventory_enabled' => [
        'label' => 'Enable Inventory',
        'type' => 'boolean',
        'input' => 'switch',
        'default' => true,
        'scope' => 'module',
    ],

    // Company-level setting with options
    'default_currency' => [
        'label' => 'Default Currency',
        'type' => 'string',
        'input' => 'select',
        'default' => 'USD',
        'scope' => 'company',
        'options' => [
            ['label' => 'US Dollar', 'value' => 'USD'],
            ['label' => 'Pakistani Rupee', 'value' => 'PKR'],
            ['label' => 'Euro', 'value' => 'EUR'],
        ],
        'rules' => ['required', 'in:USD,PKR,EUR'],
    ],

    // Site-level setting
    'low_stock_threshold' => [
        'label' => 'Low Stock Threshold',
        'type' => 'integer',
        'input' => 'number',
        'default' => 10,
        'scope' => 'site',
        'rules' => ['required', 'integer', 'min:0'],
    ],

    // Simple definition (scope defaults to module)
    'allow_negative_stock' => false,
];
```

### Complete Usage Example

```php
use Bitsnio\AsasFlow\Features\Settings\Facades\ModuleSettings;

class InventoryService
{
    public function checkInventory(): void
    {
        // Check if inventory is enabled
        if (!ModuleSettings::get('inventory', 'inventory_enabled')) {
            throw new \Exception('Inventory system is disabled.');
        }

        // Get company-specific currency
        $currency = ModuleSettings::get('inventory', 'default_currency', null, auth()->user()->company_id);

        // Get site-specific threshold
        $threshold = ModuleSettings::get('inventory', 'low_stock_threshold', null, 
            auth()->user()->company_id, 
            auth()->user()->site_id
        );

        // Check if negative stock is allowed (simple definition)
        $allowNegative = ModuleSettings::get('inventory', 'allow_negative_stock');
    }

    public function updateSettings(): void
    {
        // Update multiple settings
        ModuleSettings::update('inventory', [
            'default_currency' => 'PKR',
            'allow_negative_stock' => true,
        ], auth()->user()->company_id);
    }
}
```

### Adding a New Setting

1. **Update settings file:**

```php
// Modules/Inventory/config/settings.php
return [
    // ... existing settings
    
    'new_setting' => [
        'label' => 'New Setting',
        'type' => 'string',
        'input' => 'text',
        'default' => 'default value',
        'scope' => 'company',
    ],
];
```

2. **Clear config cache (if enabled):**

```bash
php artisan optimize:clear
```

3. **Start using:**

```php
$value = ModuleSettings::get('inventory', 'new_setting', null, $companyId);
```

No database migration is required for new settings.

---

## Troubleshooting

### Common Errors

**"Settings for module [x] are not registered"**
- Ensure the module has a `config/settings.php` file
- Check that the SettingsServiceProvider is registered

**"Unknown setting [module.key]"**
- Verify the setting exists in the module's settings file
- Check for typos in the setting key
- Clear config cache after adding new settings

**"Setting [key] is module-level and cannot be overridden per company/site"**
- The setting is defined with `scope: module`
- Remove `company_id` and `site_id` from the update call

**"Setting [key] requires a company scope"**
- The setting is defined with `scope: company`
- Include `company_id` in the update call

**"Setting [key] requires a site scope"**
- The setting is defined with `scope: site`
- Include both `company_id` and `site_id` in the update call

### Cache Issues

If settings appear stale:

```bash
php artisan cache:clear
```

Or clear specific cache in code:

```php
ModuleSettings::forget('inventory', $companyId, $siteId);
```
```

### docs/1.0/getting_started/controller_and_routes.md

```markdown
# ⚡ `module:make-menu_controllers` Command

This command **automatically generates** API controllers and optimized route files (`api.php`) for a module based on its `menu.php` configuration.

---

## 🛠️ Command Signature

```bash
php artisan module:make-menu_controllers {module}
```

- **{module}** — The name of the module (e.g., `Inventory`, `Users`, `Products`).

---

## 📋 What This Command Does

1. **Reads** the `menu.php` configuration file from the specified module.
2. **Generates or updates**:
   - API **Controllers** for modules, sub-modules, and actions.
   - **Route groups** based on middleware specified in the menu.
   - An optimized `Routes/api.php` with `Route::apiResources`.
3. **Avoids unnecessary regeneration** if a controller is already up-to-date.

---

## 📂 Files Affected or Created

- **Controllers**  
  Generated under:  
  ```
  Modules/{Module}/App/Http/Controllers/
  ```
  Controller names are automatically StudlyCase formatted (e.g., `UserManagementController.php`).

- **Routes**  
  Generated at:  
  ```
  Modules/{Module}/Routes/api.php
  ```

---

## 🧩 How It Works (Simplified Flow)

| Step | Action |
|:----:|:-------|
| 1 | Find the specified module. |
| 2 | Load the module’s `menu.php` structure. |
| 3 | Create or update controllers based on menu hierarchy. |
| 4 | Group API routes by shared middleware. |
| 5 | Save an optimized `api.php` route file with clean groupings. |

---

## 🧠 Important Details

- Controllers follow **PSR-4** autoloading.
- Routes are named using **kebab-case** combining module and submodule names.
- Middleware from `menu.php` is respected and applied properly.
- **Idempotent:** Only creates or updates files if needed (based on timestamps).

---

## 🏗️ Example

Given a `menu.php` like:

```php
[
    'module' => [
        'name' => 'Inventory',
        'middleware' => ['api', 'auth'],
        'sub_module' => [
            [
                'name' => 'Products',
                'middleware' => ['api', 'auth:admin'],
                'actions' => [
                    [
                        'name' => 'ProductVariants',
                        'middleware' => ['api'],
                    ]
                ]
            ]
        ]
    ]
];
```

After running:

```bash
php artisan module:make-menu_controllers Inventory
```

You will get:

- `InventoryController.php`
- `ProductsController.php`
- `ProductVariantsController.php`
- `Routes/api.php` automatically generated with properly grouped routes and middleware.

---

## ⚙️ Developer Tips

- If you modify the `menu.php`, **run this command again** to update the controllers and routes.
- Controllers are always generated with the `--api` flag for RESTful APIs.
- Use **descriptive names** in your `menu.php` to maintain clarity.

---

> ✅ **Best Practice:** Always commit your `menu.php` changes and regenerate controllers/routes before pushing code.


```

### docs/1.0/getting_started/create_menu.md

```markdown
# `MakeMenuCommand` Documentation

This command helps manage module menus by allowing developers to **add sub-modules** and **actions** dynamically through the CLI.

---

## Command Signature

```bash
php artisan module:menu-manager {module} [--add-sub-module] [--add-action]
```

### Arguments
| Argument | Description |
| :------- | :---------- |
| `module` | The name of the module where the menu is managed. |

### Options
| Option | Description |
| :----- | :----------- |
| `--add-sub-module` | Adds a new sub-module to the module's menu. |
| `--add-action` | Adds a new action to an existing sub-module. |

---

## Features

- Create sub-modules with custom title, icon, middleware, route types, and order.
- Create actions inside sub-modules similarly.
- Sorts sub-modules and actions by their **order** property.
- Automatically saves updates to the module's `Config/menu.php` file.

---

## How It Works

### 1. Add a Sub-Module

- Prompts for a **title**, **icon class**, **middleware**, and **order**.
- Automatically generates a **StudlyCase** `name` from the title.
- Validates if the sub-module already exists.
- Saves it into the `menu.php` configuration.

### 2. Add an Action

- Prompts for a **sub-module selection**.
- Then asks for **action title**, **icon**, **middleware**, and **order**.
- Automatically generates a **StudlyCase** action `name`.
- Validates if the action already exists inside the selected sub-module.

---

## Important Methods

| Method | Purpose |
| :----- | :------ |
| `addSubModule()` | Handles adding a new sub-module. |
| `addAction()` | Handles adding a new action inside a sub-module. |
| `saveMenuConfig()` | Saves the updated menu configuration into `menu.php`. |
| `askValid()` | Ensures user input is valid (non-empty, etc). |
| `askMiddleware()` | Allows adding multiple middleware entries. |
| `askOrder()` | Automatically suggests order based on existing entries. |

---

## Example Usage

### Add a Sub-Module

```bash
php artisan module:menu-manager Blog --add-sub-module
```

### Add an Action to Sub-Module

```bash
php artisan module:menu-manager Blog --add-action
```

---

## Configuration File

The menu configuration is saved inside:

```
/Modules/{ModuleName}/Config/menu.php
```

Example structure after adding:

```php
return [
    'module' => [
        'sub_module' => [
            [
                'name' => 'Posts',
                'title' => 'Posts',
                'routes_type' => 'full',
                'icon' => 'fas fa-list',
                'middleware' => ['api', 'auth'],
                'order' => 1,
                'actions' => [
                    [
                        'name' => 'CreatePost',
                        'title' => 'Create Post',
                        'routes_type' => 'single',
                        'icon' => 'fas fa-plus',
                        'middleware' => ['api', 'auth'],
                        'order' => 1,
                    ],
                ],
            ],
        ],
    ],
];
```

---

## Requirements

- Laravel Framework
- Bitsnio `RepositoryInterface` module system

---

Would you also like me to generate a **ready-to-use README.md** file for this? 🚀  
I can even format it for GitHub if you want! 📄✨
```

### docs/1.0/getting_started/create_module.md

```markdown
Here’s an improved, more professional and clean version of your documentation in `.md` format:

---

# 📦 Creating New Modules

This guide explains how to create and manage modules within our modular application framework.

---

## 🚀 Module Creation Command

Use the following Artisan command to create a new module:

```bash
php artisan module:make [ModuleName]
```

> Replace `[ModuleName]` with your desired module name (e.g., `Inventory`, `Users`, `Products`).  
> **Note:** Module names must follow PascalCase formatting.

---

## 🏗️ Generated Directory Structure

After creation, the following structure is automatically generated:

```
Modules/
└── YourModule/
    ├── App/
    │   ├── Http/
    │   │   └── Controllers/
    │   └── Providers/
    ├── config/
    │   ├── config.php
    │   └── menu.php
    ├── Database/
    │   └── Seeders/
    ├── resources/
    │   ├── assets/
    │   │   ├── js/
    │   │   └── sass/
    │   └── views/
    ├── routes/
    ├── composer.json
    ├── module.json
    ├── package.json
    └── vite.config.js
```

---

## 📋 Understanding the `menu.php` File

The `menu.php` file in the `config` directory defines:

- **Navigation structure**
- **Route auto-generation**
- **Permission requirements**
- **Controller bindings**

### Example `menu.php` Structure

```php
<?php

return [
    'module' => [
        'name' => 'Inventory',
        'title' => 'Inventory',
        'icon' => 'fas fa-cube',
        'order' => 1,
        'routes_type' => '',
        'sub_module' => [
            [
                'name' => 'Inventory',
                'title' => 'Inventory',
                'routes_type' => 'full',
                'icon' => 'fas fa-list',
                'middleware' => ['api', 'auth'],
                'order' => 1,
                'actions' => [
                    [
                        'name' => 'Inventory',
                        'title' => 'Inventory',
                        'routes_type' => 'single',
                        'icon' => 'fas fa-list',
                        'middleware' => ['api', 'auth'],
                        'order' => 1,
                    ],
                ],
            ],
        ],
    ],
];
```

---

## 🔥 What to Do After Module Creation

1. Configure navigation in `config/menu.php`.
2. Create database migrations (schema-based).
3. Build controllers and APIs according to the menu structure.
4. Set up permissions in the database.
5. Customize module settings via `config.php`.

---

## 🛠️ Module Management Commands

### Enable/Disable Modules

```bash
# Enable a module
php artisan module:enable YourModule

# Disable a module
php artisan module:disable YourModule
```

### Additional Commands

```bash
# List all available modules
php artisan module:list

# Create a controller inside a module
php artisan module:make-controller ControllerName YourModule

# Create a model inside a module
php artisan module:make-model ModelName YourModule

# Create a migration inside a module
php artisan module:make-migration create_table_name YourModule
```

---

## 🧠 Best Practices

- **Single Responsibility**: Each module should represent a distinct feature or domain.
- **Use `menu.php` as the Source of Truth** for navigation, permissions, and routes.
- **Permissions Convention**: Follow the pattern `modulename.resource.action`.
- **Seeders**: Create meaningful seeders for initial module data.
- **Document Everything**: Maintain a `README.md` inside each module explaining its functionality and usage.

---

> ✅ **Tip:** Well-structured modules make scaling and maintenance easier!
```

### docs/1.0/getting_started/permissions.md

```markdown
# 📜 **PermissionService Documentation**

## Overview
`PermissionService` is responsible for dynamically creating roles and managing permissions for users based on menu structure, using the `spatie/laravel-permission` package.

It offers:
- Role creation with permissions.
- Granular permission control at module, sub-module, and action levels.
- Role assignment to users.
- Permission updates and retrieval for roles.

---

## 🔥 Key Features

| Feature                          | Description |
|----------------------------------|-------------|
| **defineRoleWithPermissions**    | Create or update a role and assign permissions. |
| **assignRoleToUsers**             | Assign existing roles to users. |
| **getRolePermissions**            | Fetch all permissions assigned to a role. |
| **updateRolePermissions**         | Update permissions of an existing role. |

---

## ⚙️ How to Define a Role with Permissions

### Method
```php
defineRoleWithPermissions(array $config): array
```

### Config Format
```php
[
    'name' => 'RoleName', // (Required)
    'description' => 'Role description', // (Optional)
    'modules' => ['Module1', 'Module2'], // Grant ALL permissions for these modules
    'granular_modules' => [  // Grant SPECIFIC permissions
        'ModuleName' => [
            'permissions' => ['view', 'edit'],
            'sub_modules' => [
                'SubModuleName' => [
                    'permissions' => ['view'],
                    'actions' => [
                        'ActionName' => ['create']
                    ]
                ]
            ]
        ]
    ]
]
```

### Example
```php
$permissionService->defineRoleWithPermissions([
    'name' => 'Manager',
    'description' => 'Manages department modules',
    'modules' => ['HR', 'Finance'],
    'granular_modules' => [
        'Projects' => [
            'permissions' => ['view'],
            'sub_modules' => [
                'Tasks' => [
                    'permissions' => ['view', 'update'],
                    'actions' => [
                        'Assign' => ['create', 'delete']
                    ]
                ]
            ]
        ]
    ]
]);
```

---

## 🧩 Permission Sources

Permissions are collected from:
- Module Level
- Sub-Module Level
- Action Level  
(*depending on the structure provided by `MenuService`*)

---

## 👤 Assigning Roles to Users

### Method
```php
assignRoleToUsers($roleNames, $userIds, bool $replaceExisting = true): array
```

- `roleNames`: Role name(s) to assign (string or array).
- `userIds`: User ID(s) (int or array).
- `replaceExisting`: If true, replaces old roles; else adds to them.

### Example
```php
$permissionService->assignRoleToUsers('Manager', [1, 2, 3]);
```

---

## 📋 Fetch Permissions of a Role

### Method
```php
getRolePermissions(string $roleName): Collection
```

Returns all permissions associated with the given role.

---

## 🛠 Updating Role Permissions

### Method
```php
updateRolePermissions(string $roleName, array $config): array
```
- Updates the given role’s permissions based on new config.

---

## 🛡️ Validation

- Role **name** must be provided.
- Must define at least one of `modules` or `granular_modules`.
- Missing permissions are automatically created if they don't exist.

---

## 📚 Internal Helpers (Key Functions)
- `processModulesWithAllPermissions`
- `processGranularModules`
- `handleModuleLevel`
- `handleSubModules`
- `handleSubModuleLevel`
- `handleActions`
- `filterPermissions`
- `ensurePermissionsExist`
- `validateRoleConfig`

---

# 📈 Flowchart Available!
You can visualize how permissions flow from modules → submodules → actions.

(Want me to draw you a **second, updated flowchart** for this? 🚀)

---

Would you also like a **ready-to-copy Postman collection** to test all these endpoints? 🚀  
Or maybe a **Markdown + HTML format** version too? 📚
```

### docs/1.0/how_to_use/filters_and_pagination.md

```markdown
# Laravel FilterScope Documentation

## Overview

The `TenantScopes` trait provides dynamic filtering, sorting, and pagination capabilities for Laravel Eloquent models. It now also includes **automatic filter column metadata generation** for paginated responses.

It offers:

* Dynamic filtering with column security validation
* Automatic pagination and sorting
* Relationship and JSON column filtering support
* Filterable columns metadata for front-end usage
* Unified request handling for complex queries

---

## Key Features

* **Dynamic Filtering** - Filter by any column with various operators
* **Automatic Sorting** - Request-based sorting with validation
* **Security First** - Only allows filtering on authorized columns
* **Relationship Support** - Filter across related models
* **JSON Column Support** - Nested JSON keys can be filtered using dot notation
* **Automatic Filter Metadata** - Filters included in `meta.filters` for paginated responses
* **Customizable Labels** - Override default labels for user-friendly names
* **Pagination Ready** - Built-in pagination with meta data
* **Flexible Operators** - Equals, contains, in, between, null checks, and more
* **Caching** - Filter metadata cached for 24 hours to reduce DB queries

---

## Controller Usage Examples

### 1. Basic Usage (Recommended)

```php
public function index()
{
    $transactions = MtRpMg::with(['company', 'site', 'creator'])
        ->getPaginated(); // Handles filters, sorting, pagination, and generates filter columns

    return $this->paginatedSuccessResponse(
        MtRPmgtPanelResource::collection($transactions)
    );
}
```

**Features:**

* ✅ Automatic pagination
* ✅ Dynamic filtering from request
* ✅ Automatic sorting from request
* ✅ Column security validation
* ✅ JSON & relationship filters
* ✅ `meta.filters` auto-generated

---

### 2. Model Configuration for Filters

**Example model with JSON & relationships:**

```php
use App\Traits\HasFilterColumns;

class MtRpMg extends Model
{
    use HasFilterColumns;

    protected $fillable = [
        'receiver_address', 'receiver_personal_details', 'mto_name', 'site_id'
    ];

    // Optional JSON schema for nested columns
    protected array $jsonSchema = [
        'receiver_address' => ['street', 'city', 'zip'],
        'receiver_personal_details' => ['birthCountryCode', 'citizenshipCountryCode'],
    ];

    // Exclude columns from filters
    protected array $excludeFilterColumns = ['mto_name'];

    // Override labels for user-friendly names
    protected array $labelOverrides = [
        'receiver_address.street' => 'Receiver Street',
        'receiver_personal_details.birthCountryCode' => 'Birth Country',
        'site.name' => 'Agent Site Name',
    ];

    public function site()
    {
        return $this->belongsTo(Sites::class, 'site_id');
    }
}
```

> The `meta.filters` will automatically include:
>
> * Normal fillable columns
> * JSON columns expanded as dot notation paths
> * Relationship columns expanded as `relation.column`

---

### 3. Paginated Response Example

```json
{
  "status": true,
  "message": "Success",
  "data": [ ...paginated items... ],
  "meta": {
    "model": "MtRpMg",
    "table": "money_transfers",
    "filters": [
      { "label": "Receiver Street", "value": "receiver_address.street" },
      { "label": "Birth Country", "value": "receiver_personal_details.birthCountryCode" },
      { "label": "Agent Site Name", "value": "site.name" }
    ],
    "total": 120,
    "per_page": 20,
    "current_page": 1,
    "last_page": 6,
    "from": 1,
    "to": 20,
    "has_more_pages": true
  }
}
```

---

### 4. Frontend Usage

```javascript
// Request filters dynamically
const params = {
    page: 1,
    per_page: 25,
    sort_by: 'created_at',
    sort_order: 'desc',
    filters: JSON.stringify([
        { status: ["approved", "pending"], operator: "in" },
        { "receiver_address.street": "Main", operator: "contains" },
        { "site.name": "NY Branch", operator: "=" }
    ])
};

axios.get('/api/transactions', { params })
     .then(res => console.log(res.data));
```

---

## ⚡ Customizing Filters

* **Exclude columns**:

```php
protected array $excludeFilterColumns = ['internal_notes', 'deleted_at'];
```

* **Rename labels for clarity**:

```php
protected array $labelOverrides = [
    'receiver_address.street' => 'Street Address',
    'site.name' => 'Branch Name',
];
```

* **Provide JSON schema manually**:

```php
protected array $jsonSchema = [
    'receiver_address' => ['street', 'city', 'zip']
];
```

---

## 🛠️ Cache Management & Way Forward

### Current Caching

* Filters are cached per model for **24 hours**.
* Cache key format: `filters:{ModelClass}`

### Recommended Cache Invalidation Strategies

1. **Automatic on Model Change**

   * Listen to `Schema::table` changes or run an Artisan command after migration:

   ```bash
   php artisan cache:forget filters:App\\Models\\MtRpMg
   ```
2. **Manual Cache Reset**

   * If a new column is added or JSON structure changes:

   ```php
   Cache::forget('filters:' . MtRpMg::class);
   ```
3. **Dynamic Cache Expiry**

   * Optional: shorten TTL to a few hours in dev environments to reflect changes quickly.

> Future Improvement: Build a **model observer** that clears cache on `saved`/`updated` events if JSON schema or fillable columns change.

---

## 🔒 Security Features

* ✅ Column validation against fillable fields
* ✅ Relationship validation
* ✅ JSON path validation using dot notation
* ✅ SQL injection protection through Eloquent
* ✅ Type safety for filters

---

## Summary

* Filters metadata (`meta.filters`) is now **automatic and cached**
* Supports **JSON columns**, **relationships**, and **label overrides**
* **Dot notation** used for nested JSON and related models
* Cache can be invalidated manually or via future observer integration
```

### docs/1.0/index.md

```markdown
- ## Introduction
    - [Overview](/{{route}}/{{version}}/overview)
    - [Installation](/{{route}}/{{version}}/installation)
    
- ## Getting Strted
    - [Create Module](/{{route}}/{{version}}/getting_started/create_module)
    - [Generate Menu](/{{route}}/{{version}}/getting_started/create_menu)
    - [Generate Controllers & Routes](/{{route}}/{{version}}/getting_started/controller_and_routes)
    - [Create Permissions](/{{route}}/{{version}}/getting_started/permissions)
- ## Features
    - [Settings](/{{route}}/{{version}}/features/settings)
    - [Cache](/{{route}}/{{version}}/features/cache)
- ## How To Use
    - [Filters And Pagination](/{{route}}/{{version}}/how_to_use/filters_and_pagination)
    
```

### docs/1.0/installation.md

```markdown
# Laravel Project Installation Guide

This guide will walk you through the process of cloning and installing an existing Laravel project using Composer.

## Prerequisites

Before you begin, ensure you have the following installed on your system:

- PHP (8.1 or higher recommended)
- Composer (2.0+ recommended)
- Git
- MySQL, PostgreSQL, or SQLite
- Node.js and NPM (for frontend assets)
- Server requirements:
  - BCMath PHP Extension
  - Ctype PHP Extension
  - Fileinfo PHP Extension
  - JSON PHP Extension
  - Mbstring PHP Extension
  - OpenSSL PHP Extension
  - PDO PHP Extension
  - Tokenizer PHP Extension
  - XML PHP Extension

## Installation Steps

### 1. Clone the Repository

```bash
git clone https://github.com/username/project-name.git
cd project-name
```

### 2. Install Composer Dependencies

```bash
composer install
```

### 3. Create Environment File

Copy the example environment file and generate an application key:

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Environment Variables

Open the `.env` file in your text editor and configure your database connection:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
```

### 5. Run Database Migrations

```bash
php artisan migrate
```

If the project includes seed data, you can run:

```bash
php artisan db:seed
```

### 6. Install Frontend Dependencies (if applicable)

```bash
npm install
npm run dev
```

For production:

```bash
npm run build
```

### 7. Create Storage Link (if needed)

```bash
php artisan storage:link
```

### 8. Set Directory Permissions

```bash
chmod -R 775 storage bootstrap/cache
```

### 9. Clear Configuration Cache

```bash
php artisan config:clear
php artisan cache:clear
```

### 10. Serve the Application

For local development:

```bash
php artisan serve
```

This will start a development server at `http://localhost:8000`.

## Troubleshooting

### Common Issues

1. **Composer Memory Limit**
   
   If Composer runs out of memory:
   ```bash
   COMPOSER_MEMORY_LIMIT=-1 composer install
   ```

2. **Permission Denied Errors**
   
   If you encounter permission issues:
   ```bash
   sudo chown -R $USER:www-data storage
   sudo chown -R $USER:www-data bootstrap/cache
   ```

3. **Database Connection Issues**
   
   Verify your database credentials and ensure the database exists.

4. **Missing Extensions**
   
   If PHP extensions are missing, install them using your system's package manager.

## Next Steps

After installation, you should:

1. Review the project documentation
2. Set up your IDE/editor
3. Configure your local development environment
4. Learn about the project structure and architecture

For more information, refer to the [Laravel documentation](https://laravel.com/docs).
```

### docs/1.0/overview.md

```markdown
# Overview

---

- [First Section](#section-1)

<a name="section-1"></a>
## First Section

# Introduction

Welcome to the documentation for our Modular Application Framework. This framework provides a robust foundation for developing modular Laravel applications with integrated role-based permissions management.

## What is the Modular App Framework?

The Modular App Framework is a Laravel-based solution designed to accelerate the development of complex, modular applications. Built on a forked version of Laravel Modules and integrated with Spatie Permissions, this framework provides a solid architecture that allows developers to:

- Create independent, self-contained modules
- Manage module-specific permissions and roles
- Generate standardized controllers and API routes
- Define data structures using JSON Schema

Our framework eliminates the repetitive tasks typically associated with setting up modular applications, allowing you to focus on building your application's unique functionality.

## Core Features

### Modular Architecture

The framework uses a modified version of Laravel Modules to organize your application into discrete, reusable modules. Each module functions as a mini-application that can be enabled, disabled, or transferred between projects.

### Role-Based Access Control

Built on Spatie Permissions, our framework provides a comprehensive role and permission system that operates at the module level. This allows you to:

- Define granular permissions for each module
- Create role hierarchies specific to modules
- Assign users different roles across different modules
- Control access to features with precision

### Menu-Driven Development

One of the unique aspects of our framework is the menu-driven approach to application structure. Each module contains a `menu.php` file that serves as the central configuration point. From this file, the framework:

- Automatically generates appropriate routes
- Creates controller scaffolding
- Establishes necessary API endpoints
- Configures permission requirements

### Schema-Based Database Management

Database structures are defined using JSON Schema specifications. This approach provides:

- Consistent database migrations across modules
- Validation of data structures at the schema level
- Self-documenting data models
- Simplified database version control

## Getting Started

To begin developing with the Modular App Framework, follow our [Installation Guide](./installation.md) and review the [Basic Concepts](./basic-concepts.md) documentation. Once familiar with the framework, you can explore specific features in detail through our module-specific guides.

## Use Cases

This framework is ideal for applications that:

- Need to scale in complexity over time
- Require granular permission systems
- Benefit from modular development practices
- Are maintained by multiple development teams
- Need standardized API endpoints

By providing a structured yet flexible foundation, the Modular App Framework helps development teams create maintainable, extensible applications with minimal boilerplate code.
```

