# Laravel FilterScope Documentation

## Overview

The `TenantScopes` trait provides dynamic filtering, sorting, and pagination capabilities for Laravel Eloquent models. It now also includes **automatic filter column metadata generation** for paginated responses.

It offers:

* Dynamic filtering with column security validation
* Automatic pagination and sorting
* Relationship and JSON column filtering support
* Filterable columns metadata for front-end usage
* Unified request handling for complex queries

---

## Key Features

* **Dynamic Filtering** - Filter by any column with various operators
* **Automatic Sorting** - Request-based sorting with validation
* **Security First** - Only allows filtering on authorized columns
* **Relationship Support** - Filter across related models
* **JSON Column Support** - Nested JSON keys can be filtered using dot notation
* **Automatic Filter Metadata** - Filters included in `meta.filters` for paginated responses
* **Customizable Labels** - Override default labels for user-friendly names
* **Pagination Ready** - Built-in pagination with meta data
* **Flexible Operators** - Equals, contains, in, between, null checks, and more
* **Caching** - Filter metadata cached for 24 hours to reduce DB queries

---

## Controller Usage Examples

### 1. Basic Usage (Recommended)

```php
public function index()
{
    $transactions = MtRpMg::with(['company', 'site', 'creator'])
        ->getPaginated(); // Handles filters, sorting, pagination, and generates filter columns

    return $this->paginatedSuccessResponse(
        MtRPmgtPanelResource::collection($transactions)
    );
}
```

**Features:**

* ✅ Automatic pagination
* ✅ Dynamic filtering from request
* ✅ Automatic sorting from request
* ✅ Column security validation
* ✅ JSON & relationship filters
* ✅ `meta.filters` auto-generated

---

### 2. Model Configuration for Filters

**Example model with JSON & relationships:**

```php
use App\Traits\HasFilterColumns;

class MtRpMg extends Model
{
    use HasFilterColumns;

    protected $fillable = [
        'receiver_address', 'receiver_personal_details', 'mto_name', 'site_id'
    ];

    // Optional JSON schema for nested columns
    protected array $jsonSchema = [
        'receiver_address' => ['street', 'city', 'zip'],
        'receiver_personal_details' => ['birthCountryCode', 'citizenshipCountryCode'],
    ];

    // Exclude columns from filters
    protected array $excludeFilterColumns = ['mto_name'];

    // Override labels for user-friendly names
    protected array $labelOverrides = [
        'receiver_address.street' => 'Receiver Street',
        'receiver_personal_details.birthCountryCode' => 'Birth Country',
        'site.name' => 'Agent Site Name',
    ];

    public function site()
    {
        return $this->belongsTo(Sites::class, 'site_id');
    }
}
```

> The `meta.filters` will automatically include:
>
> * Normal fillable columns
> * JSON columns expanded as dot notation paths
> * Relationship columns expanded as `relation.column`

---

### 3. Paginated Response Example

```json
{
  "status": true,
  "message": "Success",
  "data": [ ...paginated items... ],
  "meta": {
    "model": "MtRpMg",
    "table": "money_transfers",
    "filters": [
      { "label": "Receiver Street", "value": "receiver_address.street" },
      { "label": "Birth Country", "value": "receiver_personal_details.birthCountryCode" },
      { "label": "Agent Site Name", "value": "site.name" }
    ],
    "total": 120,
    "per_page": 20,
    "current_page": 1,
    "last_page": 6,
    "from": 1,
    "to": 20,
    "has_more_pages": true
  }
}
```

---

### 4. Frontend Usage

```javascript
// Request filters dynamically
const params = {
    page: 1,
    per_page: 25,
    sort_by: 'created_at',
    sort_order: 'desc',
    filters: JSON.stringify([
        { status: ["approved", "pending"], operator: "in" },
        { "receiver_address.street": "Main", operator: "contains" },
        { "site.name": "NY Branch", operator: "=" }
    ])
};

axios.get('/api/transactions', { params })
     .then(res => console.log(res.data));
```

---

## ⚡ Customizing Filters

* **Exclude columns**:

```php
protected array $excludeFilterColumns = ['internal_notes', 'deleted_at'];
```

* **Rename labels for clarity**:

```php
protected array $labelOverrides = [
    'receiver_address.street' => 'Street Address',
    'site.name' => 'Branch Name',
];
```

* **Provide JSON schema manually**:

```php
protected array $jsonSchema = [
    'receiver_address' => ['street', 'city', 'zip']
];
```

---

## 🛠️ Cache Management & Way Forward

### Current Caching

* Filters are cached per model for **24 hours**.
* Cache key format: `filters:{ModelClass}`

### Recommended Cache Invalidation Strategies

1. **Automatic on Model Change**

   * Listen to `Schema::table` changes or run an Artisan command after migration:

   ```bash
   php artisan cache:forget filters:App\\Models\\MtRpMg
   ```
2. **Manual Cache Reset**

   * If a new column is added or JSON structure changes:

   ```php
   Cache::forget('filters:' . MtRpMg::class);
   ```
3. **Dynamic Cache Expiry**

   * Optional: shorten TTL to a few hours in dev environments to reflect changes quickly.

> Future Improvement: Build a **model observer** that clears cache on `saved`/`updated` events if JSON schema or fillable columns change.

---

## 🔒 Security Features

* ✅ Column validation against fillable fields
* ✅ Relationship validation
* ✅ JSON path validation using dot notation
* ✅ SQL injection protection through Eloquent
* ✅ Type safety for filters

---

## Summary

* Filters metadata (`meta.filters`) is now **automatic and cached**
* Supports **JSON columns**, **relationships**, and **label overrides**
* **Dot notation** used for nested JSON and related models
* Cache can be invalidated manually or via future observer integration