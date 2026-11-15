<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Support\ModuleRegistrar;
use Bitsnio\AsasFlow\Foundation\Testing\InteractsWithModuleApi;

uses(InteractsWithModuleApi::class);

beforeEach(function () {
    $externalModulesPath = '/Users/haseeb/Desktop/sourcecode/project_to_test_local_package/Modules';

    if (!is_dir($externalModulesPath . '/Vms')) {
        $this->markTestSkipped('External Vms module directory is not available.');
    }

    config([
        'asasflow.modules.path' => $externalModulesPath,
        'asasflow.modules.namespace' => 'Modules',
    ]);

    // Register Vms module provider, routes, and PSR-4 autoloading dynamically
    $this->bypassModuleAuth();
    require dirname(__DIR__, 3) . '/src/routes/api.php';
    app(ModuleRegistrar::class)->register();

    // Run Vms migrations in test SQLite database
    foreach (glob($externalModulesPath . '/Vms/database/migrations/*.php') as $migrationFile) {
        $migration = include $migrationFile;
        $migration->up();
    }
});

it('registers all VMS routes, serves schemas, and passes schema-driven CRUD contract for every VMS resource', function () {
    $this->assertModuleRoutesRegistered('Vms');
    $this->assertModuleSchemasAccessible('Vms');

    foreach ($this->getModuleMenuDefinitions('Vms') as $definition) {
        if (!$definition->isResource()) {
            continue;
        }

        $uri = '/' . $definition->routePath();
        $payload = $this->buildPayloadFromSchema('Vms', $definition->modelClass());

        $this->assertResourceCrudLifecycle($uri, $payload);
    }
});

