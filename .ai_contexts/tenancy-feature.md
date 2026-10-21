# Laravel Project Context

**Task:** Tenancy Feature

## Background and Purpose

building a package in laravel which provides modular approach of code generated through laravel-module package. this package generate basic strucure along with menu.php file which contain full information based on what routes and controller are generated. i have designe the package in a way that each function is created as feature.
---

## Directory Structure

```
src/Features/Tenancy
src/Features/Tenancy/Contracts
src/Features/Tenancy/Http
src/Features/Tenancy/Http/Middleware
src/Features/Tenancy/Models
src/Features/Tenancy/Services
```

---

## Project Files

### src/Features/Tenancy/Contracts/TenantAware.php

```php
<?php

namespace Bitsnio\AsasFlow\Features\Tenancy\Contracts;

interface TenantAware
{
    /**
     * Get the tenant identifier for this resource.
     */
    public function getTenantId(): ?string;

    /**
     * Scope queries to current tenant.
     */
    public function scopeForCurrentTenant($query);
}

```

### src/Features/Tenancy/Http/Middleware/InitializeTenancy.php

```php
<?php

namespace Bitsnio\AsasFlow\Features\Tenancy\Http\Middleware;

use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancy
{
    /**
     * Handle request with tenant initialization.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for central routes
        if ($this->isCentralRoute($request)) {
            return $next($request);
        }

        // Skip if tenancy disabled
        if (!config('asasflow.tenancy.enabled', false)) {
            return $next($request);
        }

        $tenant = TenantContext::initialize();

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found or inactive',
                'domain' => $request->getHost(),
            ], 404);
        }

        // Attach tenant to request
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);

        return $next($request);
    }

    /**
     * Check if route is central (landlord).
     */
    protected function isCentralRoute(Request $request): bool
    {
        $centralRoutes = config('asasflow.tenancy.central_routes', [
            'api/central/*',
            'admin/*',
            'health',
            'tenants/*',
            'asasflow/*',
        ]);

        $routeName = $request->route()?->getName() ?? '';
        
        foreach ($centralRoutes as $pattern) {
            if ($request->is($pattern) || str_starts_with($routeName, str_replace('/*', '', $pattern))) {
                return true;
            }
        }

        // Check central domains
        $centralDomains = config('asasflow.tenancy.central_domains', []);
        if (in_array($request->getHost(), $centralDomains)) {
            // Only central routes allowed on central domains
            return !$request->is('api/module/*');
        }

        return false;
    }
}

```

### src/Features/Tenancy/Models/Tenant.php

```php
<?php

namespace Bitsnio\AsasFlow\Features\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'domain',
        'database',
        'settings',
        'is_active',
        'plan',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function getDatabaseName(): string
    {
        return $this->database ?? "tenant_{$this->slug}";
    }
}

```

### src/Features/Tenancy/Models/TenantDomain.php

```php
<?php

namespace Bitsnio\AsasFlow\Features\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
        'is_verified',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

```

### src/Features/Tenancy/Services/TenantContext.php

