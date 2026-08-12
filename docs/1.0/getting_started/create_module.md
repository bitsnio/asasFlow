Here’s an improved, more professional and clean version of your documentation in `.md` format:

---

# 📦 Creating New Modules

This guide explains how to create and manage modules within our modular application framework.

---

## 🚀 Module Creation Command

Use the following Artisan command to create a new module:

```bash
php artisan module:make [ModuleName]
```

> Replace `[ModuleName]` with your desired module name (e.g., `Inventory`, `Users`, `Products`).  
> **Note:** Module names must follow PascalCase formatting.

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

### Enable/Disable Modules

```bash
# Enable a module
php artisan module:enable YourModule

# Disable a module
php artisan module:disable YourModule
```

### Additional Commands

```bash
# List all available modules
php artisan module:list

# Create a controller inside a module
php artisan module:make-controller ControllerName YourModule

# Create a model inside a module
php artisan module:make-model ModelName YourModule

# Create a migration inside a module
php artisan module:make-migration create_table_name YourModule
```

---

## 🧠 Best Practices

- **Single Responsibility**: Each module should represent a distinct feature or domain.
- **Use `menu.php` as the Source of Truth** for navigation, permissions, and routes.
- **Permissions Convention**: Follow the pattern `modulename.resource.action`.
- **Seeders**: Create meaningful seeders for initial module data.
- **Document Everything**: Maintain a `README.md` inside each module explaining its functionality and usage.

---

> ✅ **Tip:** Well-structured modules make scaling and maintenance easier!