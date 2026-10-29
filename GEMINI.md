# AsasFlow (`bitsnio/asasflow`) — Workspace Guide

AsasFlow is a Laravel package (`Bitsnio\AsasFlow\`) for building modular API applications with a menu-driven and schema-driven development workflow. Each module's `config/menu.php` acts as the single source of truth for navigation hierarchy, resource endpoints, custom actions, and permission definitions.

## Tech Stack & Requirements

- **Language**: PHP `8.2.* | 8.3.* | 8.4.*`
- **Framework**: Laravel `^13.0` (`illuminate/support`)
- **Key Dependencies**:
  - `spatie/laravel-permission` (`^7.3.0`) — Role & permission management
  - `spatie/laravel-settings` (`^3.7.2`) — Module settings persistence
  - `php-open-source-saver/jwt-auth` (`^2.9.0`) — API JWT authentication
  - `binarytorch/larecipe` (`^2.9`) — Documentation viewer (`routes/docs.php`)
  - `wikimedia/composer-merge-plugin` (`^2.1`) — Merges module `composer.json` files
- **Testing**: Pest `4.x` / PHPUnit (`./vendor/bin/pest` or `./vendor/bin/phpunit`)

## Directory & Architecture Layout

- `src/AsasFlowServiceProvider.php`: Main service provider registering core bindings, feature providers (`SettingsServiceProvider`, `CacheServiceProvider`, `TenancyServiceProvider`), module discovery/registration, migrations, and console commands.
- `src/Foundation/`:
  - `Contracts/`: Core interfaces (`GeneratorInterface`, `ModuleRepositoryInterface`, `SchemaArtifactGeneratorInterface`).
  - `Support/`: Core infrastructure services (`ModuleDiscovery`, `ModuleRegistrar`, `ModuleRepository`, `ModuleScaffolder`, `MenuDefinition`, `MenuService`, `PermissionDefinition`, `PermissionResolver`, `PermissionService`, `SchemaDefinition`, `StubRenderer`, `GeneratedBlock`, `FileHandler`).
  - `Services/`: Setup services such as `JwtSetupService`.
- `src/Generators/`: Code generators driven by `config/menu.php` and JSON schemas:
  - `Module/` (`ModuleGenerator`), `Controller/` (`ControllerGenerator`, `ControllerActionSynchronizer`), `Route/` (`RouteGenerator`, `RouteFileBuilder`, `RouteFileSynchronizer`), `Request/` (`RequestGenerator`), `Resource/` (`ResourceGenerator`), `Model/` (`ModelGenerator`), `Migration/` (`MigrationGenerator`), `Schema/` (`SchemaGenerator`, `SchemaArtifactGenerator`), `Cache/` (`CacheObserverGenerator`), and `Stubs/`.
- `src/Features/`: Self-contained feature verticals:
  - `Cache/`: Attribute/observer-based caching, tenant-isolated cache keys, cache warming jobs, and CLI commands (`asasflow:cache:clear`, `asasflow:cache:warm`, `asasflow:cache:stats`).
  - `Settings/`: Module-scoped settings discovery, repository, and endpoints.
  - `Tenancy/`: Single-DB (`tenant_id` / `site_id` columns) and multi-DB tenant resolution, middleware, traits, and models.
- `src/Console/`: Artisan commands registered via `ConsoleServiceProvider`:
  - `asasflow:install` — Install and configure AsasFlow in host application
  - `asasflow:make-module` — Scaffold a new module based on `config/asasflow.php`
  - `asasflow:generate-controllers` — Generate controllers, requests, resources, and routes from a module's `config/menu.php`
  - `asasflow:generate-from-schema` — Generate models, migrations, requests, and resources from JSON Schema definitions
  - `asasflow:module-install` — Install required modules
  - `asasflow:docs` — Update/publish LaRecipe documentation
  - `asasflow:tests` — Install test scaffolding
- `config/`:
  - `asasflow.php` — Main package configuration (module scaffolding layout, cache, tenancy, generator defaults, JWT & required modules).
  - `asasflow-cache.php` — Cache feature configuration.
- `database/migrations/`: Package migrations for module settings, tenants, tenant domains, and cache registry.
- `docs/1.0/`: LaRecipe markdown documentation for package users.

## Coding Conventions & Guidelines

1. **Feature-First Design**: Keep self-contained features under `src/Features/<FeatureName>/` with their own `ServiceProvider`, `Contracts`, `Services`, `Models`, `Http`, and `routes`.
2. **Idempotent Code Generation**: Generators use `GeneratedBlock` markers and synchronizers (`ControllerActionSynchronizer`, `RouteFileSynchronizer`) so regenerating from `menu.php` or schemas preserves custom user code outside managed blocks.
3. **Namespace & PSR-4**:
   - Package namespace: `Bitsnio\AsasFlow\` mapped to `src/`.
   - Generated modules default to `Modules\<ModuleName>\` (configurable via `config('asasflow.modules.namespace')`).
4. **Permissions Convention**:
   - Resource CRUD permissions follow the menu path: `<group>.<resource>.(view|create|update|delete)`.
   - Custom resource actions append `.execute`: `<group>.<resource>.<action>.execute`.
5. **Testing**:
   - Run tests with `./vendor/bin/pest` (or `./vendor/bin/phpunit`).
   - Feature-specific tests can reside alongside features (e.g., `src/Features/Settings/Tests`, `src/Generators/Tests`) or in `tests/Unit` and `tests/Feature`.
