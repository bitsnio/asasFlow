<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Http\Traits\JsonResponseTrait;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/asasflow-controller-gen-test-' . uniqid();
    mkdir($this->tempDir, 0777, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        (new Filesystem())->deleteDirectory($this->tempDir);
    }
});

function makeTestModule(string $baseDir, string $name): ModuleDefinition
{
    $modulePath = $baseDir . '/' . $name;
    mkdir($modulePath . '/app/Http/Controllers', 0777, true);

    return new ModuleDefinition(
        name: $name,
        path: $modulePath,
        namespace: "Modules\\{$name}",
    );
}

it('generates standardized controller methods with and without Resource class and deduplicates trait imports', function () {
    config([
        'asasflow.generators.controller.traits' => [
            JsonResponseTrait::class,
            '\\' . JsonResponseTrait::class,
        ],
        'asasflow.generators.controller.features.pagination.enabled' => true,
        'asasflow.generators.controller.features.pagination.per_page' => 15,
    ]);

    $generator = app(ControllerGenerator::class);
    $coreModule = makeTestModule($this->tempDir, 'Core');
    $inventoryModule = makeTestModule($this->tempDir, 'Inventory');

    // 1. With Resource class in Core module
    $userDef = new MenuDefinition(
        module: 'Core',
        name: 'users',
        title: 'Users',
        path: ['core', 'users'],
        type: 'resource',
        model: true,
        controllerClass: 'UserController',
        resourceClass: 'UserResource',
        config: [
            'actions' => [
                'export' => ['method' => 'GET'],
            ],
        ],
    );

    $results = $generator->generate($coreModule, [$userDef]);
    $userControllerCode = file_get_contents($results[0]['full_path']);

    expect($userControllerCode)
        ->toContain('use Illuminate\Http\JsonResponse;')
        ->toContain('use Bitsnio\AsasFlow\Foundation\Http\Traits\JsonResponseTrait;')
        ->toContain('use JsonResponseTrait;')
        ->toContain('$items = $this->paginateQuery(Users::query(), 15);')
        ->toContain('return $this->paginatedResponse(')
        ->toContain('UserResource::collection($items)')
        ->toContain('return $this->createdResponse(')
        ->toContain('return $this->successResponse(')
        ->toContain("return \$this->notImplementedResponse(\n            'export not implemented'\n        );")
        // Verify trait import and usage appear only once
        ->and(substr_count($userControllerCode, 'use Bitsnio\AsasFlow\Foundation\Http\Traits\JsonResponseTrait;'))->toBe(1)
        ->and(substr_count($userControllerCode, 'use JsonResponseTrait;'))->toBe(1);

    // 2. Without Resource class in Inventory module
    $warehouseDef = new MenuDefinition(
        module: 'Inventory',
        name: 'warehouses',
        title: 'Warehouses',
        path: ['inventory', 'warehouses'],
        type: 'resource',
        model: true,
        controllerClass: 'WarehouseController',
        resourceClass: null,
    );

    $invResults = $generator->generate($inventoryModule, [$warehouseDef]);
    $warehouseControllerCode = file_get_contents($invResults[0]['full_path']);

    expect($warehouseControllerCode)
        ->not->toContain('Resource')
        ->toContain('$items = $this->paginateQuery(Warehouses::query(), 15);')
        ->toContain("return \$this->paginatedResponse(\n            \$items\n        );")
        ->toContain("return \$this->createdResponse(\n            \$item\n        );");
});

it('is idempotent when run twice and preserves developer-customized methods during synchronization', function () {
    $generator = app(ControllerGenerator::class);
    $module = makeTestModule($this->tempDir, 'Core');

    $initialDef = new MenuDefinition(
        module: 'Core',
        name: 'organizations',
        title: 'Organizations',
        path: ['core', 'organizations'],
        type: 'resource',
        model: true,
        controllerClass: 'OrganizationController',
        resourceClass: 'OrganizationResource',
    );

    $firstRun = $generator->generate($module, [$initialDef]);
    $controllerPath = $firstRun[0]['full_path'];

    // Simulate developer customizing the index() method with custom business logic
    $customized = str_replace(
        '$items = $this->paginateQuery(Organizations::query(), 15);',
        '// Custom developer query logic' . PHP_EOL . '        $items = $this->paginateQuery(Organizations::where("active", true), 25);',
        file_get_contents($controllerPath),
    );
    file_put_contents($controllerPath, $customized);

    // Run generator a second time with identical definition -> skipped, custom code preserved
    $secondRun = $generator->generate($module, [$initialDef]);
    expect($secondRun[0]['action'])->toBe('skipped')
        ->and(file_get_contents($controllerPath))->toContain('// Custom developer query logic');

    // Now add a new custom action 'archive' to menu.php and sync
    $updatedDef = new MenuDefinition(
        module: 'Core',
        name: 'organizations',
        title: 'Organizations',
        path: ['core', 'organizations'],
        type: 'resource',
        model: true,
        controllerClass: 'OrganizationController',
        resourceClass: 'OrganizationResource',
        config: [
            'actions' => [
                'archive' => ['method' => 'POST'],
            ],
        ],
    );

    $thirdRun = $generator->generate($module, [$updatedDef]);
    $syncedContent = file_get_contents($controllerPath);

    expect($thirdRun[0]['action'])->toContain('added: archive')
        ->and($syncedContent)
        ->toContain('// Custom developer query logic')
        ->toContain('public function archive(Request $request): JsonResponse')
        ->toContain("'archive not implemented'")
        ->and(substr_count($syncedContent, 'public function index('))->toBe(1);
});
