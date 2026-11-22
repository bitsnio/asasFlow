# Automatic Module-Aware CORS & Modular Configuration

When accessing an AsasFlow host application from a browser frontend (such as Angular/Formly, React, or Vue running on `http://localhost:4200` or a separate production domain), Cross-Origin Resource Sharing (**CORS**) must cover not only `/api/*`, but also every module prefix (`/core/*`, `/vms/*`, `/crm/*`, `/field-ops/*`, etc.) and AsasFlow's core endpoints (`/schema`, `/dropdown/*`, `/settings/*`).

AsasFlow includes a zero-maintenance **CORS Configurator** (`Bitsnio\AsasFlow\Foundation\Http\Cors\CorsConfigurator`) and a modular configuration structure under `config/asasflow/*.php`.

---

## Table of Contents

1. [How Automatic Module-Aware CORS Works](#how-automatic-module-aware-cors-works)
2. [Modular Configuration Architecture (`config/asasflow/*.php`)](#modular-configuration-architecture-configasasflowphp)
3. [Environment Variables (`.env`)](#environment-variables-env)
4. [Production Configuration (Specific Origins & Credentials)](#production-configuration-specific-origins--credentials)
5. [Browser `Accept: */*` & JSON Exception Handling](#browser-accept--json-exception-handling)

---

## How Automatic Module-Aware CORS Works

Laravel's built-in `Illuminate\Http\Middleware\HandleCors` middleware only attaches `Access-Control-Allow-*` headers to paths matching `config('cors.paths')` (which defaults to `['api/*', 'sanctum/csrf-cookie']`).

During application boot, AsasFlow's `CorsConfigurator` automatically:

1. **Includes Core Package Paths**:
   Registers `api/*`, `sanctum/csrf-cookie`, `schema`, `schema/*`, `dropdown/*`, `settings`, `settings/*`, and `asasflow/*`.
2. **Auto-Discovers All Module Prefixes**:
   When `asasflow.cors.auto_discover_module_paths` is `true` (default), it inspects every discovered module in `Modules/` and appends `<kebab-name>/*`, `<lower-name>/*`, and `<alias>/*` (e.g., `core/*`, `vms/*`, `crm/*`, `field-ops/*`, `fieldops/*`, `payables/*`, `receivables/*`) to Laravel's `config('cors.paths')`.
   Whenever you create a new module with `php artisan asasflow:make-module Inventory`, `inventory/*` is automatically CORS-enabled without manual configuration.
3. **Exposes Cache & ETag Headers**:
   Automatically exposes `X-AsasFlow-Cache`, `X-AsasFlow-Cache-Key`, and `ETag` to browser JavaScript clients via `Access-Control-Expose-Headers`.

---

## Modular Configuration Architecture (`config/asasflow/*.php`)

For simplicity and readability, AsasFlow splits its configuration by functional domain under `config/asasflow/` and imports each file into `config/asasflow.php`:

```text
config/
├── asasflow.php                  # Root entry point importing functional configs
├── asasflow-cache.php            # Standalone cache feature config
└── asasflow/
    ├── modules.php               # Module paths, namespace, and scaffolding layout
    ├── cache.php                 # Core cache settings (config('asasflow.cache'))
    ├── tenancy.php               # Single-DB / multi-DB tenancy & site settings (config('asasflow.tenancy'))
    ├── generators.php            # Controller, request, and resource generator settings (config('asasflow.generators'))
    ├── dropdown.php              # Unified dropdown API, aliases, protected tables/columns (config('asasflow.dropdown'))
    ├── cors.php                  # Automatic module-aware CORS settings (config('asasflow.cors'))
    └── install.php               # Required modules & JWT settings (config('asasflow.install'))
```

Publishing the configuration publishes both `config/asasflow.php` and the `config/asasflow/` directory:

```bash
php artisan vendor:publish --tag=asasflow-config
```

---

## Environment Variables (`.env`)

You can customize CORS behavior directly from your host application's `.env` file without modifying PHP configuration files:

```dotenv
# Enable or disable AsasFlow automatic CORS configuration
ASASFLOW_CORS_ENABLED=true

# Automatically add <module>/* for all discovered modules
ASASFLOW_CORS_AUTO_MODULE_PATHS=true

# Comma-separated base paths (module paths are appended automatically)
ASASFLOW_CORS_PATHS="api/*,sanctum/csrf-cookie,schema,schema/*,dropdown/*,settings,settings/*,asasflow/*"

# Allowed HTTP methods ('*' or comma-separated: GET,POST,PUT,PATCH,DELETE,OPTIONS)
ASASFLOW_CORS_ALLOWED_METHODS="*"

# Allowed origins ('*' for development, or comma-separated origins for production)
ASASFLOW_CORS_ALLOWED_ORIGINS="*"

# Allowed request headers ('*' or comma-separated including X-Tenant-ID,X-Site-ID,Authorization)
ASASFLOW_CORS_ALLOWED_HEADERS="*"

# Response headers exposed to browser JavaScript clients
ASASFLOW_CORS_EXPOSED_HEADERS="X-AsasFlow-Cache,X-AsasFlow-Cache-Key,ETag"

# Preflight OPTIONS cache duration in seconds
ASASFLOW_CORS_MAX_AGE=0

# Whether to set Access-Control-Allow-Credentials: true
ASASFLOW_CORS_SUPPORTS_CREDENTIALS=false
```

---

## Production Configuration (Specific Origins & Credentials)

In production, if your frontend runs on specific domains (e.g., `https://app.example.com` and `https://admin.example.com`) or uses cookies/credentials (`ASASFLOW_CORS_SUPPORTS_CREDENTIALS=true`), specify the exact origins in `.env`:

```dotenv
ASASFLOW_CORS_ALLOWED_ORIGINS="https://app.example.com,https://admin.example.com,http://localhost:4200"
ASASFLOW_CORS_SUPPORTS_CREDENTIALS=true
ASASFLOW_CORS_MAX_AGE=86400
```

> **Note:** The W3C CORS specification does not allow `Access-Control-Allow-Origin: *` when `ASASFLOW_CORS_SUPPORTS_CREDENTIALS=true`. Always list explicit origins in `ASASFLOW_CORS_ALLOWED_ORIGINS` when `supports_credentials` is enabled.

---

## Browser `Accept: */*` & JSON Exception Handling

By default, browser `fetch()` requests send `Accept: */*` unless `Accept: application/json` is explicitly set. In standard Laravel applications, an unauthenticated request with `Accept: */*` to a non-`api/*` route (such as `/vms/catalog/service-categories` or `/core/access/users`) causes Laravel's `Authenticate` middleware to redirect to `route('login')`, resulting in a `Route [login] not defined` (`500`) HTML error or a blocked cross-origin redirect.

AsasFlow's `CorsConfigurator` and `ApiExceptionRenderer` automatically treat all resolved AsasFlow package and module routes as JSON API endpoints:

- Unauthenticated browser requests (even with `Accept: */*`) never redirect to `route('login')`; they always return a `401 Unauthenticated` unified JSON response with full `Access-Control-Allow-*` CORS headers.
- Validation (`422`), permission (`403`), not-found (`404`), and server (`500`) errors on any module or package route always return the unified JSON response envelope with CORS headers attached.
