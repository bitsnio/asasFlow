<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Http\Dropdown;

use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;
use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\MenuService;
use Bitsnio\AsasFlow\Foundation\Support\PermissionService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class DropdownService
{
    public function __construct(
        protected ModuleRepositoryInterface $moduleRepository,
        protected MenuService $menuService,
        protected PermissionService $permissionService,
        protected ModuleSettingsService $settingsService,
        protected ModuleSettingsRegistry $settingsRegistry,
        protected ModuleSettingsDiscovery $settingsDiscovery,
    ) {
    }

    /**
     * Resolve dropdown options for a virtual source or database table.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     * @return array{status: int, message: string, data?: mixed, errors?: mixed, cache_tag?: ?string, is_virtual?: bool}
     */
    public function resolve(
        string $table,
        string $label = 'name',
        ?string $value = null,
        array $filters = [],
        ?int $limit = null,
        ?string $groupBy = null,
        ?string $search = null,
        ?string $orderBy = null,
        string $orderDir = 'asc',
        array $options = [],
    ): array {
        $normalizedTable = strtolower(trim($table));

        if ($normalizedTable === 'modules') {
            return $this->resolveModulesDropdown($search, $limit);
        }

        if ($normalizedTable === 'permissions') {
            return $this->resolvePermissionsDropdown($filters, $options, $search, $limit);
        }

        if ($normalizedTable === 'settings') {
            return $this->resolveSettingsDropdown(
                label: $label,
                value: $value,
                filters: $filters,
                options: $options,
                search: $search,
                limit: $limit,
                groupBy: $groupBy,
            );
        }

        return $this->resolveDatabaseDropdown(
            table: $table,
            label: $label,
            value: $value,
            filters: $filters,
            limit: $limit,
            groupBy: $groupBy,
            search: $search,
            orderBy: $orderBy,
            orderDir: $orderDir,
        );
    }

    /**
     * Resolve dropdown items from discovered modules.
     *
     * @return array{status: int, message: string, data: array<int, array{label: string, value: string}>, cache_tag: null, is_virtual: true}
     */
    protected function resolveModulesDropdown(?string $search = null, ?int $limit = null): array
    {
        $modules = array_keys($this->moduleRepository->all());

        if ($modules === []) {
            $modules = $this->menuService->allModules();
        }

        $collection = collect($modules)
            ->map(fn (string $name): array => [
                'label' => $name,
                'value' => $name,
            ]);

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $collection = $collection->filter(
                fn (array $item): bool => str_contains(mb_strtolower($item['label']), $needle)
            );
        }

        if ($limit !== null && $limit > 0) {
            $collection = $collection->take($this->resolveLimit($limit));
        }

        return [
            'status' => 200,
            'message' => 'Dropdown data retrieved successfully.',
            'data' => $collection->values()->all(),
            'cache_tag' => null,
            'is_virtual' => true,
        ];
    }

    /**
     * Resolve dropdown items from module permissions.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     * @return array{status: int, message: string, data: array<int, array{label: string, value: string}>, cache_tag: null, is_virtual: true}
     */
    protected function resolvePermissionsDropdown(
        array $filters = [],
        array $options = [],
        ?string $search = null,
        ?int $limit = null,
    ): array {
        $module = $options['module'] ?? $filters['module'] ?? request()->query('module');
        $moduleName = is_string($module) && $module !== '' && strtolower($module) !== 'all'
            ? $module
            : null;

        $permissions = $this->permissionService->getAllPermissions($moduleName, true);

        $includeAll = $options['include_all'] ?? $filters['include_all'] ?? request()->query('include_all', true);
        $shouldIncludeAll = filter_var($includeAll, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

        $collection = collect($permissions);

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $collection = $collection->filter(
                fn (array $item): bool => str_contains(mb_strtolower((string) ($item['label'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($item['value'] ?? '')), $needle)
            );
        }

        if ($limit !== null && $limit > 0) {
            $collection = $collection->take($this->resolveLimit($limit));
        }

        $items = $collection->values()->all();

        if ($shouldIncludeAll) {
            array_unshift($items, [
                'label' => 'All Permissions',
                'value' => '',
            ]);
        }

        return [
            'status' => 200,
            'message' => 'Dropdown data retrieved successfully.',
            'data' => $items,
            'cache_tag' => null,
            'is_virtual' => true,
        ];
    }

    /**
     * Resolve dropdown items from ModuleSettingsService.
     *
     * Supports:
     * 1) Listing settings definitions/values for one or all modules:
     *    /dropdown/settings/label/key?module=core
     *    /dropdown/settings/label/value?module=core
     * 2) Populating options from a specific setting key:
     *    /dropdown/settings/label/value?module=core&key=default_currency
     *    /dropdown/settings/default_currency?module=core
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     * @return array{status: int, message: string, data?: mixed, errors?: mixed, cache_tag: null, is_virtual: true}
     */
    protected function resolveSettingsDropdown(
        string $label = 'label',
        ?string $value = null,
        array $filters = [],
        array $options = [],
        ?string $search = null,
        ?int $limit = null,
        ?string $groupBy = null,
    ): array {
        if ($this->settingsRegistry->all() === []) {
            $this->settingsDiscovery->discover();
        }

        $module = $options['module'] ?? $filters['module'] ?? request()->query('module');
        $settingKey = $options['key'] ?? $filters['key'] ?? request()->query('key');

        [$companyId, $siteId] = $this->resolveSettingsScope($filters);

        $standardSettingFields = ['label', 'key', 'value', 'default', 'description', 'scope', 'type', 'input', 'name'];
        if (
            ($settingKey === null || $settingKey === '')
            && is_string($module)
            && $module !== ''
            && !in_array($label, $standardSettingFields, true)
        ) {
            $settingKey = $label;
            $label = 'label';
        }

        // Mode B: Options/values for a specific setting key
        if (is_string($settingKey) && $settingKey !== '') {
            if (!is_string($module) || $module === '') {
                return [
                    'status' => 422,
                    'message' => 'Query parameter [module] is required when requesting a specific setting key.',
                    'cache_tag' => null,
                    'is_virtual' => true,
                ];
            }

            try {
                $schema = $this->settingsService->schema(strtolower($module), $companyId, $siteId);
            } catch (InvalidArgumentException $e) {
                return [
                    'status' => 404,
                    'message' => $e->getMessage(),
                    'cache_tag' => null,
                    'is_virtual' => true,
                ];
            }

            if (!array_key_exists($settingKey, $schema)) {
                return [
                    'status' => 404,
                    'message' => "Setting [{$module}.{$settingKey}] not found.",
                    'cache_tag' => null,
                    'is_virtual' => true,
                ];
            }

            $setting = $schema[$settingKey];
            $items = $this->extractOptionsFromSetting($setting);

            if ($search !== null && $search !== '') {
                $needle = mb_strtolower($search);
                $items = array_values(array_filter(
                    $items,
                    fn (array $item): bool => str_contains(mb_strtolower((string) $item['label']), $needle)
                ));
            }

            if ($limit !== null && $limit > 0) {
                $items = array_slice($items, 0, $this->resolveLimit($limit));
            }

            return [
                'status' => 200,
                'message' => 'Dropdown data retrieved successfully.',
                'data' => $items,
                'cache_tag' => null,
                'is_virtual' => true,
            ];
        }

        // Mode A: List settings across one or all registered modules
        $targetModules = [];
        if (is_string($module) && $module !== '' && strtolower($module) !== 'all') {
            $lowerModule = strtolower($module);
            if (!$this->settingsRegistry->has($lowerModule)) {
                return [
                    'status' => 404,
                    'message' => "Settings for module [{$module}] are not registered.",
                    'cache_tag' => null,
                    'is_virtual' => true,
                ];
            }
            $targetModules = [$lowerModule];
        } else {
            $targetModules = array_keys($this->settingsRegistry->all());
        }

        $labelField = ($label === 'name' || $label === '') ? 'label' : $label;
        $valueField = ($value === null || $value === '' || $value === 'id') ? 'key' : $value;

        $records = collect();

        foreach ($targetModules as $mod) {
            $schema = $this->settingsService->schema($mod, $companyId, $siteId);
            foreach ($schema as $key => $def) {
                $records->push(array_merge($def, [
                    'module' => $mod,
                    'key' => $key,
                ]));
            }
        }

        foreach (['scope', 'type', 'input'] as $filterableKey) {
            if (isset($filters[$filterableKey]) && $filters[$filterableKey] !== '') {
                $expected = (string) $filters[$filterableKey];
                $records = $records->filter(
                    fn (array $row): bool => (string) ($row[$filterableKey] ?? '') === $expected
                );
            }
        }

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $records = $records->filter(
                fn (array $row): bool => str_contains(mb_strtolower((string) ($row['label'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($row['key'] ?? '')), $needle)
            );
        }

        if ($limit !== null && $limit > 0) {
            $records = $records->take($this->resolveLimit($limit));
        }

        if ($groupBy !== null && $groupBy !== '') {
            $grouped = $records->groupBy($groupBy)->map(
                fn (Collection $group): array => $group->map(
                    fn (array $row): array => [
                        'label' => data_get($row, $labelField, $row['label'] ?? $row['key']),
                        'value' => data_get($row, $valueField, $row['key']),
                    ]
                )->values()->all()
            )->all();

            return [
                'status' => 200,
                'message' => 'Dropdown data retrieved successfully.',
                'data' => $grouped,
                'cache_tag' => null,
                'is_virtual' => true,
            ];
        }

        $formatted = $records->map(
            fn (array $row): array => [
                'label' => data_get($row, $labelField, $row['label'] ?? $row['key']),
                'value' => data_get($row, $valueField, $row['key']),
            ]
        )->values()->all();

        return [
            'status' => 200,
            'message' => 'Dropdown data retrieved successfully.',
            'data' => $formatted,
            'cache_tag' => null,
            'is_virtual' => true,
        ];
    }

    /**
     * Extract dropdown options array from a single setting definition/value.
     *
     * @param  array<string, mixed>  $setting
     * @return array<int, array{label: mixed, value: mixed}>
     */
    protected function extractOptionsFromSetting(array $setting): array
    {
        $rawOptions = $setting['options'] ?? [];

        if (is_array($rawOptions) && $rawOptions !== []) {
            return $this->normalizeOptionList($rawOptions);
        }

        if (is_array($setting['value'] ?? null)) {
            return $this->normalizeOptionList($setting['value']);
        }

        return [
            [
                'label' => $setting['label'] ?? $setting['key'] ?? '',
                'value' => $setting['value'] ?? $setting['default'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<mixed>  $options
     * @return array<int, array{label: mixed, value: mixed}>
     */
    protected function normalizeOptionList(array $options): array
    {
        $normalized = [];

        foreach ($options as $key => $item) {
            if (is_array($item) && (array_key_exists('label', $item) || array_key_exists('value', $item))) {
                $normalized[] = [
                    'label' => $item['label'] ?? $item['value'] ?? $key,
                    'value' => $item['value'] ?? $item['label'] ?? $key,
                ];
            } elseif (is_scalar($item)) {
                $normalized[] = [
                    'label' => is_string($key) ? $key : $item,
                    'value' => $item,
                ];
            }
        }

        return $normalized;
    }

    /**
     * Resolve dropdown items from a database table.
     *
     * @param  array<string, mixed>  $filters
     * @return array{status: int, message: string, data?: mixed, errors?: mixed, cache_tag?: ?string, is_virtual?: bool}
     */
    protected function resolveDatabaseDropdown(
        string $table,
        string $label = 'name',
        ?string $value = null,
        array $filters = [],
        ?int $limit = null,
        ?string $groupBy = null,
        ?string $search = null,
        ?string $orderBy = null,
        string $orderDir = 'asc',
    ): array {
        $protectedTables = (array) config('asasflow.dropdown.protected_tables', [
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
        ]);

        $aliases = (array) config('asasflow.dropdown.table_aliases', [
            'tenants' => 'asasflow_tenants',
            'tenant_domains' => 'asasflow_tenant_domains',
        ]);

        $resolvedTable = (string) ($aliases[$table] ?? $table);
        if (!Schema::hasTable($resolvedTable) && isset($aliases[$table]) && Schema::hasTable($table)) {
            $resolvedTable = $table;
        }

        if (
            in_array($table, $protectedTables, true)
            || in_array($resolvedTable, $protectedTables, true)
        ) {
            return [
                'status' => 403,
                'message' => 'Access to this resource is restricted',
            ];
        }

        if (!Schema::hasTable($resolvedTable)) {
            return [
                'status' => 404,
                'message' => 'Table not found',
            ];
        }

        $tableColumns = Schema::getColumnListing($resolvedTable);
        $protectedColumns = (array) config('asasflow.dropdown.protected_columns', [
            'password',
            'remember_token',
            'api_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ]);

        $resolvedValue = ($value === null || $value === '') ? 'id' : $value;
        $labelSpecs = $this->resolveLabelSpecs($label, $tableColumns);

        // Validate protected and existing columns for label, value, and groupBy
        $valueBaseCol = $this->extractBaseColumn($resolvedValue);
        $columnsToValidate = array_merge(
            array_map(fn (array $spec): string => $spec['base'], $labelSpecs),
            [$valueBaseCol]
        );

        if ($groupBy !== null && $groupBy !== '') {
            $columnsToValidate[] = $this->extractBaseColumn($groupBy);
        }

        foreach (array_unique($columnsToValidate) as $col) {
            if (in_array($col, $protectedColumns, true)) {
                return [
                    'status' => 403,
                    'message' => "Access to column [{$col}] is restricted",
                ];
            }

            if (!in_array($col, $tableColumns, true)) {
                return [
                    'status' => 422,
                    'message' => "Column [{$col}] does not exist on table [{$table}].",
                ];
            }
        }

        // Validate filter columns against protectedColumns
        foreach (array_keys($filters) as $filterCol) {
            if (in_array($filterCol, ['with_trashed'], true)) {
                continue;
            }
            if (in_array($filterCol, $protectedColumns, true)) {
                return [
                    'status' => 403,
                    'message' => "Filtering by restricted column [{$filterCol}] is not allowed",
                ];
            }
        }

        $selectColumns = array_values(array_unique($columnsToValidate));
        $query = DB::table($resolvedTable)->select($selectColumns);

        // Soft-delete exclusion
        $withTrashed = filter_var($filters['with_trashed'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($filters['with_trashed']);

        if (!$withTrashed && in_array('deleted_at', $tableColumns, true)) {
            $query->whereNull('deleted_at');
        }

        // Single-DB Multi-Tenancy scoping
        $this->applyTenantScope($query, $table, $resolvedTable, $tableColumns, $filters);

        // Apply user filters
        $this->applyFilters($query, $tableColumns, $protectedColumns, $filters);

        // Apply search across base label columns
        if ($search !== null && $search !== '') {
            $query->where(function (Builder $subQuery) use ($labelSpecs, $search): void {
                foreach ($labelSpecs as $spec) {
                    $subQuery->orWhere($spec['base'], 'like', '%' . $search . '%');
                }
            });
        }

        // Apply ordering
        if ($orderBy !== null && $orderBy !== '') {
            $orderBase = $this->extractBaseColumn($orderBy);
            if (in_array($orderBase, $tableColumns, true) && !in_array($orderBase, $protectedColumns, true)) {
                $direction = strtolower($orderDir) === 'desc' ? 'desc' : 'asc';
                $query->orderBy($orderBase, $direction);
            }
        }

        // Apply limit
        $effectiveLimit = $this->resolveLimit($limit);
        if ($effectiveLimit > 0) {
            $query->limit($effectiveLimit);
        }

        $results = $query->get();

        // Format results (grouped or flat)
        if ($groupBy !== null && $groupBy !== '') {
            $groupBase = $this->extractBaseColumn($groupBy);
            $grouped = $results->groupBy($groupBase)->map(function (Collection $group) use ($labelSpecs, $resolvedValue): array {
                return $group->map(
                    fn (object $item): array => $this->formatRow($item, $labelSpecs, $resolvedValue)
                )->values()->all();
            })->all();

            return [
                'status' => 200,
                'message' => 'Dropdown data retrieved successfully.',
                'data' => $grouped,
                'cache_tag' => 'dropdown:' . $resolvedTable,
                'is_virtual' => false,
            ];
        }

        $formatted = $results->map(
            fn (object $item): array => $this->formatRow($item, $labelSpecs, $resolvedValue)
        )->values()->all();

        return [
            'status' => 200,
            'message' => 'Dropdown data retrieved successfully.',
            'data' => $formatted,
            'cache_tag' => 'dropdown:' . $resolvedTable,
            'is_virtual' => false,
        ];
    }

    /**
     * Resolve one or more label column specifications, supporting comma-separated composite labels,
     * JSON dot-notation, and automatic fallback from `name` to `firstName,lastName`.
     *
     * @param  array<int, string>  $tableColumns
     * @return array<int, array{raw: string, base: string, json_path: ?string}>
     */
    protected function resolveLabelSpecs(string $label, array $tableColumns): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $label))));

        // Fallback if single column 'name' was requested on a table that uses firstName/lastName
        if (count($parts) === 1 && $parts[0] === 'name' && !in_array('name', $tableColumns, true)) {
            if (in_array('firstName', $tableColumns, true) && in_array('lastName', $tableColumns, true)) {
                $parts = ['firstName', 'lastName'];
            } elseif (in_array('first_name', $tableColumns, true) && in_array('last_name', $tableColumns, true)) {
                $parts = ['first_name', 'last_name'];
            } elseif (in_array('firstName', $tableColumns, true)) {
                $parts = ['firstName'];
            } elseif (in_array('title', $tableColumns, true)) {
                $parts = ['title'];
            }
        }

        $specs = [];
        foreach ($parts as $part) {
            if (str_contains($part, '.')) {
                [$base, $jsonPath] = explode('.', $part, 2);
                $specs[] = [
                    'raw' => $part,
                    'base' => $base,
                    'json_path' => $jsonPath,
                ];
            } else {
                $specs[] = [
                    'raw' => $part,
                    'base' => $part,
                    'json_path' => null,
                ];
            }
        }

        return $specs;
    }

    protected function extractBaseColumn(string $column): string
    {
        if (str_contains($column, '.')) {
            return explode('.', $column, 2)[0];
        }

        return trim($column);
    }

    /**
     * Format a database row into `['label' => ..., 'value' => ...]`.
     *
     * @param  array<int, array{raw: string, base: string, json_path: ?string}>  $labelSpecs
     * @return array{label: mixed, value: mixed}
     */
    protected function formatRow(object $item, array $labelSpecs, string $valueColumn): array
    {
        $labelValues = [];

        foreach ($labelSpecs as $spec) {
            $extracted = $this->extractColumnValue($item, $spec['base'], $spec['json_path']);
            if ($extracted !== null && $extracted !== '') {
                $labelValues[] = $extracted;
            }
        }

        $label = count($labelValues) <= 1
            ? ($labelValues[0] ?? null)
            : implode(' ', array_map('strval', $labelValues));

        $valueBase = $this->extractBaseColumn($valueColumn);
        $valueJsonPath = str_contains($valueColumn, '.') ? explode('.', $valueColumn, 2)[1] : null;
        $value = $this->extractColumnValue($item, $valueBase, $valueJsonPath);

        return [
            'label' => $label,
            'value' => $value,
        ];
    }

    protected function extractColumnValue(object $item, string $baseColumn, ?string $jsonPath = null): mixed
    {
        $raw = $item->{$baseColumn} ?? null;

        if ($jsonPath === null || $jsonPath === '') {
            return $raw;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return data_get($decoded, $jsonPath);
            }
        } elseif (is_array($raw) || is_object($raw)) {
            return data_get($raw, $jsonPath);
        }

        return null;
    }

    /**
     * Automatically scope single-DB tenant tables by tenant_id.
     *
     * @param  array<int, string>  $tableColumns
     * @param  array<string, mixed>  $filters
     */
    protected function applyTenantScope(
        Builder $query,
        string $requestedTable,
        string $resolvedTable,
        array $tableColumns,
        array $filters,
    ): void {
        if (!config('asasflow.tenancy.enabled', false)) {
            return;
        }

        if (config('asasflow.tenancy.database_strategy', 'single') !== 'single') {
            return;
        }

        $exemptTables = (array) config('asasflow.tenancy.exempt_tables', []);
        if (
            in_array($requestedTable, $exemptTables, true)
            || in_array($resolvedTable, $exemptTables, true)
        ) {
            return;
        }

        $tenantColumn = (string) config('asasflow.tenancy.tenant_column', 'tenant_id');
        if (!in_array($tenantColumn, $tableColumns, true)) {
            return;
        }

        if (array_key_exists($tenantColumn, $filters)) {
            return;
        }

        $tenantId = TenantContext::getTenantId()
            ?? auth('api')->user()?->{$tenantColumn}
            ?? auth()->user()?->{$tenantColumn}
            ?? null;

        if ($tenantId !== null && $tenantId !== '') {
            $query->where($tenantColumn, $tenantId);
        }
    }

    /**
     * Apply user-provided filters (`where`, `whereIn`, `whereBetween`).
     *
     * @param  array<int, string>  $tableColumns
     * @param  array<int, string>  $protectedColumns
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(
        Builder $query,
        array $tableColumns,
        array $protectedColumns,
        array $filters,
    ): void {
        foreach ($filters as $column => $filterValue) {
            if (
                !in_array($column, $tableColumns, true)
                || in_array($column, $protectedColumns, true)
            ) {
                continue;
            }

            if ($filterValue === null || $filterValue === '') {
                continue;
            }

            if (is_array($filterValue)) {
                $query->whereIn($column, $filterValue);
                continue;
            }

            if (is_string($filterValue) && str_contains($filterValue, '|')) {
                [$start, $end] = array_pad(explode('|', $filterValue, 2), 2, null);
                if ($start !== null && $end !== null && $start !== '' && $end !== '') {
                    $query->whereBetween($column, [trim($start), trim($end)]);
                    continue;
                }
            }

            if (is_string($filterValue) && str_contains($filterValue, ',')) {
                $values = array_values(array_filter(array_map('trim', explode(',', $filterValue)), fn ($v) => $v !== ''));
                if (count($values) > 1) {
                    $query->whereIn($column, $values);
                    continue;
                }
            }

            $query->where($column, $filterValue);
        }
    }

    protected function resolveLimit(?int $requestedLimit): int
    {
        $defaultLimit = (int) config('asasflow.dropdown.default_limit', 500);
        $maxLimit = (int) config('asasflow.dropdown.max_limit', 1000);

        $limit = ($requestedLimit !== null && $requestedLimit > 0)
            ? $requestedLimit
            : $defaultLimit;

        return $maxLimit > 0 ? min($limit, $maxLimit) : $limit;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: ?int, 1: ?int}
     */
    protected function resolveSettingsScope(array $filters): array
    {
        $companyId = isset($filters['company_id']) && is_numeric($filters['company_id'])
            ? (int) $filters['company_id']
            : (isset($filters['tenant_id']) && is_numeric($filters['tenant_id']) ? (int) $filters['tenant_id'] : null);

        $siteId = isset($filters['site_id']) && is_numeric($filters['site_id'])
            ? (int) $filters['site_id']
            : null;

        if ($companyId === null && config('asasflow.tenancy.enabled', false)) {
            $tenantId = TenantContext::getTenantId()
                ?? auth('api')->user()?->tenant_id
                ?? auth()->user()?->tenant_id;

            if ($tenantId !== null && is_numeric($tenantId)) {
                $companyId = (int) $tenantId;
            }
        }

        if ($siteId === null && config('asasflow.tenancy.enabled', false) && config('asasflow.tenancy.site_enabled', true)) {
            $resolvedSiteId = TenantContext::getSiteId()
                ?? auth('api')->user()?->site_id
                ?? auth()->user()?->site_id;

            if ($resolvedSiteId !== null && is_numeric($resolvedSiteId)) {
                $siteId = (int) $resolvedSiteId;
            }
        }

        return [$companyId, $siteId];
    }
}
