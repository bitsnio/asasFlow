# `MakeMenuCommand` Documentation

This command helps manage module menus by allowing developers to **add sub-modules** and **actions** dynamically through the CLI.

---

## Command Signature

```bash
php artisan module:menu-manager {module} [--add-sub-module] [--add-action]
```

### Arguments
| Argument | Description |
| :------- | :---------- |
| `module` | The name of the module where the menu is managed. |

### Options
| Option | Description |
| :----- | :----------- |
| `--add-sub-module` | Adds a new sub-module to the module's menu. |
| `--add-action` | Adds a new action to an existing sub-module. |

---

## Features

- Create sub-modules with custom title, icon, middleware, route types, and order.
- Create actions inside sub-modules similarly.
- Sorts sub-modules and actions by their **order** property.
- Automatically saves updates to the module's `Config/menu.php` file.

---

## How It Works

### 1. Add a Sub-Module

- Prompts for a **title**, **icon class**, **middleware**, and **order**.
- Automatically generates a **StudlyCase** `name` from the title.
- Validates if the sub-module already exists.
- Saves it into the `menu.php` configuration.

### 2. Add an Action

- Prompts for a **sub-module selection**.
- Then asks for **action title**, **icon**, **middleware**, and **order**.
- Automatically generates a **StudlyCase** action `name`.
- Validates if the action already exists inside the selected sub-module.

---

## Important Methods

| Method | Purpose |
| :----- | :------ |
| `addSubModule()` | Handles adding a new sub-module. |
| `addAction()` | Handles adding a new action inside a sub-module. |
| `saveMenuConfig()` | Saves the updated menu configuration into `menu.php`. |
| `askValid()` | Ensures user input is valid (non-empty, etc). |
| `askMiddleware()` | Allows adding multiple middleware entries. |
| `askOrder()` | Automatically suggests order based on existing entries. |

---

## Example Usage

### Add a Sub-Module

```bash
php artisan module:menu-manager Blog --add-sub-module
```

### Add an Action to Sub-Module

```bash
php artisan module:menu-manager Blog --add-action
```

---

## Configuration File

The menu configuration is saved inside:

```
/Modules/{ModuleName}/Config/menu.php
```

Example structure after adding:

```php
return [
    'module' => [
        'sub_module' => [
            [
                'name' => 'Posts',
                'title' => 'Posts',
                'routes_type' => 'full',
                'icon' => 'fas fa-list',
                'middleware' => ['api', 'auth'],
                'order' => 1,
                'actions' => [
                    [
                        'name' => 'CreatePost',
                        'title' => 'Create Post',
                        'routes_type' => 'single',
                        'icon' => 'fas fa-plus',
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

## Requirements

- Laravel Framework
- Bitsnio `RepositoryInterface` module system

---

Would you also like me to generate a **ready-to-use README.md** file for this? 🚀  
I can even format it for GitHub if you want! 📄✨