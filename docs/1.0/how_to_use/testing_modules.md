# Host Application & Module Testing

AsasFlow includes a schema-driven testing engine (`Bitsnio\AsasFlow\Foundation\Testing\InteractsWithModuleApi`) and an Artisan test installer (`php artisan asasflow:tests`) so your host application can automatically verify **all modules** generated via `asasflow:generate-from-schema`—as well as define custom module-specific business logic tests.

---

## Table of Contents

1. [Overview](#overview)
2. [Host Application Setup](#host-application-setup)
3. [Running Automatic Schema-Driven CRUD Tests](#running-automatic-schema-driven-crud-tests)
4. [What `assertAllSchemaGeneratedModulesCrud()` Verifies](#what-assertallschemageneratedmodulescrud-verifies)
5. [Writing Custom Module Tests](#writing-custom-module-tests)
6. [`InteractsWithModuleApi` Helper Reference](#interactswithmoduleapi-helper-reference)
7. [Manual API Testing with `.http` Files](#manual-api-testing-with-http-files)

---

## Overview

In AsasFlow, testing is split into two layers:

1. **Automatic Schema-Generated Module Contract Tests (`tests/Feature/AsasFlow/Modules/SchemaGeneratedModulesCrudTest.php`)**:
   Zero-configuration tests that dynamically discover every module generated through `php artisan asasflow:generate-from-schema`, run their migrations, synthesize valid payloads directly from each resource's `schema/*.json` file, and test all standard CRUD endpoints, relational child arrays (`"relation": true`), validation rules, `/schema?link=...` endpoints, custom action routes, and `/dropdown/...` endpoints.
2. **Custom Module Feature Tests (`Modules/<Module>/tests/Feature/<Module>ApiTest.php`)**:
   Module-specific tests where you test custom business workflows, state transitions, custom actions (such as `lock-services`, `renew`, `terminate`, `duplicate`, `assign-areas`), and domain guards.

---

## Host Application Setup

### 1. Install & Synchronize AsasFlow Tests

Run the `asasflow:tests` Artisan command in your host Laravel application to verify/install Pest and publish AsasFlow's feature and module test suites into `tests/Feature/AsasFlow`:

```bash
# Install and sync all AsasFlow feature & module contract tests
php artisan asasflow:tests

# Sync only the schema-generated Modules test suite
php artisan asasflow:tests --feature=Modules

# Replace locally modified host tests with the latest package version
php artisan asasflow:tests --update

# Force overwrite all synced tests
php artisan asasflow:tests --force
```

### 2. Configure `phpunit.xml` to Discover Module Tests

Ensure your host application's `phpunit.xml` includes both the application `tests/` directories and each module's `Modules/*/tests/` directories:

```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
        <directory>Modules/*/tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
        <directory>Modules/*/tests/Feature</directory>
    </testsuite>
</testsuites>
```

### 3. Configure `tests/Pest.php`

Bind `Tests\TestCase` and `RefreshDatabase` to both `Feature` and `../Modules/*/tests/Feature` in your host application's `tests/Pest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../Modules/*/tests/Feature');
```

---

## Running Automatic Schema-Driven CRUD Tests

Once you have generated your modules using `asasflow:generate-controllers` and `asasflow:generate-from-schema`:

```bash
# Generate controllers, routes, models, migrations, requests, and resources for all modules
php artisan asasflow:generate-controllers --all
php artisan asasflow:generate-from-schema --all
```

You can immediately run the automatic schema-driven CRUD test suite across **all generated modules**:

```bash
# Run all AsasFlow synced tests (including all schema-generated modules)
./vendor/bin/pest tests/Feature/AsasFlow/Modules

# Or using Composer
composer exec pest -- tests/Feature/AsasFlow/Modules
```

### Testing a Single Module Only

To run the automatic schema CRUD test suite against a **single specific module** (e.g. `Vms` or `Crm`), pass the `ASASFLOW_TEST_MODULE` environment variable:

```bash
ASASFLOW_TEST_MODULE=Vms ./vendor/bin/pest tests/Feature/AsasFlow/Modules
```

---

## What `assertAllSchemaGeneratedModulesCrud()` Verifies

For every discovered module that has `config/menu.php`, `schema/*.json`, `database/migrations/*.php`, generated models, and generated controllers, `assertAllSchemaGeneratedModulesCrud()` automatically executes the following 11 checks on every resource:

| Step | Check | Assertion |
| :--- | :--- | :--- |
| **1. Route Registration** | Inspects `config/menu.php` | Verifies `GET`, `POST`, `GET /{id}`, `PUT /{id}`, `DELETE /{id}`, and all custom `actions` routes are registered in Laravel's router. |
| **2. Create Schema** | `GET /schema?link={routePath}` | Asserts `200 OK` and verifies `data.schema` and default `data.model` are returned. |
| **3. Validation (`422`)** | `POST /{routePath}` with `{}` | When the JSON Schema defines `required` fields, asserts `422 Unprocessable Entity` with `{ "success": false, "message": "Validation failed", "errors": { ... } }`. |
| **4. Create (`201`)** | `POST /{routePath}` | Synthesizes a valid payload from the JSON Schema (respecting `enum`, `format`, `minLength`, `minimum`, nested `object`s, and `"relation": true` child arrays) and asserts `201 Created` + child relation persistence. |
| **5. Edit Schema Prefill** | `GET /schema?link={routePath}&id={id}` | Asserts `200 OK` and verifies `data.model` is populated with the database record (including auto-loaded `"relation": true` child arrays). |
| **6. Paginated Index** | `GET /{routePath}?page=1&per_page=15` | Asserts `200 OK` and verifies the unified pagination `meta` (`current_page`, `per_page`, `total`, `last_page`, `from`, `to`, `has_more_pages`). |
| **7. Show (`200`)** | `GET /{routePath}/{id}` | Asserts `200 OK` and verifies `data.id === $id`. |
| **8. Update (`200`)** | `PUT /{routePath}/{id}` | Synthesizes a mutated update payload from the JSON Schema (and child relations) and asserts `200 OK`. |
| **9. Dropdown (`200`)** | `GET /dropdown/{table}/{label}/id` | Automatically detects label columns (`name`, `title`, `code`, `contract_number`, etc.) and verifies `[{ "label": "...", "value": ... }]` response structure. |
| **10. Custom Actions** | `POST /{routePath}/{action}` | Calls each custom action route with `{ "id": $id }` and verifies it returns a valid unified JSON response (`200`, `201`, `422`, or `501`), never a `500` crash. |
| **11. Destroy (`200`)** | `DELETE /{routePath}/{id}` | Asserts `200 OK` (or `422` if a custom action locked the record against deletion). |

---

## Writing Custom Module Tests

When you add custom business logic or custom actions to a module's controllers, create a Pest feature test inside that module's `tests/Feature/` directory:

```text
Modules/<Module>/tests/Feature/<Module>ApiTest.php
```

Import the `Bitsnio\AsasFlow\Foundation\Testing\InteractsWithModuleApi` trait to reuse schema payload synthesis, route/schema assertions, and CRUD lifecycle helpers while focusing your test on custom domain behavior.

### Example 1: Custom Actions & Relational Workflow Test (`Modules/Vms/tests/Feature/VmsApiTest.php`)

```php
<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Testing\InteractsWithModuleApi;

uses(InteractsWithModuleApi::class);

beforeEach(function () {
    // Registers modules, runs module migrations, and bypasses auth/permission gates
    $this->bootAndMigrateSchemaGeneratedModules('Vms');
});

it('duplicates a service checklist with its tasks via the custom duplicate action', function () {
    // 1. Create a checklist using a schema-generated payload with custom overrides
    $payload = $this->buildPayloadFromSchema('Vms', 'ServiceChecklists', [
        'name' => 'Quarterly HVAC Inspection',
        'code' => 'CHK-HVAC-Q1',
        'tasks' => [
            [
                'title' => 'Inspect and clean air filters',
                'input_type' => 'photo',
                'is_required' => true,
            ],
        ],
    ]);

    $checklist = $this->assertResourceCrudLifecycle(
        uri: '/vms/catalog/service-checklists',
        createPayload: $payload,
        deleteAtEnd: false,
    );

    // 2. Test custom action: POST /vms/catalog/service-checklists/duplicate
    $response = $this->postJson('/vms/catalog/service-checklists/duplicate', [
        'id' => $checklist['id'],
        'name' => 'Annual HVAC Deep Inspection',
        'code' => 'CHK-HVAC-ANNUAL',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Annual HVAC Deep Inspection')
        ->assertJsonPath('data.code', 'CHK-HVAC-ANNUAL')
        ->assertJsonCount(1, 'data.tasks');
});

it('locks vendor contract services and prevents modifying a locked contract', function () {
    // Create a contract with relational services lines
    $contractPayload = $this->buildPayloadFromSchema('Vms', 'VendorContracts', [
        'contract_number' => 'CNT-2026-0001',
        'is_locked' => false,
        'services' => [
            [
                'service_id' => 1,
                'service_area_id' => 1,
                'purchase_rate' => 150.00,
                'selling_rate' => 225.00,
                'is_locked' => false,
                'is_active' => true,
            ],
        ],
    ]);

    $contract = $this->postJson('/vms/onboarding/vendor-contracts', $contractPayload)
        ->assertCreated()
        ->json('data');

    // Lock services via custom action
    $this->postJson('/vms/onboarding/vendor-contracts/lock-services', [
        'id' => $contract['id'],
    ])
        ->assertOk()
        ->assertJsonPath('data.is_locked', true)
        ->assertJsonPath('data.services.0.is_locked', true);

    // Attempting to update a locked contract must fail with 422
    $this->putJson('/vms/onboarding/vendor-contracts/' . $contract['id'], $contractPayload)
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
```

### Example 2: Testing with Authenticated Users & Permissions (Without Bypassing Auth)

If you want to test role/permission enforcement (`401 Unauthenticated` or `403 Forbidden`) rather than bypassing middleware:

```php
<?php

declare(strict_types=1);

use Modules\Core\Models\Users;

it('blocks unauthenticated requests and enforces module permissions', function () {
    // 1. Unauthenticated request returns 401
    $this->getJson('/vms/catalog/service-categories')
        ->assertStatus(401)
        ->assertJsonPath('success', false);

    // 2. Authenticate as a user without 'vms.catalog.service-categories.view' permission
    $user = Users::factory()->create();
    $this->actingAs($user, 'api')
        ->getJson('/vms/catalog/service-categories')
        ->assertStatus(403);
});
```

---

## `InteractsWithModuleApi` Helper Reference

The `Bitsnio\AsasFlow\Foundation\Testing\InteractsWithModuleApi` trait provides the following methods for custom tests:

| Method | Description |
| :--- | :--- |
| `bypassModuleAuth(): static` | Bypasses `auth:api`, `Authorize`, and `asasflow.tenancy` middleware and allows all Gate checks so controller logic can be tested directly. |
| `discoverSchemaGeneratedModules(?string $onlyModule = null): array` | Returns all `ModuleDefinition` objects that have `config/menu.php`, `schema/*.json`, `database/migrations/*.php`, models, and controllers. |
| `bootAndMigrateSchemaGeneratedModules(?string $onlyModule = null): array` | Registers module providers/routes and runs all migrations for discovered modules (or a single `$onlyModule`). |
| `assertAllSchemaGeneratedModulesCrud(?string $onlyModule = null): int` | Runs the complete 11-step route, schema, validation, CRUD, relational, custom action, and dropdown verification across all (or one) schema-generated modules. |
| `assertModuleRoutesRegistered(string $moduleName): void` | Asserts every CRUD and custom action route in `Modules/{$moduleName}/config/menu.php` is registered. |
| `assertModuleSchemasAccessible(string $moduleName): void` | Asserts `GET /schema?link=...` returns `200 OK` with `schema` and `model` for every resource in the module. |
| `buildPayloadFromSchema(string $moduleName, string $modelClass, array $overrides = [], bool $forUpdate = false): array` | Synthesizes a valid request payload from `Modules/{$moduleName}/schema/{$modelClass}.json`, merged with `$overrides`. Pass `$forUpdate = true` to generate mutated values for `PUT` requests. |
| `assertResourceCrudLifecycle(string $uri, array $createPayload, array $updatePayload = [], bool $deleteAtEnd = true, ?MenuDefinition $definition = null, ?SchemaDefinition $schema = null): array` | Runs `POST` (`201`), `GET` paginated index (`200`), `GET /{id}` (`200`), `PUT /{id}` (`200`), and optional `DELETE /{id}` (`200`), returning the updated resource array. |

---

## Manual API Testing with `.http` Files

Alongside automated Pest tests, we recommend keeping a `restapi/<module>.http` file in each module (such as `Modules/Core/restapi/access.http` and `Modules/Vms/restapi/vms.http`) for interactive manual testing in VS Code (**REST Client** extension) or PhpStorm (**HTTP Client**).

### Chaining Requests Automatically

Use named requests (`# @name createCategory`) so subsequent requests automatically reference the created record IDs and JWT token without manual copy-pasting:

```http
@baseUrl = http://localhost:8000
@token = {{login.response.body.data.token}}
@categoryId = {{createCategory.response.body.data.id}}

### 1. Login
# @name login
POST {{baseUrl}}/core/access/users/login
Accept: application/json
Content-Type: application/json

{
    "email": "admin@primary.localhost",
    "password": "password123"
}

### 2. Create Service Category
# @name createCategory
POST {{baseUrl}}/vms/catalog/service-categories
Authorization: Bearer {{token}}
Accept: application/json
Content-Type: application/json

{
    "name": "HVAC & Climate Control",
    "code": "CAT-HVAC",
    "description": "Heating, ventilation, and air conditioning services",
    "is_active": true
}

### 3. Get Created Service Category
GET {{baseUrl}}/vms/catalog/service-categories/{{categoryId}}
Authorization: Bearer {{token}}
Accept: application/json
```
