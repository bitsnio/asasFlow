<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Http\Cors\CorsConfigurator;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    app('router')->aliasMiddleware('auth', Authenticate::class);

    $this->app->bind(ModuleRepositoryInterface::class, fn () => new class implements ModuleRepositoryInterface {
        public function find(string $name): ?ModuleDefinition
        {
            foreach ($this->all() as $module) {
                if (strcasecmp($module->getName(), $name) === 0) {
                    return $module;
                }
            }

            return null;
        }

        public function all(): array
        {
            return [
                new ModuleDefinition('Core', '/tmp/Modules/Core', 'Modules\\Core'),
                new ModuleDefinition('Vms', '/tmp/Modules/Vms', 'Modules\\Vms'),
                new ModuleDefinition('FieldOps', '/tmp/Modules/FieldOps', 'Modules\\FieldOps'),
            ];
        }

        public function enabled(): array
        {
            return $this->all();
        }
    });

    app()->forgetInstance(CorsConfigurator::class);
    app(CorsConfigurator::class)->configure();

    Route::get('vms/catalog/service-categories', fn () => response()->success(['items' => []]));
    Route::post('core/access/users/login', fn () => response()->success(['token' => 'jwt-token']));
    Route::middleware('auth:api')->get('vms/protected-endpoint', fn () => response()->success(['ok' => true]));
    Route::get('schema', fn () => response()->success(['schema' => [], 'model' => []]));
    Route::get('dropdown/modules/name', fn () => response()->success([['label' => 'Vms', 'value' => 'Vms']]));
    Route::get('settings', fn () => response()->success(['modules' => []]));
});

it('loads all modular functional configurations into config(asasflow.*)', function () {
    expect(config('asasflow.modules'))->toBeArray()->toHaveKey('scaffold')
        ->and(config('asasflow.cache'))->toBeArray()->toHaveKey('default_ttl')
        ->and(config('asasflow.tenancy'))->toBeArray()->toHaveKey('database_strategy')
        ->and(config('asasflow.generators'))->toBeArray()->toHaveKey('controller')
        ->and(config('asasflow.dropdown'))->toBeArray()->toHaveKey('protected_tables')
        ->and(config('asasflow.cors'))->toBeArray()->toHaveKey('auto_discover_module_paths')
        ->and(config('asasflow.install'))->toBeArray()->toHaveKey('jwt');
});

it('automatically discovers module route prefixes and merges them into Laravel cors.paths', function () {
    $paths = config('cors.paths');

    expect($paths)->toBeArray()
        ->toContain('api/*')
        ->toContain('schema')
        ->toContain('dropdown/*')
        ->toContain('settings')
        ->toContain('core/*')
        ->toContain('vms/*')
        ->toContain('field-ops/*')
        ->toContain('fieldops/*');
});

it('handles browser preflight OPTIONS requests on module and package routes with CORS headers', function (string $uri) {
    $response = $this->call('OPTIONS', $uri, [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost:4200',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type, Authorization, X-Tenant-ID, X-Site-ID',
    ]);

    expect($response->getStatusCode())->toBeIn([200, 204])
        ->and($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
})->with([
    '/core/access/users/login',
    '/vms/catalog/service-categories',
    '/schema',
    '/dropdown/modules/name',
    '/settings',
]);

it('attaches CORS and exposed headers to actual browser GET and POST requests on module and package routes', function () {
    $getResponse = $this->get('/vms/catalog/service-categories', [
        'Origin' => 'http://localhost:4200',
    ]);

    $getResponse->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*');

    $exposed = (string) $getResponse->headers->get('Access-Control-Expose-Headers');
    expect($exposed)->toContain('X-AsasFlow-Cache')
        ->toContain('ETag');

    $postResponse = $this->postJson('/core/access/users/login', [
        'email' => 'admin@primary.localhost',
        'password' => 'password123',
    ], [
        'Origin' => 'http://localhost:4200',
    ]);

    $postResponse->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

it('returns a 401 JSON response with CORS headers when an unauthenticated browser sends Accept: */* to a module route', function () {
    $response = $this->get('/vms/protected-endpoint', [
        'Origin' => 'http://localhost:4200',
        'Accept' => '*/*',
    ]);

    $response->assertStatus(401)
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Unauthenticated.');
});
