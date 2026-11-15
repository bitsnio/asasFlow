<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Testing;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\MenuService;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait InteractsWithModuleApi
{
    /**
     * Bypass authentication and permission gates for module endpoint testing.
     */
    protected function bypassModuleAuth(): static
    {
        app('router')->aliasMiddleware('auth', Authenticate::class);
        if (!array_key_exists('api', app('router')->getMiddlewareGroups())) {
            app('router')->middlewareGroup('api', [
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
            ]);
        }

        $this->withoutMiddleware([
            Authenticate::class,
            Authorize::class,
            'asasflow.tenancy',
        ]);

        Gate::before( static fn () => true);

        return $this;
    }

    /**
     * Retrieve all flattened resource MenuDefinition objects for a given module.
     *
     * @return array<int, MenuDefinition>
     */
    protected function getModuleMenuDefinitions(string $moduleName): array
    {
        $tree = app(MenuService::class)->getDefinitions($moduleName);
        $flattened = app(MenuBuilder::class)->flatten($tree);

        return array_values(array_filter(
            $flattened,
            static fn (MenuDefinition $definition): bool => $definition->isResource(),
        ));
    }

    /**
     * Assert that all CRUD and custom action routes defined in the module's menu.php are registered.
     */
    protected function assertModuleRoutesRegistered(string $moduleName): void
    {
        $definitions = $this->getModuleMenuDefinitions($moduleName);
        expect($definitions)->not->toBeEmpty();

        $registeredRoutes = collect(Route::getRoutes()->getRoutes());

        foreach ($definitions as $definition) {
            if (!$definition->isResource()) {
                continue;
            }

            $baseUri = trim($definition->routePath(), '/');
            $paramUri = "{$baseUri}/{{$definition->parameterName()}}";

            $expectedCrudMap = [
                'index' => ['GET', $baseUri],
                'store' => ['POST', $baseUri],
                'show' => ['GET', $paramUri],
                'update' => ['PUT', $paramUri],
                'destroy' => ['DELETE', $paramUri],
            ];

            foreach ($definition->routeActions() as $crudAction) {
                if (!isset($expectedCrudMap[$crudAction])) {
                    continue;
                }

                [$method, $uri] = $expectedCrudMap[$crudAction];
                $found = $registeredRoutes->contains(
                    static fn ($route) => in_array($method, $route->methods(), true)
                        && trim($route->uri(), '/') === $uri
                );

                expect($found)->toBeTrue("Expected CRUD route [{$method} /{$uri}] to be registered.");
            }

            foreach (array_keys($definition->customActions()) as $customAction) {
                $method = $definition->customActionMethod((string) $customAction);
                $uri = trim($definition->customActionRoutePath((string) $customAction), '/');

                $found = $registeredRoutes->contains(
                    static fn ($route) => in_array($method, $route->methods(), true)
                        && trim($route->uri(), '/') === $uri
                );

                expect($found)->toBeTrue("Expected custom action route [{$method} /{$uri}] to be registered.");
            }
        }
    }

    /**
     * Assert that all JSON schemas for the module are accessible via GET /schema?link=...
     */
    protected function assertModuleSchemasAccessible(string $moduleName): void
    {
        $this->bypassModuleAuth();

        /** @var ModuleRepositoryInterface $repository */
        $repository = app(ModuleRepositoryInterface::class);
        $module = $repository->find($moduleName);
        /** @var FileHandler $files */
        $files = app(FileHandler::class);

        foreach ($this->getModuleMenuDefinitions($moduleName) as $definition) {
            if (!$definition->isResource()) {
                continue;
            }

            $schemaPath = $files->getSchemaPath($module, $definition->modelClass());
            if (!is_file($schemaPath)) {
                continue;
            }

            $link = $definition->routePath();

            $response = $this->getJson("/schema?link={$link}");

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'schema',
                        'model',
                    ],
                ]);
        }
    }

    /**
     * Build a valid sample payload from a module resource's JSON Schema.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function buildPayloadFromSchema(
        string $moduleName,
        string $modelClass,
        array $overrides = [],
    ): array {
        /** @var ModuleRepositoryInterface $repository */
        $repository = app(ModuleRepositoryInterface::class);
        $module = $repository->find($moduleName);
        /** @var FileHandler $files */
        $files = app(FileHandler::class);

        $schemaPath = $files->getSchemaPath($module, $modelClass);
        $schema = SchemaDefinition::fromFile($schemaPath);

        $payload = [];
        foreach ($schema->properties() as $field => $property) {
            if (!is_array($property) || ($property['readOnly'] ?? false) === true) {
                continue;
            }

            $payload[$field] = $this->generateSampleValueForProperty((string) $field, $property);
        }

        return array_replace_recursive($payload, $overrides);
    }

    /**
     * Generate a deterministic valid value for a single JSON Schema property.
     *
     * @param  array<string, mixed>  $property
     */
    protected function generateSampleValueForProperty(string $field, array $property): mixed
    {
        if (isset($property['enum']) && is_array($property['enum']) && $property['enum'] !== []) {
            return $property['enum'][0];
        }

        $type = $property['type'] ?? 'string';
        if (is_array($type)) {
            $nonNull = array_values(array_filter($type, static fn ($t) => $t !== 'null'));
            $type = $nonNull[0] ?? 'string';
        }

        return match ($type) {
            'boolean' => (bool) ($property['default'] ?? true),
            'integer' => (int) ($property['default'] ?? max(1, (int) ($property['minimum'] ?? 1))),
            'number' => (float) ($property['default'] ?? max(10.0, (float) ($property['minimum'] ?? 10.0))),
            'array' => $this->generateSampleArrayValue($property),
            'object' => $this->generateSampleObjectValue($property),
            default => $this->generateSampleStringValue($field, $property),
        };
    }

    /**
     * @param  array<string, mixed>  $property
     * @return array<int, mixed>
     */
    protected function generateSampleArrayValue(array $property): array
    {
        $items = $property['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        if (($items['type'] ?? null) === 'object' || isset($items['properties'])) {
            return [$this->generateSampleObjectValue($items)];
        }

        return [$this->generateSampleValueForProperty('item', $items)];
    }

    /**
     * @param  array<string, mixed>  $property
     * @return array<string, mixed>
     */
    protected function generateSampleObjectValue(array $property): array
    {
        $object = [];
        $properties = $property['properties'] ?? [];

        if (is_array($properties)) {
            foreach ($properties as $subField => $subProperty) {
                if (is_array($subProperty)) {
                    $object[$subField] = $this->generateSampleValueForProperty((string) $subField, $subProperty);
                }
            }
        }

        return $object;
    }

    /**
     * @param  array<string, mixed>  $property
     */
    protected function generateSampleStringValue(string $field, array $property): string
    {
        $format = $property['format'] ?? null;

        if ($format === 'email' || str_contains($field, 'email')) {
            return 'test.' . Str::lower(Str::random(6)) . '@example.com';
        }

        if ($format === 'date' || str_ends_with($field, '_date')) {
            return str_starts_with($field, 'end') ? '2026-12-31' : '2026-01-01';
        }

        if ($format === 'date-time') {
            return '2026-01-01T00:00:00Z';
        }

        if (str_contains($field, 'phone')) {
            return '+966501234567';
        }

        if ($field === 'code' || str_ends_with($field, '_number')) {
            return 'TST-' . strtoupper(Str::random(5));
        }

        $maxLength = (int) ($property['maxLength'] ?? 100);
        $sample = 'Sample ' . Str::headline($field);

        return mb_substr($sample, 0, max(2, $maxLength));
    }

    /**
     * Execute full CRUD lifecycle assertions against a resource endpoint using the unified response format.
     *
     * @param  array<string, mixed>  $createPayload
     * @param  array<string, mixed>  $updatePayload
     * @return array<string, mixed> The created resource data
     */
    protected function assertResourceCrudLifecycle(
        string $uri,
        array $createPayload,
        array $updatePayload = [],
        bool $deleteAtEnd = true,
    ): array {
        $this->bypassModuleAuth();

        // 1. Store (201 Created)
        $storeResponse = $this->postJson($uri, $createPayload);
        $storeResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id'],
            ]);

        $created = $storeResponse->json('data');
        $id = $created['id'];

        // 2. Index (200 OK + Paginated Meta)
        $indexResponse = $this->getJson("{$uri}?page=1&per_page=15");
        $indexResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to',
                    'has_more_pages',
                ],
            ]);

        // 3. Show (200 OK)
        $showResponse = $this->getJson("{$uri}/{$id}");
        $showResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $id);

        // 4. Update (200 OK)
        $payloadForUpdate = $updatePayload !== [] ? array_replace_recursive($createPayload, $updatePayload) : $createPayload;
        $updateResponse = $this->putJson("{$uri}/{$id}", $payloadForUpdate);
        $updateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $id);

        // 5. Destroy (200 OK)
        if ($deleteAtEnd) {
            $deleteResponse = $this->deleteJson("{$uri}/{$id}");
            $deleteResponse->assertOk()
                ->assertJsonPath('success', true);
        }

        return $updateResponse->json('data');
    }
}
