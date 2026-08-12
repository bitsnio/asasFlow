# ⚡ `module:make-menu_controllers` Command

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

> ✅ **Best Practice:** Always commit your `menu.php` changes and regenerate controllers/routes before pushing code.

