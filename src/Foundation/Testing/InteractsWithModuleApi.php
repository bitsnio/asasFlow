<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Testing;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\MenuService;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Foundation\Support\ModuleRegistrar;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
                SubstituteBindings::class,
            ]);
        }

        $this->withoutMiddleware([
            Authenticate::class,
            Authorize::class,
            'asasflow.tenancy',
        ]);

        Gate::before(static fn () => true);

        return $this;
    }

    /**
     * Discover all modules that have artifacts generated through `asasflow:generate-from-schema`.
     *
     * @return array<int, ModuleDefinition>
     */
    protected function discoverSchemaGeneratedModules(?string $onlyModule = null): array
    {
        /** @var ModuleRepositoryInterface $repository */
        $repository = app(ModuleRepositoryInterface::class);
        /** @var FileHandler $files */
        $files = app(FileHandler::class);

        $modules = $onlyModule !== null && $onlyModule !== ''
            ? array_filter([$repository->find($onlyModule)])
            : $repository->all();

        $discovered = [];

        foreach ($modules as $module) {
            if (!$module instanceof ModuleDefinition) {
                continue;
            }

            // Skip Core auth/tenancy bootstrap module unless explicitly requested
            if ($onlyModule === null && strtolower($module->getName()) === 'core') {
                continue;
            }

            $menuPath = $module->getPath() . '/config/menu.php';
            $migrationsDir = $module->getPath() . '/database/migrations';
            $schemaDir = $module->getPath() . '/schema';

            if (!is_file($menuPath) || !is_dir($schemaDir) || !is_dir($migrationsDir)) {
                continue;
            }

            $migrationFiles = glob($migrationsDir . '/*.php') ?: [];
            if ($migrationFiles === []) {
                continue;
            }

            $definitions = $this->getModuleMenuDefinitions($module->getName());
            if ($definitions === []) {
                continue;
            }

            // Ensure at least one resource has both a generated controller and a generated model
            $hasGeneratedArtifacts = false;
            foreach ($definitions as $definition) {
                $controllerPath = $files->getControllerPath($module, $definition->controllerRelativePath());
                $modelPath = $files->getModelPath($module, $definition->modelClass());

                if (is_file($controllerPath) && is_file($modelPath)) {
                    $hasGeneratedArtifacts = true;
                    break;
                }
            }

            if ($hasGeneratedArtifacts) {
                $discovered[] = $module;
            }
        }

        return $discovered;
    }

    /**
     * Register all modules, core routes, and execute migrations for schema-generated modules.
     *
     * @return array<int, ModuleDefinition>
     */
    protected function bootAndMigrateSchemaGeneratedModules(?string $onlyModule = null): array
    {
        $this->bypassModuleAuth();

        $packageRoutes = dirname(__DIR__, 2) . '/routes/api.php';
        if (is_file($packageRoutes) && !Route::getRoutes()->hasNamedRoute('asasflow.schema.show')) {
            require $packageRoutes;
        }

        app(ModuleRegistrar::class)->register();

        $modules = $this->discoverSchemaGeneratedModules($onlyModule);

        foreach ($modules as $module) {
            $migrationFiles = glob($module->getPath() . '/database/migrations/*.php') ?: [];
            sort($migrationFiles);

            foreach ($migrationFiles as $migrationFile) {
                $contents = (string) file_get_contents($migrationFile);
                if (
                    preg_match("/Schema::create\(\s*['\"]([^'\"]+)['\"]/", $contents, $matches)
                    && Schema::hasTable($matches[1])
                ) {
                    continue;
                }

                $migration = include $migrationFile;
                if (is_object($migration) && method_exists($migration, 'up')) {
                    $migration->up();
                }
            }
        }

        return $modules;
    }

    /**
     * Run full route, schema, validation, CRUD, relational, and dropdown assertions
     * across all modules generated via `asasflow:generate-from-schema`.
     */
    protected function assertAllSchemaGeneratedModulesCrud(?string $onlyModule = null): int
    {
        $modules = $this->bootAndMigrateSchemaGeneratedModules($onlyModule);
        /** @var FileHandler $files */
        $files = app(FileHandler::class);

        $testedResources = 0;

        foreach ($modules as $module) {
            $moduleName = $module->getName();

            // 1. Verify all CRUD and custom action routes from config/menu.php are registered
            $this->assertModuleRoutesRegistered($moduleName);

            // 2. Verify all JSON Schemas are served via GET /schema?link=...
            $this->assertModuleSchemasAccessible($moduleName);

            // 3. Test every schema-generated resource in the module
            foreach ($this->getModuleMenuDefinitions($moduleName) as $definition) {
                if (!$definition->isResource()) {
                    continue;
                }

                $modelClass = $definition->modelClass();
                $schemaPath = $files->getSchemaPath($module, $modelClass);
                $controllerPath = $files->getControllerPath($module, $definition->controllerRelativePath());
                $table = $definition->tableName();

                if (!is_file($schemaPath) || !is_file($controllerPath) || !Schema::hasTable($table)) {
                    continue;
                }

                $schema = SchemaDefinition::fromFile($schemaPath);
                $uri = '/' . ltrim($definition->routePath(), '/');

                $createPayload = $this->buildPayloadFromSchema($moduleName, $modelClass, [], false);
                $updatePayload = $this->buildPayloadFromSchema($moduleName, $modelClass, [], true);

                $this->assertResourceCrudLifecycle(
                    uri: $uri,
                    createPayload: $createPayload,
                    updatePayload: $updatePayload,
                    deleteAtEnd: true,
                    definition: $definition,
                    schema: $schema,
                );

                $testedResources++;
            }
        }

        return $testedResources;
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
        bool $forUpdate = false,
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

            $payload[$field] = $this->generateSampleValueForProperty((string) $field, $property, $forUpdate);
        }

        return array_replace_recursive($payload, $overrides);
    }

    /**
     * Generate a deterministic valid value for a single JSON Schema property.
     *
     * @param  array<string, mixed>  $property
     */
    protected function generateSampleValueForProperty(string $field, array $property, bool $forUpdate = false): mixed
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
            'integer' => (int) ($property['default'] ?? max(1, (int) ($property['minimum'] ?? 1))) + ($forUpdate && !str_ends_with($field, '_id') ? 1 : 0),
            'number' => (float) ($property['default'] ?? max(10.0, (float) ($property['minimum'] ?? 10.0))) + ($forUpdate ? 5.0 : 0.0),
            'array' => $this->generateSampleArrayValue($property, $forUpdate),
            'object' => $this->generateSampleObjectValue($property, $forUpdate),
            default => $this->generateSampleStringValue($field, $property, $forUpdate),
        };
    }

    /**
     * @param  array<string, mixed>  $property
     * @return array<int, mixed>
     */
    protected function generateSampleArrayValue(array $property, bool $forUpdate = false): array
    {
        $items = $property['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        if (($items['type'] ?? null) === 'object' || isset($items['properties'])) {
            return [$this->generateSampleObjectValue($items, $forUpdate)];
        }

        return [$this->generateSampleValueForProperty('item', $items, $forUpdate)];
    }

    /**
     * @param  array<string, mixed>  $property
     * @return array<string, mixed>
     */
    protected function generateSampleObjectValue(array $property, bool $forUpdate = false): array
    {
        $object = [];
        $properties = $property['properties'] ?? [];

        if (is_array($properties)) {
            foreach ($properties as $subField => $subProperty) {
                if (is_array($subProperty)) {
                    $object[$subField] = $this->generateSampleValueForProperty((string) $subField, $subProperty, $forUpdate);
                }
            }
        }

        return $object;
    }

    /**
     * @param  array<string, mixed>  $property
     */
    protected function generateSampleStringValue(string $field, array $property, bool $forUpdate = false): string
    {
        $format = $property['format'] ?? null;

        if ($format === 'email' || str_contains($field, 'email')) {
            return ($forUpdate ? 'updated.' : 'test.') . Str::lower(Str::random(6)) . '@example.com';
        }

        if ($format === 'date' || str_ends_with($field, '_date')) {
            return str_starts_with($field, 'end') ? '2026-12-31' : '2026-01-01';
        }

        if ($format === 'date-time') {
            return '2026-01-01T00:00:00Z';
        }

        if (str_contains($field, 'phone')) {
            return $forUpdate ? '+966509876543' : '+966501234567';
        }

        if ($field === 'code' || str_ends_with($field, '_number')) {
            return ($forUpdate ? 'UPD-' : 'TST-') . strtoupper(Str::random(5));
        }

        $maxLength = (int) ($property['maxLength'] ?? 100);
        $prefix = $forUpdate ? 'Updated ' : 'Sample ';
        $sample = $prefix . Str::headline($field);

        return mb_substr($sample, 0, max(2, $maxLength));
    }

    /**
     * Execute full CRUD lifecycle, validation, schema edit-mode prefill, relational sync,
     * and dropdown assertions against a resource endpoint using the unified response format.
     *
     * @param  array<string, mixed>  $createPayload
     * @param  array<string, mixed>  $updatePayload
     * @return array<string, mixed> The updated resource data
     */
    protected function assertResourceCrudLifecycle(
        string $uri,
        array $createPayload,
        array $updatePayload = [],
        bool $deleteAtEnd = true,
        ?MenuDefinition $definition = null,
        ?SchemaDefinition $schema = null,
    ): array {
        $this->bypassModuleAuth();

        // 0. Validation Error Check (422 Unprocessable Entity) when required fields exist
        if ($schema !== null && $schema->required() !== []) {
            $validationResponse = $this->postJson($uri, []);
            $validationResponse->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors',
                ]);
        }

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

        // Verify relational child arrays ("relation": true) were persisted and returned
        if ($schema !== null) {
            foreach ($schema->relationNames() as $relationName) {
                expect($created)->toHaveKey($relationName)
                    ->and($created[$relationName])->toBeArray()
                    ->not->toBeEmpty();
            }
        }

        // 2. Schema Edit Mode Prefill (`GET /schema?link=...&id={id}`)
        if ($definition !== null) {
            $link = $definition->routePath();
            $schemaEditResponse = $this->getJson("/schema?link={$link}&id={$id}");
            $schemaEditResponse->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'schema',
                        'model',
                    ],
                ]);

            if ($schema !== null) {
                foreach ($schema->relationNames() as $relationName) {
                    expect($schemaEditResponse->json("data.model.{$relationName}"))
                        ->toBeArray()
                        ->not->toBeEmpty();
                }
            }
        }

        // 3. Index (200 OK + Paginated Meta)
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

        expect($indexResponse->json('meta.total'))->toBeGreaterThanOrEqual(1);

        // 4. Show (200 OK)
        $showResponse = $this->getJson("{$uri}/{$id}");
        $showResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $id);

        // 5. Update (200 OK)
        $payloadForUpdate = $updatePayload !== [] ? array_replace_recursive($createPayload, $updatePayload) : $createPayload;
        $updateResponse = $this->putJson("{$uri}/{$id}", $payloadForUpdate);
        $updateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $id);

        // 6. Dropdown Endpoint Check (if table has a standard label column)
        if ($definition !== null && $schema !== null && Route::getRoutes()->hasNamedRoute('asasflow.dropdown.index')) {
            $table = $definition->tableName();
            $fillableKeys = array_keys($schema->fillableProperties());
            $candidateLabels = ['name', 'title', 'code', 'contract_number', 'invoice_number', 'payment_number', 'order_number'];
            $labelCol = null;

            foreach ($candidateLabels as $candidate) {
                if (in_array($candidate, $fillableKeys, true)) {
                    $labelCol = $candidate;
                    break;
                }
            }

            if ($labelCol !== null) {
                $dropdownResponse = $this->getJson("/dropdown/{$table}/{$labelCol}/id");
                $dropdownResponse->assertOk()
                    ->assertJsonPath('success', true)
                    ->assertJsonStructure([
                        'success',
                        'message',
                        'data' => [
                            '*' => ['label', 'value'],
                        ],
                    ]);
            }
        }

        // 7. Custom Actions Check (verifies every custom action route responds with a valid unified JSON envelope)
        if ($definition !== null && $definition->customActions() !== []) {
            foreach (array_keys($definition->customActions()) as $customAction) {
                $method = $definition->customActionMethod((string) $customAction);
                $actionUri = '/' . ltrim($definition->customActionRoutePath((string) $customAction), '/');

                $actionResponse = $this->json($method, $actionUri, ['id' => $id]);
                expect($actionResponse->status())
                    ->toBeIn([200, 201, 422, 501], "Custom action [{$method} {$actionUri}] returned unexpected status [{$actionResponse->status()}].")
                    ->and($actionResponse->json())->toBeArray()
                    ->toHaveKey('success')
                    ->toHaveKey('message');
            }
        }

        // 8. Destroy (200 OK, or 422 if a custom action locked the record against deletion)
        if ($deleteAtEnd) {
            $deleteResponse = $this->deleteJson("{$uri}/{$id}");
            expect($deleteResponse->status())->toBeIn([200, 422]);
            if ($deleteResponse->status() === 200) {
                $deleteResponse->assertJsonPath('success', true);
            }
        }

        return $updateResponse->json('data');
    }
}
