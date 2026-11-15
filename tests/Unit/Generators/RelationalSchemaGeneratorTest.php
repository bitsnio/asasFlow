<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
use Bitsnio\AsasFlow\Generators\Migration\MigrationGenerator;
use Bitsnio\AsasFlow\Generators\Model\ModelGenerator;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/asasflow-relational-schema-test-' . uniqid();
    mkdir($this->tempDir, 0777, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        (new Filesystem())->deleteDirectory($this->tempDir);
    }
});

function makeRelationalTestModule(string $baseDir, string $name = 'Vms'): ModuleDefinition
{
    $modulePath = $baseDir . '/' . $name;
    mkdir($modulePath . '/app/Http/Controllers', 0777, true);
    mkdir($modulePath . '/app/Models', 0777, true);
    mkdir($modulePath . '/database/migrations', 0777, true);
    mkdir($modulePath . '/schema', 0777, true);

    return new ModuleDefinition(
        name: $name,
        path: $modulePath,
        namespace: "Modules\\{$name}",
    );
}

it('keeps array properties as JSON columns when relation is not set', function () {
    $schema = new SchemaDefinition([
        'schema' => [
            'title' => 'Service Checklist',
            'type' => 'object',
            'required' => ['name', 'tasks'],
            'properties' => [
                'name' => ['type' => 'string'],
                'tasks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($schema->relationNames())->toBe([])
        ->and($schema->relations('service_checklists', 'ServiceChecklists'))->toBe([])
        ->and($schema->migrationColumns())->toHaveKey('tasks')
        ->and($schema->casts())->toHaveKey('tasks', 'array');
});

it('derives parent and child migrations, models, and relational controller methods when relation: true is set on an array property', function () {
    $module = makeRelationalTestModule($this->tempDir, 'Vms');

    $schema = new SchemaDefinition([
        'schema' => [
            'title' => 'Vendor Contract',
            'type' => 'object',
            'required' => ['vendor_id', 'contract_number', 'services'],
            'properties' => [
                'vendor_id' => ['type' => 'integer'],
                'contract_number' => ['type' => 'string', 'maxLength' => 100],
                'is_locked' => ['type' => 'boolean', 'default' => false],
                'services' => [
                    'type' => 'array',
                    'relation' => true,
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'required' => ['service_id', 'purchase_rate'],
                        'properties' => [
                            'service_id' => ['type' => 'integer'],
                            'service_area_id' => ['type' => 'integer'],
                            'purchase_rate' => ['type' => 'number', 'default' => 0],
                            'selling_rate' => ['type' => 'number', 'default' => 0],
                            'is_locked' => ['type' => 'boolean', 'default' => true],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $definition = new MenuDefinition(
        module: 'Vms',
        name: 'vendor-contracts',
        title: 'Vendor Contracts',
        path: ['vms', 'onboarding', 'vendor-contracts'],
        type: 'resource',
        model: true,
        controllerClass: 'VendorContractsController',
        resourceClass: 'VendorContractsResource',
        config: [
            'tenancy' => true,
            'site' => false,
        ],
    );

    // 1. Verify SchemaDefinition convention metadata
    $relations = $schema->relations('vendor_contracts', 'VendorContracts');
    expect($schema->relationNames())->toBe(['services'])
        ->and($schema->migrationColumns())->not->toHaveKey('services')
        ->and($schema->casts())->not->toHaveKey('services')
        ->and($relations)->toHaveKey('services')
        ->and($relations['services']['table'])->toBe('vendor_contract_services')
        ->and($relations['services']['model'])->toBe('VendorContractServices')
        ->and($relations['services']['foreign_key'])->toBe('vendor_contract_id')
        ->and($relations['services']['inverse_method'])->toBe('vendorContract');

    // 2. Verify MigrationGenerator creates both parent and child migrations
    $migrationResult = app(MigrationGenerator::class)->syncFromSchema($module, $definition, $schema);
    expect($migrationResult['action'])->toBe('created')
        ->and($migrationResult)->toHaveKey('children')
        ->and($migrationResult['children'])->toHaveCount(1);

    $parentMigrationCode = file_get_contents($migrationResult['path']);
    $childMigrationCode = file_get_contents($migrationResult['children'][0]['path']);

    expect($parentMigrationCode)
        ->toContain("Schema::create('vendor_contracts'")
        ->toContain("\$table->integer('vendor_id');")
        ->toContain("\$table->string('contract_number', 100);")
        ->not->toContain("'services'");

    expect($childMigrationCode)
        ->toContain("Schema::create('vendor_contract_services'")
        ->toContain("\$table->foreignId('vendor_contract_id')->constrained('vendor_contracts')->cascadeOnDelete();")
        ->toContain("\$table->integer('service_id');")
        ->toContain("\$table->decimal('purchase_rate', 18, 4)->default(0);");

    // 3. Verify ModelGenerator creates both parent and child models with Eloquent relations
    $modelResult = app(ModelGenerator::class)->syncFromSchema($module, $definition, $schema);
    expect($modelResult['action'])->toBe('created')
        ->and($modelResult['children'])->toHaveCount(1);

    $parentModelCode = file_get_contents($modelResult['path']);
    $childModelCode = file_get_contents($modelResult['children'][0]['path']);

    expect($parentModelCode)
        ->toContain('class VendorContracts extends Model')
        ->toContain("'vendor_id',")
        ->toContain("'contract_number',")
        ->not->toContain("'services',")
        ->toContain('public function services(): \Illuminate\Database\Eloquent\Relations\HasMany')
        ->toContain("return \$this->hasMany(VendorContractServices::class, 'vendor_contract_id');");

    expect($childModelCode)
        ->toContain('class VendorContractServices extends Model')
        ->toContain("'vendor_contract_id',")
        ->toContain("'service_id',")
        ->toContain("'purchase_rate' => 'decimal:4',")
        ->toContain('public function vendorContract(): \Illuminate\Database\Eloquent\Relations\BelongsTo')
        ->toContain("return \$this->belongsTo(VendorContracts::class, 'vendor_contract_id');");

    // 4. Verify ControllerGenerator creates transactional relational store/update and eager-loaded index/show
    $controllerResult = app(ControllerGenerator::class)->syncFromSchema($module, $definition, $schema);
    $controllerCode = file_get_contents($controllerResult['path']);

    expect($controllerCode)
        ->toContain('use Illuminate\Support\Arr;')
        ->toContain('use Illuminate\Support\Facades\DB;')
        ->toContain("VendorContracts::query()->with(['services'])")
        ->toContain('DB::transaction(function () use ($request)')
        ->toContain("\$services = Arr::pull(\$data, 'services', []);")
        ->toContain('$item->services()->createMany($services);')
        ->toContain("\$vendor_contract->load(['services']);");
});
