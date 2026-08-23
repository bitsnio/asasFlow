# Module Settings Documentation

## Overview

The Module Settings system provides a centralized configuration management solution for your Laravel application. It allows modules to define, retrieve, override, and update settings with support for three hierarchical scopes:

- **Module** - Global settings shared across the entire application
- **Company** - Settings specific to individual companies
- **Site** - Settings specific to individual sites within a company

Settings are automatically discovered, cached, and made available through both a PHP facade and REST API.

---

## Table of Contents

1. [Defining Settings](#defining-settings)
2. [Setting Scopes](#setting-scopes)
3. [Value Resolution](#value-resolution)
4. [PHP Usage](#php-usage)
5. [API Usage](#api-usage)
6. [Frontend Integration](#frontend-integration)
7. [Caching](#caching)
8. [Complete Examples](#complete-examples)

---

## Defining Settings

### File Structure

Each module defines its settings in:

```
Modules/{ModuleName}/config/settings.php
```

### Simple Definitions

The simplest form uses a key-value pair:

```php
<?php

return [
    'inventory_enabled' => true,
    'low_stock_threshold' => 10,
    'default_currency' => 'USD',
];
```

**Note:** Simple definitions default to `scope: module`.

### Advanced Definitions

For full control, define settings as arrays:

```php
<?php

return [
    'costing_method' => [
        'label' => 'Costing Method',
        'description' => 'Select the inventory costing method.',
        'type' => 'string',
        'input' => 'select',
        'default' => 'fifo',
        'scope' => 'company',
        'options' => [
            ['label' => 'FIFO', 'value' => 'fifo'],
            ['label' => 'LIFO', 'value' => 'lifo'],
            ['label' => 'Weighted Average', 'value' => 'weighted_average'],
        ],
        'rules' => ['required', 'in:fifo,lifo,weighted_average'],
    ],
];
```

### Available Properties

| Property | Type | Description |
|----------|------|-------------|
| `label` | string | Human-readable setting name |
| `description` | string | Additional context or help text |
| `type` | string | Data type (string, boolean, integer, etc.) |
| `input` | string | Suggested frontend input component |
| `default` | mixed | Default value |
| `options` | array | Options for select-based inputs |
| `rules` | array | Validation rules |
| `scope` | string | `module`, `company`, or `site` |

### Default Values

When omitted, these defaults apply:

```php
[
    'label' => 'setting_key',
    'description' => null,
    'type' => null,
    'input' => null,
    'default' => null,
    'options' => [],
    'rules' => [],
    'scope' => 'module',
]
```

---

## Setting Scopes

### Module Scope

Settings at this level have one value for the entire application.

**Definition:**

```php
'maintenance_mode' => [
    'default' => false,
    'scope' => 'module',
],
```

**Update:**

```php
ModuleSettings::update('admin', ['maintenance_mode' => true]);
```

### Company Scope

Settings at this level can differ per company.

**Definition:**

```php
'default_currency' => [
    'default' => 'USD',
    'scope' => 'company',
],
```

**Update:**

```php
ModuleSettings::update('inventory', ['default_currency' => 'PKR'], $companyId);
```

### Site Scope

Settings at this level can differ per site within a company.

**Definition:**

```php
'low_stock_threshold' => [
    'default' => 10,
    'scope' => 'site',
],
```

**Update:**

```php
ModuleSettings::update('inventory', ['low_stock_threshold' => 25], $companyId, $siteId);
```

---

## Value Resolution

Settings inherit values from broader scopes when specific overrides don't exist.

### Module-Level Resolution

```
Module DB Value → Default Value
```

### Company-Level Resolution

```
Company Override → Module Override → Default Value
```

### Site-Level Resolution

```
Site Override → Company Override → Module Override → Default Value
```

### Example

Given these definitions:

```php
'default_currency' => [
    'default' => 'USD',
    'scope' => 'company',
],
```

**Scenario 1:** Company 1 has override 'PKR'
```
Result for Company 1: 'PKR'
Result for Company 2: 'USD' (fallback)
```

**Scenario 2:** Site 1 has override 'EUR'
```
Result for Site 1: 'EUR'
Result for Site 2: 'USD' (fallback)
```

---

## PHP Usage

### Facade Import

```php
use Bitsnio\AsasFlow\Features\Settings\Facades\ModuleSettings;
```

### Get a Single Setting

```php
$value = ModuleSettings::get('inventory', 'inventory_enabled');

// With fallback
$value = ModuleSettings::get('inventory', 'unknown_setting', false);

// Company-level
$currency = ModuleSettings::get('inventory', 'default_currency', null, $companyId);

// Site-level
$threshold = ModuleSettings::get('inventory', 'low_stock_threshold', null, $companyId, $siteId);
```

### Get All Settings

```php
// All settings for a module
$settings = ModuleSettings::all('inventory');

// All settings for a company
$settings = ModuleSettings::all('inventory', $companyId);

// All settings for a site
$settings = ModuleSettings::all('inventory', $companyId, $siteId);
```

### Update Settings

```php
// Module-level
ModuleSettings::update('admin', ['maintenance_mode' => true]);

// Company-level
ModuleSettings::update('inventory', ['default_currency' => 'PKR'], $companyId);

// Site-level
ModuleSettings::update('inventory', ['low_stock_threshold' => 25], $companyId, $siteId);

// Multiple settings
ModuleSettings::update('inventory', [
    'default_currency' => 'PKR',
    'allow_negative_stock' => true,
], $companyId);
```

### Get Settings Schema

Retrieve definitions with current values for frontend use:

```php
// Module-level schema
$schema = ModuleSettings::schema('inventory');

// Company-level schema
$schema = ModuleSettings::schema('inventory', $companyId);

// Site-level schema
$schema = ModuleSettings::schema('inventory', $companyId, $siteId);
```

### Cross-Module Access

Any module can access another module's settings:

```php
// Admin module accessing Inventory settings
$inventoryEnabled = ModuleSettings::get('inventory', 'inventory_enabled');

// Inventory module accessing Admin settings
$maintenanceMode = ModuleSettings::get('admin', 'maintenance_mode');
```

### Important Notes

**Method Signatures:**

```php
ModuleSettings::get(
    string $module,
    string $key,
    mixed $default = null,
    ?int $companyId = null,
    ?int $siteId = null
);

ModuleSettings::all(
    string $module,
    ?int $companyId = null,
    ?int $siteId = null
);

ModuleSettings::update(
    string $module,
    array $values,
    ?int $companyId = null,
    ?int $siteId = null
);
```

**Third Parameter Quirk:** For `get()`, the third parameter is the default value. Use `null` when passing company/site IDs:

```php
// ✅ Correct
$currency = ModuleSettings::get('inventory', 'default_currency', null, $companyId);

// ❌ Wrong - this interprets $companyId as the default
$currency = ModuleSettings::get('inventory', 'default_currency', $companyId);
```

**Scope Validation:** Settings must be updated with the correct scope. This will throw an exception:

```php
// ❌ Maintenance mode is module-level
ModuleSettings::update('admin', ['maintenance_mode' => true], $companyId);
```

---

## API Usage

### Authentication

All endpoints are protected with `auth:api`.

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/settings` | List all modules with settings |
| GET | `/settings/{module}` | Get schema and values for a module |
| PUT | `/settings/{module}` | Update settings for a module |

### Get All Modules

```http
GET /api/settings
```

**Response:**

```json
{
    "modules": ["inventory", "admin", "sales"]
}
```

### Get Module Settings Schema

```http
GET /api/settings/inventory
```

**Response:**

```json
{
    "module": "inventory",
    "settings": {
        "inventory_enabled": {
            "label": "Enable Inventory",
            "description": null,
            "type": "boolean",
            "input": "switch",
            "default": true,
            "options": [],
            "rules": [],
            "scope": "module",
            "key": "inventory_enabled",
            "value": true
        },
        "default_currency": {
            "label": "Default Currency",
            "type": "string",
            "input": "select",
            "default": "USD",
            "scope": "company",
            "options": [
                {"label": "US Dollar", "value": "USD"},
                {"label": "Pakistani Rupee", "value": "PKR"}
            ],
            "rules": [],
            "key": "default_currency",
            "value": "USD"
        }
    }
}
```

### Update Settings

**Module-level update:**

```http
PUT /api/settings/admin
```

```json
{
    "settings": {
        "maintenance_mode": true
    }
}
```

**Company-level update:**

```http
PUT /api/settings/inventory
```

```json
{
    "settings": {
        "default_currency": "PKR"
    },
    "company_id": 1
}
```

**Site-level update:**

```http
PUT /api/settings/inventory
```

```json
{
    "settings": {
        "low_stock_threshold": 25
    },
    "company_id": 1,
    "site_id": 5
}
```

**Response:**

```json
{
    "message": "Settings updated successfully.",
    "module": "inventory",
    "settings": {
        "inventory_enabled": true,
        "default_currency": "PKR",
        "low_stock_threshold": 10
    }
}
```

### Error Responses

**Invalid Setting:**

```json
{
    "message": "Unknown setting [inventory.invalid_setting]."
}
```

**Scope Mismatch:**

```json
{
    "message": "Setting [maintenance_mode] is module-level and cannot be overridden per company/site."
}
```

---

## Frontend Integration

### Recommended Workflow

```
1. GET /settings/{module} → Receive schema + current values
2. Build settings form dynamically from schema
3. User modifies values
4. PUT /settings/{module} → Update settings
5. Receive updated resolved values
```

### Angular Example

```typescript
import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

@Injectable({
    providedIn: 'root'
})
export class SettingsService {
    constructor(private http: HttpClient) {}

    getSettings(module: string): Observable<any> {
        return this.http.get(`/api/settings/${module}`);
    }

    updateSettings(
        module: string,
        settings: Record<string, any>,
        companyId?: number,
        siteId?: number
    ): Observable<any> {
        return this.http.put(`/api/settings/${module}`, {
            settings,
            company_id: companyId,
            site_id: siteId
        });
    }
}
```

**Usage:**

```typescript
// Load settings
this.settingsService.getSettings('inventory').subscribe(response => {
    this.schema = response.settings;
});

// Update settings
this.settingsService.updateSettings('inventory', {
    default_currency: 'PKR'
}, companyId).subscribe(response => {
    console.log('Settings updated:', response);
});
```

### Dynamic Form Rendering

```typescript
renderSetting(key: string, setting: any): string {
    switch (setting.input) {
        case 'select':
            return `
                <label>${setting.label}</label>
                <select name="${key}" value="${setting.value}">
                    ${setting.options.map(opt => `
                        <option value="${opt.value}">${opt.label}</option>
                    `).join('')}
                </select>
            `;
        case 'switch':
            return `
                <label>${setting.label}</label>
                <input type="checkbox" ${setting.value ? 'checked' : ''}>
            `;
        case 'number':
            return `
                <label>${setting.label}</label>
                <input type="number" value="${setting.value}">
            `;
        default:
            return `
                <label>${setting.label}</label>
                <input type="text" value="${setting.value}">
            `;
    }
}
```

### Input Component Mapping

| Input Value | Suggested Component |
|-------------|---------------------|
| `text` | Text input |
| `number` | Number input |
| `switch` | Toggle/Checkbox |
| `select` | Dropdown |
| `textarea` | Textarea |

---

## Caching

### How It Works

- Resolved settings are cached for **24 hours**
- Each module+scope combination has a unique cache key
- Cache is automatically cleared on update

### Cache Keys

```php
// Format
module-settings:{module}:{companyId}:{siteId}

// Examples
module-settings:inventory:global:global  // Module-level
module-settings:inventory:1:global       // Company 1
module-settings:inventory:1:5            // Site 5
```

### Clearing Cache

Cache is automatically cleared when updating settings:

```php
ModuleSettings::update('inventory', ['setting' => 'value'], $companyId);
// Cache for inventory:companyId:global is automatically cleared
```

### Manual Cache Management

```php
use Illuminate\Support\Facades\Cache;

// Clear specific cache
Cache::forget('module-settings:inventory:1:global');

// Clear all settings cache (if needed)
Cache::delete('module-settings:*');
```

---

## Complete Examples

### Full Setting Definition

```php
<?php

return [
    // Module-level setting
    'inventory_enabled' => [
        'label' => 'Enable Inventory',
        'type' => 'boolean',
        'input' => 'switch',
        'default' => true,
        'scope' => 'module',
    ],

    // Company-level setting with options
    'default_currency' => [
        'label' => 'Default Currency',
        'type' => 'string',
        'input' => 'select',
        'default' => 'USD',
        'scope' => 'company',
        'options' => [
            ['label' => 'US Dollar', 'value' => 'USD'],
            ['label' => 'Pakistani Rupee', 'value' => 'PKR'],
            ['label' => 'Euro', 'value' => 'EUR'],
        ],
        'rules' => ['required', 'in:USD,PKR,EUR'],
    ],

    // Site-level setting
    'low_stock_threshold' => [
        'label' => 'Low Stock Threshold',
        'type' => 'integer',
        'input' => 'number',
        'default' => 10,
        'scope' => 'site',
        'rules' => ['required', 'integer', 'min:0'],
    ],

    // Simple definition (scope defaults to module)
    'allow_negative_stock' => false,
];
```

### Complete Usage Example

```php
use Bitsnio\AsasFlow\Features\Settings\Facades\ModuleSettings;

class InventoryService
{
    public function checkInventory(): void
    {
        // Check if inventory is enabled
        if (!ModuleSettings::get('inventory', 'inventory_enabled')) {
            throw new \Exception('Inventory system is disabled.');
        }

        // Get company-specific currency
        $currency = ModuleSettings::get('inventory', 'default_currency', null, auth()->user()->company_id);

        // Get site-specific threshold
        $threshold = ModuleSettings::get('inventory', 'low_stock_threshold', null, 
            auth()->user()->company_id, 
            auth()->user()->site_id
        );

        // Check if negative stock is allowed (simple definition)
        $allowNegative = ModuleSettings::get('inventory', 'allow_negative_stock');
    }

    public function updateSettings(): void
    {
        // Update multiple settings
        ModuleSettings::update('inventory', [
            'default_currency' => 'PKR',
            'allow_negative_stock' => true,
        ], auth()->user()->company_id);
    }
}
```

### Adding a New Setting

1. **Update settings file:**

```php
// Modules/Inventory/config/settings.php
return [
    // ... existing settings
    
    'new_setting' => [
        'label' => 'New Setting',
        'type' => 'string',
        'input' => 'text',
        'default' => 'default value',
        'scope' => 'company',
    ],
];
```

2. **Clear config cache (if enabled):**

```bash
php artisan optimize:clear
```

3. **Start using:**

```php
$value = ModuleSettings::get('inventory', 'new_setting', null, $companyId);
```

No database migration is required for new settings.

---

## Troubleshooting

### Common Errors

**"Settings for module [x] are not registered"**
- Ensure the module has a `config/settings.php` file
- Check that the SettingsServiceProvider is registered

**"Unknown setting [module.key]"**
- Verify the setting exists in the module's settings file
- Check for typos in the setting key
- Clear config cache after adding new settings

**"Setting [key] is module-level and cannot be overridden per company/site"**
- The setting is defined with `scope: module`
- Remove `company_id` and `site_id` from the update call

**"Setting [key] requires a company scope"**
- The setting is defined with `scope: company`
- Include `company_id` in the update call

**"Setting [key] requires a site scope"**
- The setting is defined with `scope: site`
- Include both `company_id` and `site_id` in the update call

### Cache Issues

If settings appear stale:

```bash
php artisan cache:clear
```

Or clear specific cache in code:

```php
ModuleSettings::forget('inventory', $companyId, $siteId);
```