```php
<?php

namespace Bitsnio\AsasFlow\Features\Tenancy\Services;

use Bitsnio\AsasFlow\Features\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantContext
{
    protected static ?Tenant $currentTenant = null;
    protected static bool $initialized = false;
    protected static ?string $originalPrefix = null;

    /**
     * Initialize tenant from request.
     */
    public static function initialize(?string $identifier = null): ?Tenant
    {
        if (self::$initialized && !$identifier) {
            return self::$currentTenant;
        }

        $tenant = self::resolveTenant($identifier);
        
        if ($tenant) {
            self::setTenant($tenant);
        }

        self::$initialized = true;
        return $tenant;
    }

    /**
     * Resolve tenant from various sources.
     */
    public static function resolveTenant(?string $identifier = null): ?Tenant
    {
        // 1. Explicit identifier
        if ($identifier) {
            return self::findTenant($identifier);
        }

        // 2. X-Tenant-ID header
        if ($header = request()->header('X-Tenant-ID')) {
            return self::findTenant($header);
        }

        // 3. X-Tenant-Slug header
        if ($slug = request()->header('X-Tenant-Slug')) {
            return self::findTenant($slug);
        }

        // 4. Domain-based resolution
        $domain = request()->getHost();
        return Cache::remember("asasflow:tenant:domain:{$domain}", 3600, function () use ($domain) {
            $domainRecord = TenantDomain::where('domain', $domain)
                ->where('is_verified', true)
                ->with('tenant')
                ->first();

            return $domainRecord?->tenant;
        });
    }

    /**
     * Find tenant by ID or slug.
     */
    public static function findTenant(string $identifier): ?Tenant
    {
        return Cache::remember("asasflow:tenant:id:{$identifier}", 3600, function () use ($identifier) {
            return Tenant::where('slug', $identifier)
                ->orWhere('id', $identifier)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * Set current tenant.
     */
    public static function setTenant(Tenant $tenant): void
    {
        self::$currentTenant = $tenant;

        // Store original cache prefix
        if (self::$originalPrefix === null) {
            self::$originalPrefix = config('cache.prefix');
        }

        // Set tenant-scoped cache prefix
        Config::set('cache.prefix', "t{$tenant->id}_" . self::$originalPrefix);

        // Switch database if using separate strategy
        if (config('asasflow.tenancy.database_strategy') === 'separate') {
            self::switchDatabase($tenant);
        }

        // Bind to container
        app()->instance('current.tenant', $tenant);
        app()->instance(Tenant::class, $tenant);
    }

    /**
     * Get current tenant.
     */
    public static function getCurrentTenant(): ?Tenant
    {
        return self::$currentTenant ?? app('current.tenant', null);
    }

    /**
     * Get tenant ID.
     */
    public static function getTenantId(): ?string
    {
        return self::getCurrentTenant()?->id;
    }

    /**
     * Get tenant slug.
     */
    public static function getTenantSlug(): ?string
    {
        return self::getCurrentTenant()?->slug;
    }

    /**
     * Check if tenant is initialized.
     */
    public static function isTenantInitialized(): bool
    {
        return self::$currentTenant !== null;
    }

    /**
     * Reset tenant context.
     */
    public static function reset(): void
    {
        self::$currentTenant = null;
        self::$initialized = false;
        
        if (self::$originalPrefix !== null) {
            Config::set('cache.prefix', self::$originalPrefix);
        }

        // Reset database connection
        if (config('asasflow.tenancy.database_strategy') === 'separate') {
            Config::set('database.default', config('asasflow.tenancy.central_connection', 'mysql'));
            DB::purge('tenant');
        }
    }

    /**
     * Execute callback within tenant context.
     */
    public static function runForTenant(Tenant|string $tenant, callable $callback): mixed
    {
        $previousTenant = self::$currentTenant;
        
        if (is_string($tenant)) {
            $tenant = self::findTenant($tenant);
        }

        if (!$tenant) {
            throw new \RuntimeException("Tenant not found: {$tenant}");
        }

        try {
            self::setTenant($tenant);
            return $callback();
        } finally {
            if ($previousTenant) {
                self::setTenant($previousTenant);
            } else {
                self::reset();
            }
        }
    }

    /**
     * Switch database connection for tenant.
     */
    protected static function switchDatabase(Tenant $tenant): void
    {
        $connection = config('asasflow.tenancy.tenant_connection', 'tenant');
        
        Config::set("database.connections.{$connection}.database", $tenant->getDatabaseName());
        
        DB::purge($connection);
        DB::reconnect($connection);
        
        Config::set('database.default', $connection);
    }
}

```

### src/Features/Tenancy/TenancyServiceProvider.php

```php
<?php

namespace Bitsnio\AsasFlow\Features\Tenancy;

use Bitsnio\AsasFlow\Features\Tenancy\Http\Middleware\InitializeTenancy;
use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tenant context is stateless, no singleton needed
    }

    public function boot(): void
    {
        // Register middleware
        $this->app['router']->aliasMiddleware('asasflow.tenancy', InitializeTenancy::class);

        // Auto-apply tenancy to module routes
        $this->autoApplyTenancy();
    }

    protected function autoApplyTenancy(): void
    {
        if (!config('asasflow.tenancy.enabled', false)) {
            return;
        }

        $router = $this->app['router'];

        // Apply to all module routes
        $router->matched(function ($route, $request) {
            $name = $route->getName() ?? '';
            
            if (str_starts_with($name, 'module.')) {
                $route->middleware('asasflow.tenancy');
            }
        });
    }
}

```

### config/asasflow.php