it('executes full end-to-end VMS catalog, vendor onboarding, relational contract, custom actions, and dropdown workflows', function () {
    // 1. Service Categories CRUD
    $category = $this->assertResourceCrudLifecycle(
        '/vms/catalog/service-categories',
        [
            'name' => 'HVAC & Climate Control',
            'code' => 'CAT-HVAC',
            'description' => 'Heating, ventilation, and air conditioning services',
            'is_active' => true,
        ],
        [
            'name' => 'HVAC & Chiller Maintenance',
            'description' => 'Updated HVAC and chiller maintenance services',
        ],
        deleteAtEnd: false,
    );

    expect($category['name'])->toBe('HVAC & Chiller Maintenance');

    // 2. SLA Policies CRUD
    $slaPolicy = $this->assertResourceCrudLifecycle(
        '/vms/catalog/sla-policies',
        [
            'name' => 'Critical Emergency SLA (4 Hours)',
            'code' => 'SLA-CRIT-4H',
            'priority' => 'critical',
            'response_time_minutes' => 30,
            'resolution_time_minutes' => 240,
            'penalty_type' => 'percentage',
            'penalty_value' => 5.0,
            'description' => '30m response / 4h resolution',
            'is_active' => true,
        ],
        [
            'response_time_minutes' => 15,
            'resolution_time_minutes' => 180,
        ],
        deleteAtEnd: false,
    );

    expect($slaPolicy['response_time_minutes'])->toBe(15);

    // 3. Service Checklists CRUD + Duplicate Custom Action
    $checklist = $this->assertResourceCrudLifecycle(
        '/vms/catalog/service-checklists',
        [
            'name' => 'Quarterly HVAC Preventive Inspection',
            'code' => 'CHK-HVAC-Q1',
            'description' => 'Standard technician SOP checklist',
            'tasks' => [
                [
                    'title' => 'Inspect and clean air filters',
                    'input_type' => 'photo',
                    'is_required' => true,
                ],
            ],
            'is_active' => true,
        ],
        [
            'name' => 'Quarterly HVAC Preventive Inspection v2',
        ],
        deleteAtEnd: false,
    );

    $duplicateResponse = $this->postJson('/vms/catalog/service-checklists/duplicate', [
        'id' => $checklist['id'],
        'name' => 'Annual HVAC Deep Inspection',
        'code' => 'CHK-HVAC-ANNUAL',
    ]);

    $duplicateResponse->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Annual HVAC Deep Inspection')
        ->assertJsonPath('data.code', 'CHK-HVAC-ANNUAL');

    expect($duplicateResponse->json('data.tasks'))->toHaveCount(1);

    // 4. Service Coverage Areas CRUD + Activate / Deactivate Actions
    $serviceArea = $this->assertResourceCrudLifecycle(
        '/vms/catalog/service-areas',
        [
            'name' => 'Riyadh North Business District',
            'code' => 'AREA-RUH-N',
            'city' => 'Riyadh',
            'state' => 'Riyadh Province',
            'postal_code' => '11564',
            'is_active' => true,
        ],
        [
            'name' => 'Riyadh North & Olaya District',
        ],
        deleteAtEnd: false,
    );

    $this->postJson('/vms/catalog/service-areas/deactivate', ['id' => $serviceArea['id']])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_active', false);

    $this->postJson('/vms/catalog/service-areas/activate', ['id' => $serviceArea['id']])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_active', true);

    // 5. Services Catalog CRUD + Activate / Deactivate Actions
    $service = $this->assertResourceCrudLifecycle(
        '/vms/catalog/services',
        [
            'service_category_id' => $category['id'],
            'sla_policy_id' => $slaPolicy['id'],
            'service_checklist_id' => $checklist['id'],
            'name' => 'Split AC Preventive Maintenance',
            'code' => 'SRV-AC-PM',
            'unit' => 'visit',
            'base_purchase_rate' => 150.00,
            'base_selling_rate' => 225.00,
            'description' => 'Preventive maintenance per split AC unit',
            'is_active' => true,
        ],
        [
            'base_purchase_rate' => 160.00,
            'base_selling_rate' => 240.00,
        ],
        deleteAtEnd: false,
    );

    $this->postJson('/vms/catalog/services/deactivate', ['id' => $service['id']])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_active', false);

    $this->postJson('/vms/catalog/services/activate', ['id' => $service['id']])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_active', true);

    // 6. Service Providers (Vendors) CRUD + Assign Areas, Activate, Suspend
    $vendor = $this->assertResourceCrudLifecycle(
        '/vms/onboarding/vendors',
        [
            'name' => 'Al-Futtaim Technical Services Co.',
            'code' => 'VND-AFTS-01',
            'contact_person' => 'Tariq Al-Mansoor',
            'email' => 'tariq@afts-example.com',
            'phone' => '+966501234567',
            'tax_number' => '300123456700003',
            'address' => [
                'street_address' => 'King Fahd Road, Tower B',
                'city' => 'Riyadh',
                'state' => 'Riyadh Province',
            ],
            'bank_details' => [
                'bank_name' => 'Al Rajhi Bank',
                'account_title' => 'Al-Futtaim Technical Services Co.',
                'account_number' => 'SA0380000000608010167519',
            ],
            'status' => 'pending',
            'is_active' => true,
        ],
        [
            'status' => 'approved',
        ],
        deleteAtEnd: false,
    );

    $this->postJson('/vms/onboarding/vendors/assign-areas', [
        'id' => $vendor['id'],
        'service_area_ids' => [$serviceArea['id']],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.address.service_area_ids.0', $serviceArea['id']);

    $this->postJson('/vms/onboarding/vendors/suspend', ['id' => $vendor['id']])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'suspended')
        ->assertJsonPath('data.is_active', false);

    $this->postJson('/vms/onboarding/vendors/activate', ['id' => $vendor['id']])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_active', true);

    // 7. Vendor Contracts & Relational Contract Services + Custom Actions
    $contract = $this->assertResourceCrudLifecycle(
        '/vms/onboarding/vendor-contracts',
        [
            'vendor_id' => $vendor['id'],
            'contract_number' => 'CNT-2026-0001',
            'title' => 'Master HVAC Maintenance Agreement 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'billing_cycle' => 'monthly',
            'payment_terms_days' => 30,
            'status' => 'draft',
            'is_locked' => false,
            'notes' => 'Initial draft agreement',
            'is_active' => true,
            'services' => [
                [
                    'service_id' => $service['id'],
                    'service_area_id' => $serviceArea['id'],
                    'purchase_rate' => 155.00,
                    'selling_rate' => 235.00,
                    'is_locked' => false,
                    'is_active' => true,
                ],
            ],
        ],
        [
            'payment_terms_days' => 45,
            'services' => [
                [
                    'service_id' => $service['id'],
                    'service_area_id' => $serviceArea['id'],
                    'purchase_rate' => 148.00,
                    'selling_rate' => 235.00,
                    'is_locked' => false,
                    'is_active' => true,
                ],
            ],
        ],
        deleteAtEnd: false,
    );

    expect($contract['services'])->toHaveCount(1)
        ->and((float) $contract['services'][0]['purchase_rate'])->toBe(148.0);

    // Verify GET /schema?link=vms/onboarding/vendor-contracts&id={id} auto-loads relational services
    $this->getJson('/schema?link=vms/onboarding/vendor-contracts&id=' . $contract['id'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.model.contract_number', 'CNT-2026-0001')
        ->assertJsonCount(1, 'data.model.services');

    // Lock Contract Services
    $this->postJson('/vms/onboarding/vendor-contracts/lock-services', ['id' => $contract['id']])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_locked', true)
        ->assertJsonPath('data.services.0.is_locked', true);

    // Updating a locked contract should return 422 error
    $this->putJson('/vms/onboarding/vendor-contracts/' . $contract['id'], [
        'vendor_id' => $vendor['id'],
        'contract_number' => 'CNT-2026-0001',
        'title' => 'Attempted Modification After Lock',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'billing_cycle' => 'monthly',
        'services' => [
            [
                'service_id' => $service['id'],
                'service_area_id' => $serviceArea['id'],
                'purchase_rate' => 100.00,
                'selling_rate' => 200.00,
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonPath('success', false);

    // Activate Contract
    $this->postJson('/vms/onboarding/vendor-contracts/activate', ['id' => $contract['id']])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_active', true);

    // Renew Contract (In-Place)
    $this->postJson('/vms/onboarding/vendor-contracts/renew', [
        'id' => $contract['id'],
        'start_date' => '2027-01-01',
        'end_date' => '2027-12-31',
        'billing_cycle' => 'quarterly',
        'replicate' => false,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.billing_cycle', 'quarterly');

    // Renew Contract (Replicate as New Contract with Relational Services)
    $replicatedContractResponse = $this->postJson('/vms/onboarding/vendor-contracts/renew', [
        'id' => $contract['id'],
        'contract_number' => 'CNT-2028-0001',
        'start_date' => '2028-01-01',
        'end_date' => '2028-12-31',
        'replicate' => true,
    ]);

    $replicatedContractResponse->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.contract_number', 'CNT-2028-0001')
        ->assertJsonCount(1, 'data.services');

    // Terminate Contract
    $this->postJson('/vms/onboarding/vendor-contracts/terminate', [
        'id' => $contract['id'],
        'reason' => 'Replaced by 2028 contract',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'terminated')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.services.0.is_active', false);

    // 8. Unified Dropdown Endpoints for VMS Tables
    $this->getJson('/dropdown/service_categories/name/id?is_active=1')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data');

    $this->getJson('/dropdown/vendors/code,name/id?is_active=1')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.label', 'VND-AFTS-01 Al-Futtaim Technical Services Co.');
});
