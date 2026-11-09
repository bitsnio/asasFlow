# Creating New Modules

This guide explains how to create and manage modules within our modular application framework.

---

## Module Creation Command

Use the following Artisan command to create a new module:

```bash
php artisan asasflow:make-module Blog
```

You can also create **multiple modules in a single command** using space-separated or comma-separated names:

```bash
# Space-separated
php artisan asasflow:make-module Blog Shop Billing

# Comma-separated
php artisan asasflow:make-module Blog,Shop,Billing

# Mixed
php artisan asasflow:make-module Blog,Shop Inventory
```

> Module names are automatically converted to StudlyCase (e.g. `blog_posts` -> `BlogPosts`).
> Use `--force` to regenerate starter files if a module directory already exists.

---

## 🏗️ Generated Directory Structure

After creation, the following structure is automatically generated:

```
Modules/
└── YourModule/
    ├── App/
    │   ├── Http/
    │   │   └── Controllers/
    │   └── Providers/
    ├── config/
    │   ├── config.php
    │   └── menu.php
    ├── Database/
    │   └── Seeders/
    ├── resources/
    │   ├── assets/
    │   │   ├── js/
    │   │   └── sass/
    │   └── views/
    ├── routes/
    ├── composer.json
    ├── module.json
    ├── package.json
    └── vite.config.js
```

---

## 📋 Understanding the `menu.php` File

The `menu.php` file in the `config` directory defines:

- **Navigation structure**
- **Route auto-generation**
- **Permission requirements**
- **Controller bindings**

### Example `menu.php` Structure

```php
<?php

return [
    'module' => [
        'name' => 'Inventory',
        'title' => 'Inventory',
        'icon' => 'fas fa-cube',
        'order' => 1,
        'routes_type' => '',
        'sub_module' => [
            [
                'name' => 'Inventory',
                'title' => 'Inventory',
                'routes_type' => 'full',
                'icon' => 'fas fa-list',
                'middleware' => ['api', 'auth'],
                'order' => 1,
                'actions' => [
                    [
                        'name' => 'Inventory',
                        'title' => 'Inventory',
                        'routes_type' => 'single',
                        'icon' => 'fas fa-list',
                        'middleware' => ['api', 'auth'],
                        'order' => 1,
                    ],
                ],
            ],
        ],
    ],
];
```

---

## 🔥 What to Do After Module Creation

1. Configure navigation in `config/menu.php`.
2. Create database migrations (schema-based).
3. Build controllers and APIs according to the menu structure.
4. Set up permissions in the database.
5. Customize module settings via `config.php`.

---

## 🛠️ Module Management Commands

### Scaffolding & Generation

```bash
# Generate controllers, requests, resources, and routes from menu.php
php artisan asasflow:generate-controllers YourModule

# Generate models, migrations, requests, and resources from JSON Schema
php artisan asasflow:generate-from-schema YourModule

# Create a database seeder for a module
php artisan asasflow:make-seeder PostSeeder YourModule

# Create an Eloquent factory for a module
php artisan asasflow:make-factory PostFactory YourModule

# Run seeders for a module (or all modules)
php artisan asasflow:seed YourModule
```

For more details on seeders and factories, refer to the [Database Seeders & Factories Guide](/{{route}}/{{version}}/getting_started/seeders_and_factories).

---

## 🧠 Best Practices

- **Single Responsibility**: Each module should represent a distinct feature or domain.
- **Use `menu.php` as the Source of Truth** for navigation, permissions, and routes.
- **Permissions Convention**: Follow the pattern `modulename.resource.action`.
- **Seeders**: Create meaningful seeders for initial module data.
- **Document Everything**: Maintain a `README.md` inside each module explaining its functionality and usage.

---

> ✅ **Tip:** Well-structured modules make scaling and maintenance easier!