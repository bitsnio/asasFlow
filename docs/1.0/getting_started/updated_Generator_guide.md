# AsasFlow Generator User Guide

Welcome to the **AsasFlow** code generation suite. This guide walks you through how to use AsasFlow to scaffold modular Laravel applications, manage database schemas, and safely update controllers, requests, resources, and routes as your application grows.

---

## 1. Core Workflow: Two-Phase Generation

AsasFlow uses a two-phase workflow to give you rapid scaffolding while keeping your business logic safe:

* **Phase 1: Menu Scaffolding (`asasflow:generate-controllers`)**
Builds your initial folder structures, controllers, form requests, API resources, empty JSON schemas, and API routes based on your module's `menu.php` configuration.
* **Phase 2: Schema Syncing (`asasflow:generate-from-schema`)**
Reads your populated JSON schemas (`schema/*.json`) to automatically generate or update your Eloquent models, database migrations, validation rules, and resource fields.

---

## 2. Step-by-Step Usage Guide

### Step 1: Define Your Module Menu (`menu.php`)

Define your module's resources, permissions, and custom actions inside your module's menu configuration file (e.g., `Modules/Core/Config/menu.php`):

```php
return [
    'module' => [
        'name' => 'Core',
        'title' => 'System Administration',
        'children' => [
            [
                'name' => 'access',
                'title' => 'Access Management',
                'children' => [
                    [
                        'name' => 'users',
                        'title' => 'Users',
                        'type' => 'resource',
                        'model' => true,
                        'actions' => [
                            'resetPassword' => [
                                'method' => 'POST',
                                'permissionLabel' => 'Reset user passwords',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

```

### Step 2: Run Phase 1 Scaffolding

Run the menu generator command to scaffold your controllers, requests, resources, empty schema files, and API routes:

```bash
php artisan asasflow:generate-controllers Core

```

**What this creates:**

* `UsersController.php` (with standard CRUD methods and a stub for `resetPassword`)
* `UsersRequest.php` & `UsersResource.php`
* `routes/api.php` (registering apiResource and custom action routes)
* `schema/Users.json` (empty JSON schema template)

### Step 3: Populate Your JSON Schema

Open the generated schema file (`Modules/Core/schema/Users.json`) and define your table columns, types, and validation rules:

```json
{
  "title": "Users",
  "type": "object",
  "properties": {
    "name": {
      "type": "string",
      "maxLength": 255
    },
    "email": {
      "type": "string",
      "format": "email"
    }
  },
  "required": ["name", "email"]
}

```

### Step 4: Run Phase 2 Schema Generation

Once your schema is defined, run the schema generator to build your database migration, fillable attributes, casts, validation rules, and resource fields:

```bash
php artisan asasflow:generate-from-schema Core

```

---

## 3. Safe Incremental Updates (Adding New Actions Later)

As your application evolves, you can add new actions or endpoints to your `menu.php` or schema at any time without worrying about losing custom code you've written inside your controllers or route files.

* **Adding New Actions to Controllers:** If you add a new action (e.g., `'verifyEmail'`) to `menu.php` and re-run the generator, AsasFlow detects that your controller already exists. It **preserves all your existing custom code** and safely appends *only* the missing `verifyEmail` method to the bottom of the controller class.
* **Adding New Routes:** Similarly, running the generator when new routes or actions are added will incrementally inject the missing route definitions into your existing `routes/api.php` file without overwriting your custom route groups or file comments.

---

## 4. Command Reference & Options

### `asasflow:generate-controllers`

Scaffolds controllers, requests, resources, schemas, and routes from `menu.php`.

```bash
php artisan asasflow:generate-controllers ModuleName [options]

```

**Available Options:**

* `--only=all` — Restrict generation to a specific component (`controllers`, `requests`, `resources`, `schemas`, or `routes`).
* `--force` — Force regeneration of files.
* `--trace` — Display the route hierarchy tree.

---

### `asasflow:generate-from-schema`

Generates or syncs models, migrations, validation rules, resource fields, and controller methods from JSON schemas.

```bash
php artisan asasflow:generate-from-schema ModuleName [options]

```

**Available Options:**

* `--schema=ModelName` — Target and generate artifacts for a single specific schema/model only (e.g., `--schema=Users`).
* `--only=all` — Restrict sync to specific artifacts (`models`, `migrations`, `requests`, `resources`, or `controllers`).
* `--force` — Fully regenerate selected artifacts.
* `--trace` — Display the schema generation hierarchy tree.
* `--dry-run` — Preview generation targets without writing files.