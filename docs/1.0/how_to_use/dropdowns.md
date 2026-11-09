# Schema Dropdown

AsasFlow provides a unified, multi-tenant-aware, and cache-integrated **Dropdown API** so Formly JSON Schemas and module controllers can fetch `[{ "label": "...", "value": "..." }]` options from database tables or virtual system sources (`modules`, `permissions`, and `settings`).

---

## Table of Contents

1. [Endpoint Overview](#endpoint-overview)
2. [Using Dropdowns in JSON Schemas](#using-dropdowns-in-json-schemas)
3. [Database Table Dropdowns](#database-table-dropdowns)
4. [Virtual Sources (`modules`, `permissions`, `settings`)](#virtual-sources-modules-permissions-settings)
5. [Filtering, Search, Grouping & Limits](#filtering-search-grouping--limits)
6. [Using `DropdownTrait` in Custom Controllers](#using-dropdowntrait-in-custom-controllers)
7. [Caching & Automatic Invalidation](#caching--automatic-invalidation)
8. [Security & Configuration](#security--configuration)

---

## Endpoint Overview

AsasFlow automatically registers the dropdown endpoint under both `/api/dropdown/...` and `/dropdown/...` (protected by `auth:api`):

```http
GET /api/dropdown/{table}/{label?}/{value?}
GET /dropdown/{table}/{label?}/{value?}
```

| Parameter | Default | Description |
| :--- | :--- | :--- |
| `table` | *(required)* | Database table name (e.g. `sites`, `roles`, `users`) or virtual source (`modules`, `permissions`, `settings`). |
| `label` | `name` | Column name, comma-separated composite columns (`firstName,lastName`), or JSON path (`meta.region`) used for the option's `label`. |
| `value` | `id` | Column name or JSON path used for the option's `value`. |

### Standard Response Envelope

```json
{
    "success": true,
    "message": "Dropdown data retrieved successfully.",
    "data": [
        {
            "label": "Headquarters",
            "value": 1
        },
        {
            "label": "Lahore Branch",
            "value": 2
        }
    ]
}
```

---

## Using Dropdowns in JSON Schemas

In your module's `schema/*.json` files, configure the `widget.formlyConfig` of any `select` property to point to `/dropdown/{table}/{label}/{value}`:

```json
{
    "site_id": {
        "type": "integer",
        "widget": {
            "formlyConfig": {
                "type": "select",
                "props": {
                    "label": "Site / Branch",
                    "placeholder": "Select Site",
                    "url": "/dropdown/sites/name/id?is_active=1"
                }
            }
        }
    },
    "currency": {
        "type": "string",
        "widget": {
            "formlyConfig": {
                "type": "select",
                "props": {
                    "label": "Currency",
                    "url": "/dropdown/settings/default_currency?module=core"
                }
            }
        }
    }
}
```

---

## Database Table Dropdowns

### 1. Standard Columns
```http
GET /api/dropdown/sites/name/id
GET /api/dropdown/sites/name/code
```

### 2. Composite Labels & Automatic `firstName,lastName` Fallback
Pass comma-separated column names in `{label}` to concatenate multiple columns:
```http
GET /api/dropdown/sites/code,name/id
# Returns: [{ "label": "HQ Headquarters", "value": 1 }]

GET /api/dropdown/users/firstName,lastName/id
# Returns: [{ "label": "Haseeb Ahmad", "value": 1 }]
```

> **Automatic Fallback:** If a schema requests `/api/dropdown/users/name/id` on a table that does not have a `name` column, `DropdownService` automatically falls back to `firstName,lastName` (or `first_name,last_name`).

### 3. JSON Dot-Notation Columns
Extract nested properties from JSON columns using dot notation:
```http
GET /api/dropdown/sites/meta.region/id
```

### 4. Automatic Multi-Tenancy & Soft Deletes
- **Soft Deletes:** If the table has a `deleted_at` column, soft-deleted rows are automatically excluded (pass `?with_trashed=1` to include them).
- **Single-DB Multi-Tenancy:** When `asasflow.tenancy.enabled` is `true` (`single` strategy) and the table is not in `exempt_tables`, queries are automatically scoped to the active tenant (`TenantContext::getTenantId()` or `auth('api')->user()->tenant_id`).

---

## Virtual Sources (`modules`, `permissions`, `settings`)

### 1. Modules (`/dropdown/modules`)
Lists all discovered modules from `ModuleRepositoryInterface`:
```http
GET /api/dropdown/modules/name
```

### 2. Permissions (`/dropdown/permissions`)
Lists permissions defined in module `config/menu.php` files:
```http
# Permissions for a specific module (includes "All Permissions" option by default)
GET /api/dropdown/permissions/name?module=Core

# Permissions across all modules without the "All Permissions" header item
GET /api/dropdown/permissions/name?include_all=0
```

### 3. Module Settings (`/dropdown/settings`)
Supports two modes for working with `ModuleSettingsService`:

#### Mode A — List a module's settings definitions or resolved values
```http
# List setting labels and keys for a module
GET /api/dropdown/settings/label/key?module=core

# List setting labels and their effective resolved values (module/company/site scoped)
GET /api/dropdown/settings/label/value?module=core

# List all settings across all modules, grouped by module
GET /api/dropdown/settings/label/key?group_by=module
```

#### Mode B — Populate a dropdown from a specific setting's `options`
When a setting in `config/settings.php` defines selectable `options` (e.g. `default_currency` or `date_format`), you can populate a schema dropdown directly from that setting:
```http
GET /api/dropdown/settings/default_currency?module=core
# or explicitly:
GET /api/dropdown/settings/label/value?module=core&key=default_currency
```

---

## Filtering, Search, Grouping & Limits

| Query Parameter | Example | Behavior |
| :--- | :--- | :--- |
| Exact Filter | `?is_active=1&category=branch` | Applies `WHERE column = value` for any valid table column. |
| `whereIn` Filter | `?status=active,pending` | Comma-separated values or array query parameters apply `WHERE column IN (...)`. |
| `whereBetween` Filter | `?created_at=2026-01-01\|2026-12-31` | Pipe-separated `start\|end` values apply `WHERE column BETWEEN start AND end`. |
| `search` | `?search=Lahore` | Case-insensitive `LIKE %search%` across the label column(s). |
| `order_by` & `order_dir` | `?order_by=name&order_dir=asc` | Sorts results by a valid table column (`asc` or `desc`). |
| `limit` | `?limit=50` | Caps returned items (defaults to `500`, clamped to `max_limit: 1000`). |
| `group_by` | `?group_by=category` | Groups options under keys matching the `group_by` column. |

---

## Using `DropdownTrait` in Custom Controllers

Any module controller can use `Bitsnio\AsasFlow\Foundation\Http\Traits\DropdownTrait`:

```php
<?php

namespace Modules\Core\Http\Controllers;

use Bitsnio\AsasFlow\Foundation\Http\Traits\DropdownTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomLookupController extends Controller
{
    use DropdownTrait;

    public function activeSites(Request $request): JsonResponse
    {
        return $this->getDropdownData(
            table: 'sites',
            label: 'code,name',
            value: 'id',
            filters: ['is_active' => 1],
            search: $request->query('search'),
            limit: 100,
        );
    }
}
```

---

## Caching & Automatic Invalidation

- **Virtual Sources (`modules`, `permissions`, `settings`):**
  - `modules` and `permissions` resolve from in-memory configuration without hitting the database.
  - `settings` is already cached for 24 hours by `ModuleSettingsService` and invalidated on `ModuleSettings::update()`.
- **Database Table Dropdowns:**
  - Cached via `CacheManager` with tag `dropdown:{table}` (controlled by `config('asasflow.dropdown.cache.enabled')`).
  - Whenever an Eloquent model using `CacheAware` / `ModelCacheObserver` is created, updated, deleted, or restored, `ModelCacheObserver` automatically invalidates `dropdown:{$model->getTable()}` so dropdown options are never stale.

---

## Security & Configuration

Configure dropdown behavior in `config/asasflow.php`:

```php
'dropdown' => [
    'default_limit' => 500,
    'max_limit' => 1000,
    'cache' => [
        'enabled' => env('ASASFLOW_DROPDOWN_CACHE', true),
        'ttl' => env('ASASFLOW_DROPDOWN_CACHE_TTL', 300),
    ],
    'table_aliases' => [
        'tenants' => 'asasflow_tenants',
        'tenant_domains' => 'asasflow_tenant_domains',
        'module_settings' => 'module_settings',
    ],
    'protected_tables' => [
        'password_resets',
        'password_reset_tokens',
        'personal_access_tokens',
        'failed_jobs',
        'jobs',
        'job_batches',
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'asasflow_cache_entries',
    ],
    'protected_columns' => [
        'password',
        'remember_token',
        'api_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],
],
```
