# Generate Controllers

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

## 🔗 Schema-Driven Generation & Relational Child Tables (`"relation": true`)

After generating controllers and routes from `menu.php`, generate models, migrations, form requests, and resources from your JSON Schemas (`Modules/{Module}/schema/*.json`):

```bash
php artisan asasflow:generate-from-schema {module}
```

### JSON Column vs. Relational Table for `array` / `object` Properties

By default, any property of `"type": "array"` or `"type": "object"` is generated as a `json` / `jsonb` column on the parent table and cast to `'array'` on the parent model.

When an `array` (or `object`) property represents a **child relational table** submitted within the same form payload (for example, `services` inside `VendorContracts.json`), add `"relation": true` to the property:

```json
"services": {
    "type": "array",
    "relation": true,
    "title": "Locked Service Rates",
    "minItems": 1,
    "items": {
        "type": "object",
        "required": ["service_id", "service_area_id", "purchase_rate", "selling_rate"],
        "properties": {
            "service_id": { "type": "integer" },
            "service_area_id": { "type": "integer" },
            "purchase_rate": { "type": "number", "default": 0 },
            "selling_rate": { "type": "number", "default": 0 },
            "is_locked": { "type": "boolean", "default": true },
            "is_active": { "type": "boolean", "default": true }
        }
    }
}
```

When `"relation": true` is present, AsasFlow automatically derives all naming by convention (no database/model names needed in the schema):
- **Parent Migration (`vendor_contracts`)**: Excludes the `services` JSON column.
- **Child Migration (`vendor_contract_services`)**: Generated automatically with `$table->foreignId('vendor_contract_id')->constrained('vendor_contracts')->cascadeOnDelete();` and all `items.properties` columns.
- **Parent Model (`VendorContracts`)**: Generates `public function services(): HasMany`.
- **Child Model (`VendorContractServices`)**: Generated automatically with `$fillable`, `$casts`, and `public function vendorContract(): BelongsTo`.
- **Controller (`VendorContractsController`)**: Wraps `store()` and `update()` in `DB::transaction()` to save the parent and sync child records, and eager-loads `with(['services'])` in `index()` and `show()`.

---

> ✅ **Best Practice:** Always commit your `menu.php` changes and regenerate controllers/routes before pushing code.

