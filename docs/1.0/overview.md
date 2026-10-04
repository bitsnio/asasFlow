# AsasFlow Overview

AsasFlow is a Laravel package for building modular applications with a menu-driven development workflow. A module's `config/menu.php` defines its navigation hierarchy, resource endpoints, resource-level custom actions, and permission labels. Generators use that definition to scaffold API controllers, form requests, API resources, and `Routes/api.php`.

## Current development focus

The current workflow focuses on:

1. Installing and configuring the AsasFlow package.
2. Creating a Laravel module using the installed Laravel Modules integration.
3. Defining the module's menu in `config/menu.php`.
4. Generating controllers, requests, API resources, and API routes from the menu.
5. Defining JSON Schema files for forms and data structures.
6. Exploring schema-driven model and migration generation. Treat the last item as experimental until verified against your project and database.

## Core concepts

### Menu-driven development

`menu.php` is the source of truth for menu hierarchy, resource endpoints, custom resource actions, and permission descriptions. Custom actions are methods on the resource's controller; they do not create a separate controller per action.

### JSON Schema and frontend forms

Schemas use JSON Schema concepts compatible with the Formly JSON Schema example format. The frontend can use the schema to render forms and pair it with a model object containing initial values. Backend validation, model generation, and migration generation are separate concerns and should be tested independently.

### Permissions

AsasFlow integrates with Spatie Laravel Permission. Resource CRUD permission names follow the resource's menu path, for example `organization.companies.view`. Resource custom actions use the action key plus `.execute`, for example `organization.companies.approve.execute`. Human-readable permission labels are configured in `menu.php`.

## Documentation map

* [Installation](./installation.md)
* [Creating modules](./getting_started/create_module.md)
* [Menu format and generators](./getting_started/menu_and_generators.md)
* [Schema-driven forms and database generation](./getting_started/schema_driven_models_and_migrations.md)
* [Permissions](./getting_started/permissions.md)

## Compatibility note

Generated output depends on the exact versions of Laravel, the Laravel Modules fork, and AsasFlow installed in the host project. Confirm actual Artisan command signatures with `php artisan list` before relying on commands that are not shown in this guide.