```php
<?php

return [
    'version' => env('ASASFLOW_VERSION'),
    "modules" => [
        "enabled" => true,
        "path" => base_path("Modules"),
        "namespace" => "Modules",
        'scaffold' => [

            'app_providers' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Providers',
                'stub' => [
                    'enabled' => true,
                    'path' => 'app/Providers/{{ MODULE }}ServiceProvider.php',
                    'stub' => 'Module/module-service-provider.stub',
                    'replacements' => [],
                ],
            ],

            'app_commands' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Console/Commands',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_controllers' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Http/Controllers',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_requests' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Http/Requests',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_resources' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Http/Resources',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_models' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Models',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_observers' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Observers',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_policies' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Policies',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_rules' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Rules',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_services' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Services',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_actions' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Actions',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_dtos' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/DTOs',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_enums' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Enums',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_traits' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Traits',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_events' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Events',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_listeners' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Listeners',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_jobs' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Jobs',
                'stub' => [
                    'enabled' => false,
                    'path' => 'app/Jobs/{{ MODULE }}QueueJob.php',
                    'stub' => 'Module/queue-job.stub',
                    'replacements' => [],
                ],
            ],

            'queue' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Jobs/Queues',
                'stub' => [
                    'enabled' => true,
                    'path' => 'app/Jobs/Queues/{{ MODULE }}Queue.php',
                    'stub' => 'Module/queue.stub',
                    'replacements' => [],
                ],
            ],

            'app_notifications' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Notifications',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_exceptions' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Exceptions',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'config' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'config',
                'stub' => [
                    'enabled' => true,
                    'path' => 'config/config.php',
                    'stub' => 'Module/config.stub',
                    'replacements' => [],
                ],
            ],

            'menu' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'config',
                'stub' => [
                    'enabled' => true,
                    'path' => 'config/menu.php',
                    'stub' => 'Module/menu.stub',
                    'replacements' => [],
                ],
            ],

            'settings' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'config',
                'stub' => [
                    'enabled' => true,
                    'path' => 'config/settings.php',
                    'stub' => 'Module/settings.stub',
                    'replacements' => [],
                ],
            ],

            'migrations' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'database/migrations',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'factories' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'database/factories',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'seeders' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'database/seeders',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'translations' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'lang/en',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'views' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'resources/views',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'routes' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'routes',
                'stub' => [
                    'enabled' => true,
                    'path' => 'routes/api.php',
                    'stub' => 'Module/api.stub',
                    'replacements' => [],
                ],
            ],
            'custom_routes' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'routes',
                'stub' => [
                    'enabled' => true,
                    'path' => 'routes/custom.php',
                    'stub' => 'Module/rout.stub',
                    'replacements' => [],
                ],
            ],
            'tests_feature' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'tests/Feature',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'tests_unit' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'tests/Unit',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'manifest' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => '.',
                'stub' => [
                    'enabled' => true,
                    'path' => 'module.json',
                    'stub' => 'Module/module-json.stub',
                    'replacements' => [],
                ],
            ],

            'composer' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => '.',
                'stub' => [
                    'enabled' => true,
                    'path' => 'composer.json',
                    'stub' => 'Module/composer-json.stub',
                    'replacements' => [],
                ],
            ],

        ],
    ],
    "routes" => [
        "enabled" => true,
    ],
    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        // Enable/disable caching
        'enabled' => env('ASASFLOW_CACHE_ENABLED', true),

        // Cache store to use (from cache.php stores)
        'store' => env('ASASFLOW_CACHE_STORE', config('cache.default', 'redis')),

        // Key prefix for all asasflow cache keys
        'prefix' => env('ASASFLOW_CACHE_PREFIX', 'asasflow'),

        // Default TTL in seconds
        'default_ttl' => env('ASASFLOW_CACHE_TTL', 3600),

        // Strict tenant isolation (prefix all keys with tenant ID)
        'strict_isolation' => env('ASASFLOW_CACHE_STRICT_ISOLATION', true),

        // Cache authenticated requests
        'cache_authenticated' => env('ASASFLOW_CACHE_AUTH', false),

        // Use queue for cache warming
        'warm_via_queue' => env('ASASFLOW_CACHE_QUEUE', true),

        // Maintain key registry for non-tag drivers (file/database)
        'fallback_registry' => true,

        // Add debug headers to responses
        'debug_headers' => env('ASASFLOW_CACHE_DEBUG', false),

        // HTTP cache headers max-age
        'http_max_age' => env('ASASFLOW_CACHE_HTTP_MAX_AGE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Tenancy Configuration
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        // Enable multi-tenancy
        'enabled' => env('ASASFLOW_TENANCY_ENABLED', false),

        // Database strategy: 'single' (tenant_id column) or 'separate' (per-tenant DB)
        'database_strategy' => env('ASASFLOW_TENANCY_DB_STRATEGY', 'single'),

        // Central domains (landlord access)
        'central_domains' => explode(',', env('ASASFLOW_CENTRAL_DOMAINS', 'localhost,127.0.0.1')),

        // Routes that skip tenant resolution
        'central_routes' => [
            'api/central/*',
            'admin/*',
            'health',
            'tenants/*',
            'asasflow/*',
        ],

        // Tenant model class
        'tenant_model' => \Bitsnio\AsasFlow\Features\Tenancy\Models\Tenant::class,

        // Connection name for tenant databases (when using separate strategy)
        'tenant_connection' => 'tenant',

        // Central connection name
        'central_connection' => config('database.default', 'mysql'),

        // Auto-create tenant database on tenant creation
        'auto_create_db' => true,

        // Auto-run tenant migrations
        'auto_migrate' => true,

        // Cache tenant resolution
        'cache_resolution' => true,
        'cache_resolution_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Generator Configuration
    |--------------------------------------------------------------------------
    |
    | Defaults used when generating controllers, requests, and resources.
    | Individual generators decide which configured features apply.
    |
    */
    'generators' => [
        /*
        |--------------------------------------------------------------------------
        | Controller Generator
        |--------------------------------------------------------------------------
        */
        'controller' => [
            /*
         * Additional imports added to generated controllers.
         * Use fully qualified class names, without "use".
         */
            'imports' => [],

            /*
         * Traits imported and applied inside the generated class.
         * Use fully qualified trait names.
         */
            'traits' => [],

            'features' => [
                'pagination' => [
                    'enabled' => true,
                    'per_page' => 15,
                ],

                /*
             * Reserved for the filtering implementation.
             * Enabling this alone does not implement filtering.
             */
                'filtering' => [
                    'enabled' => false,
                ],

                /*
             * Global model scopes are applied by Eloquent automatically.
             * This flag does not attach scopes to a model.
             */
                'scopes' => [
                    'enabled' => false,
                ],
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Request Generator
    |--------------------------------------------------------------------------
    */
        'request' => [
            'imports' => [],
            'traits' => [],

            'features' => [
                'schema_rules' => [
                    'enabled' => true,
                ],
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Resource Generator
    |--------------------------------------------------------------------------
    */
        'resource' => [
            'imports' => [],
            'traits' => [],

            'features' => [
                'schema_fields' => [
                    'enabled' => true,
                ],
            ],
        ],
    ],
    'install' => [
        /*
     * Modules required for a usable AsasFlow installation.
     */
        'required_modules' => [
            'system' => [
                'version' => '^1.0',
                'dev_url' => env('ASASFLOW_SYSTEM_MODULE_SOURCE'),
                'release_url' => env('ASASFLOW_SYSTEM_MODULE_SHA256'),
            ],
        ],
        'jwt' => [
            'user_model' => env(
                'ASASFLOW_JWT_USER_MODEL',
                'Modules\\Core\\Models\\User'
            ),
            'guard' => 'api',
            'provider' => 'asasflow_users',
        ],
    ],
];

```
### tenancyModel
```php
<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenants extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // @asasflow:generated-fillable:start
        'name',
        'slug',
        'email',
        'contact_number',
        'address',
        'location_coordinates',
        'valid_until',
        'is_active',
        // @asasflow:generated-fillable:end
    ];

    protected $casts = [
        // @asasflow:generated-casts:start
        'address' => 'array',
        'location_coordinates' => 'array',
        'valid_until' => 'date',
        'is_active' => 'boolean',
        // @asasflow:generated-casts:end
    ];
}
```
###Tenancy migration
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Bitsnio Corp');
            $table->string('slug');
            $table->string('email');
            $table->string('contact_number');
            $table->json('address')->nullable();
            $table->json('location_coordinates')->nullable();
            $table->date('valid_until');
            $table->boolean('is_active')->nullable()->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
```