<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class MenuService
{
    protected string $cacheKey = 'asasflow_menu_permissions';
    protected int $cacheDuration = 3600;

    public function __construct(
        protected ModuleRepositoryInterface $repository,
        protected MenuBuilder $builder,
    ) {}

    /**
     * Return MenuDefinition[] for one module or all modules.
     */
    public function getDefinitions(
        ?string $moduleName = null
    ): array {
        $modules = $moduleName
            ? [$this->repository->find($moduleName)]
            : $this->repository->all();

        $definitions = [];

        foreach ($modules as $module) {
            if (!$module) {
                continue;
            }

            $menuPath = $module->getPath()
                . '/config/menu.php';

            if (!is_file($menuPath)) {
                continue;
            }

            $menu = require $menuPath;

            if (!is_array($menu)) {
                continue;
            }

            foreach ($this->builder->build($menu) as $definition) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * Return menu definitions filtered by user permissions.
     *
     * Groups remain visible when at least one child
     * remains visible.
     */
    public function getMenus(
        ?string $moduleName = null,
        bool $filterByUserPermissions = false,
        $user = null,
    ): array {
        $definitions = $this->getDefinitions(
            $moduleName
        );

        if (!$filterByUserPermissions) {
            return $definitions;
        }

        $user ??= $this->authenticatedUser();

        if (!$user) {
            return [];
        }

        return $this->filterDefinitions(
            $definitions,
            $user
        );
    }

    /**
     * Get formatted menu items for frontend navigation and metadata.
     */
    public function getFormattedMenu(
        ?string $moduleName = null,
        bool $filterByUserPermissions = false,
        bool $onlyVisible = false,
        $user = null,
    ): array {
        $user ??= ($filterByUserPermissions ? $this->authenticatedUser() : null);
        $cacheKey = $this->buildCacheKey(
            $moduleName,
            $filterByUserPermissions,
            $onlyVisible,
            $user
        );

        return Cache::remember($cacheKey, $this->cacheDuration, function () use (
            $moduleName,
            $filterByUserPermissions,
            $onlyVisible,
            $user
        ) {
            $definitions = $this->getMenus(
                $moduleName,
                $filterByUserPermissions,
                $user
            );

            $menus = [];

            foreach ($definitions as $definition) {
                $formatted = $this->formatDefinition(
                    $definition,
                    $filterByUserPermissions,
                    $onlyVisible,
                    $user
                );

                if ($formatted !== null) {
                    $menus[] = $formatted;
                }
            }

            usort($menus, fn(array $a, array $b) => ($a['order'] <=> $b['order']));

            return $menus;
        });
    }

    protected function formatDefinition(
        MenuDefinition $definition,
        bool $filterByUserPermissions,
        bool $onlyVisible,
        $user = null,
    ): ?array {
        $hidden = (bool) ($definition->config['hidden'] ?? false);

        if ($onlyVisible && $hidden) {
            return null;
        }

        $item = [
            'name' => $definition->name,
            'title' => $definition->title,
            'type' => $definition->type,
            'icon' => $definition->config['icon'] ?? null,
            'order' => $definition->config['order'] ?? 999,
            'home' => (bool) ($definition->config['home'] ?? false),
            'expanded' => (bool) ($definition->config['expanded'] ?? false),
            'hidden' => $hidden,
            'model' => $definition->model,
        ];

        if ($definition->isGroup()) {
            $children = [];

            foreach ($definition->children as $child) {
                $formattedChild = $this->formatDefinition(
                    $child,
                    $filterByUserPermissions,
                    $onlyVisible,
                    $user
                );

                if ($formattedChild !== null) {
                    $children[] = $formattedChild;
                }
            }

            if (empty($children)) {
                return null;
            }

            usort($children, fn(array $a, array $b) => ($a['order'] <=> $b['order']));
            $item['children'] = $children;

            return $item;
        }

        $schemaMeta = $this->resolveSchemaMetadata($definition);

        $item['link'] = $definition->routePath();
        $item['permission'] = $this->viewPermission($definition);
        $item['schema'] = $schemaMeta['schema'];
        $item['schema_updated_at'] = $schemaMeta['schema_updated_at'];

        if ($definition->isResource()) {
            $actions = [];

            foreach ($definition->customActions() as $actionName => $actionConfig) {
                $actionHidden = (bool) ($actionConfig['hidden'] ?? false);
                if ($onlyVisible && $actionHidden) {
                    continue;
                }

                $permissionKey = $definition->customActionPermissionKey($actionName);
                if ($filterByUserPermissions && !$this->userCan($user, $permissionKey)) {
                    continue;
                }

                $actions[] = [
                    'name' => $actionName,
                    'title' => $actionConfig['title'] ?? $definition->customActionPermissionLabel($actionName),
                    'method' => $definition->customActionMethod($actionName),
                    'link' => $definition->customActionRoutePath($actionName),
                    'permission' => $permissionKey,
                    'icon' => $actionConfig['icon'] ?? null,
                    'order' => $actionConfig['order'] ?? 999,
                    'hidden' => $actionHidden,
                ];
            }

            usort($actions, fn(array $a, array $b) => ($a['order'] <=> $b['order']));
            $item['actions'] = $actions;
        }

        return $item;
    }

    protected function filterDefinitions(
        array $definitions,
        $user
    ): array {
        $result = [];

        foreach ($definitions as $definition) {
            $children = $this->filterDefinitions(
                $definition->children,
                $user
            );

            if ($definition->isGroup()) {
                if (empty($children)) {
                    continue;
                }

                $result[] = new MenuDefinition(
                    module: $definition->module,
                    name: $definition->name,
                    title: $definition->title,
                    path: $definition->path,
                    type: $definition->type,
                    middleware: $definition->middleware,
                    model: $definition->model,
                    routes: $definition->routes,
                    permissions: $definition->permissions,
                    controllerClass: $definition->controllerClass,
                    resourceClass: $definition->resourceClass,
                    schemaName: $definition->schemaName,
                    children: $children,
                    config: $definition->config,
                );

                continue;
            }

            $permission = $this->viewPermission(
                $definition
            );

            if (
                $permission &&
                $this->userCan($user, $permission)
            ) {
                $result[] = $definition;
            }
        }

        return $result;
    }

    protected function userCan($user, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        if (($user->role_id ?? null) === 'super-admin') {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
            return true;
        }

        return method_exists($user, 'can') && $user->can($permission);
    }

    protected function viewPermission(
        MenuDefinition $definition
    ): ?string {
        return $definition->permissionKey()
            . '.view';
    }

    protected function buildCacheKey(
        ?string $moduleName,
        bool $filterByPermissions,
        bool $onlyVisible,
        $user = null
    ): string {
        $key = $this->cacheKey . '_formatted';

        if ($moduleName) {
            $key .= '_' . strtolower($moduleName);
        }

        if ($filterByPermissions) {
            $userId = $user?->id ?? ($this->authenticatedUser()?->id ?? 'guest');
            $key .= '_user_' . $userId;
        }

        if (!$onlyVisible) {
            $key .= '_all';
        }

        $stateHash = $this->getFilesStateHash($moduleName);
        if ($stateHash !== '') {
            $key .= '_' . $stateHash;
        }

        return $key;
    }

    public function clearCache($user = null): void
    {
        $user ??= $this->authenticatedUser();

        $allStateHash = $this->getFilesStateHash(null);
        $cacheKeys = [
            $this->cacheKey . '_formatted',
            $this->cacheKey . '_formatted_all',
        ];

        if ($allStateHash !== '') {
            $cacheKeys[] = $this->cacheKey . '_formatted_' . $allStateHash;
            $cacheKeys[] = $this->cacheKey . '_formatted_all_' . $allStateHash;
        }

        $modules = $this->repository->all();

        foreach ($modules as $module) {
            $rawName = $module->getName();
            $moduleName = strtolower($rawName);
            $moduleHash = $this->getFilesStateHash($rawName);

            $cacheKeys[] = $this->cacheKey . '_formatted_' . $moduleName;
            $cacheKeys[] = $this->cacheKey . '_formatted_' . $moduleName . '_all';

            if ($moduleHash !== '') {
                $cacheKeys[] = $this->cacheKey . '_formatted_' . $moduleName . '_' . $moduleHash;
                $cacheKeys[] = $this->cacheKey . '_formatted_' . $moduleName . '_all_' . $moduleHash;
            }
        }

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        if ($user && isset($user->id)) {
            $this->clearUserMenuCache($user->id);
        }
    }

    public function clearUserMenuCache(array|int|string $userIds): void
    {
        $userIds = is_array($userIds) ? $userIds : [$userIds];

        if (empty($userIds)) {
            return;
        }

        $allStateHash = $this->getFilesStateHash(null);
        $modules = $this->repository->all();

        foreach ($userIds as $userId) {
            if ($userId === null || $userId === '') {
                continue;
            }

            $cacheKeys = [
                $this->cacheKey . '_formatted_user_' . $userId,
                $this->cacheKey . '_formatted_user_' . $userId . '_all',
            ];

            if ($allStateHash !== '') {
                $cacheKeys[] = $this->cacheKey . '_formatted_user_' . $userId . '_' . $allStateHash;
                $cacheKeys[] = $this->cacheKey . '_formatted_user_' . $userId . '_all_' . $allStateHash;
            }

            foreach ($modules as $module) {
                $rawName = $module->getName();
                $moduleName = strtolower($rawName);
                $moduleHash = $this->getFilesStateHash($rawName);

                $cacheKeys[] = $this->cacheKey . '_formatted_' . $moduleName . '_user_' . $userId;
                $cacheKeys[] = $this->cacheKey . '_formatted_' . $moduleName . '_user_' . $userId . '_all';

                if ($moduleHash !== '') {
                    $cacheKeys[] = $this->cacheKey . '_formatted_' . $moduleName . '_user_' . $userId . '_' . $moduleHash;
                    $cacheKeys[] = $this->cacheKey . '_formatted_' . $moduleName . '_user_' . $userId . '_all_' . $moduleHash;
                }
            }

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
        }
    }

    protected function authenticatedUser()
    {
        try {
            return JWTAuth::parseToken()
                ->authenticate();
        } catch (\Throwable) {
            return null;
        }
    }

    public function allModules(): array
    {
        $modules = $this->repository->all();

        $names = [];

        foreach ($modules as $module) {
            $names[] = $module->getName();
        }

        return $names;
    }

    /**
     * Resolve and load a module JSON schema file using a menu link (e.g. "core/access/users").
     * When $id is provided, fetches the record and populates the schema model with its data.
     */
    public function getSchemaByLink(
        string $link,
        int|string|null $id = null
    ): array {
        $normalized = $this->normalizeLink($link);

        if ($normalized === '') {
            throw new InvalidArgumentException('Menu link is required to resolve schema.');
        }

        $definition = $this->findDefinitionByLink(
            $this->getDefinitions(),
            $normalized
        );

        if ($definition !== null) {
            $module = $this->repository->find($definition->module);
            $schemaName = $definition->schemaName();
            $modelName = $definition->modelClass();
        } else {
            $segments = array_values(array_filter(explode('/', $normalized)));

            if (count($segments) < 2) {
                throw new InvalidArgumentException("Invalid menu link [{$link}]. Expected format like 'core/access/users'.");
            }

            $module = $this->repository->find($segments[0]);
            $schemaName = Str::studly((string) end($segments));
            $modelName = $schemaName;
        }

        if (!$module) {
            throw new InvalidArgumentException("Module not found for menu link [{$link}].");
        }

        $schemaPath = $module->getPath() . '/schema/' . $schemaName . '.json';
        $schemaDef = SchemaDefinition::fromFile($schemaPath);
        $modelData = $schemaDef->modelDefaults();

        if ($id !== null && $id !== '') {
            $usePackageModel = !$schemaDef->shouldGenerate('models')
                || ($definition !== null && !$definition->model);

            $modelClass = $this->resolveModelClass($module->getName(), $modelName, $usePackageModel);

            if (!$modelClass) {
                throw new InvalidArgumentException(
                    "Model class [{$modelName}] not found in module [{$module->getName()}] to load record [{$id}]."
                );
            }

            $record = $modelClass::findOrFail($id);
            $recordData = $record->toArray();

            $modelData = $this->populateModelFromRecord(
                $modelData,
                $recordData,
                array_keys($schemaDef->properties())
            );
        }

        return [
            'schema' => $schemaDef->schema(),
            'model' => $modelData,
            'schema_updated_at' => $this->getFileTimestamp($schemaPath),
        ];
    }

    /**
     * Resolve schema name and last modification timestamp for a menu definition.
     *
     * @return array{schema: ?string, schema_updated_at: ?int}
     */
    protected function resolveSchemaMetadata(MenuDefinition $definition): array
    {
        $module = $this->repository->find($definition->module);
        if (!$module) {
            return [
                'schema' => $definition->schemaName,
                'schema_updated_at' => null,
            ];
        }

        $schemaName = $definition->schemaName();
        $schemaPath = $module->getPath() . '/schema/' . $schemaName . '.json';
        $timestamp = $this->getFileTimestamp($schemaPath);

        if ($timestamp !== null) {
            return [
                'schema' => $schemaName,
                'schema_updated_at' => $timestamp,
            ];
        }

        return [
            'schema' => $definition->schemaName,
            'schema_updated_at' => null,
        ];
    }

    /**
     * Compute a short state hash from module config/menu.php and schema/*.json modification times
     * so cached menus automatically invalidate when menu or schema files are modified.
     */
    protected function getFilesStateHash(?string $moduleName = null): string
    {
        $modules = $moduleName
            ? [$this->repository->find($moduleName)]
            : $this->repository->all();

        $parts = [];

        foreach ($modules as $module) {
            if (!$module) {
                continue;
            }

            $basePath = $module->getPath();
            $menuPath = $basePath . '/config/menu.php';
            $menuMtime = $this->getFileTimestamp($menuPath);

            if ($menuMtime !== null) {
                $parts[] = $menuPath . ':' . $menuMtime;
            }

            $schemaDir = $basePath . '/schema';
            if (is_dir($schemaDir)) {
                $schemaFiles = glob($schemaDir . '/*.json') ?: [];
                sort($schemaFiles);

                foreach ($schemaFiles as $schemaFile) {
                    $schemaMtime = $this->getFileTimestamp($schemaFile);
                    if ($schemaMtime !== null) {
                        $parts[] = $schemaFile . ':' . $schemaMtime;
                    }
                }
            }
        }

        if (empty($parts)) {
            return '';
        }

        return substr(md5(implode('|', $parts)), 0, 12);
    }

    protected function getFileTimestamp(string $path): ?int
    {
        clearstatcache(true, $path);

        if (!is_file($path)) {
            return null;
        }

        $mtime = @filemtime($path);

        return $mtime !== false ? (int) $mtime : null;
    }

    protected function resolveModelClass(
        string $moduleName,
        string $modelName,
        bool $preferPackageModel = false
    ): ?string {
        if (class_exists($modelName)) {
            return $modelName;
        }

        $tenantModel = (string) config(
            'asasflow.tenancy.tenant_model',
            \Bitsnio\AsasFlow\Features\Tenancy\Models\Tenant::class
        );

        $coreMap = [
            'Roles' => \Bitsnio\AsasFlow\Foundation\Models\Role::class,
            'Role' => \Bitsnio\AsasFlow\Foundation\Models\Role::class,
            'Permissions' => \Bitsnio\AsasFlow\Foundation\Models\Permission::class,
            'Permission' => \Bitsnio\AsasFlow\Foundation\Models\Permission::class,
            'Tenants' => $tenantModel,
            'Tenant' => $tenantModel,
            'TenantDomains' => \Bitsnio\AsasFlow\Features\Tenancy\Models\TenantDomain::class,
            'TenantDomain' => \Bitsnio\AsasFlow\Features\Tenancy\Models\TenantDomain::class,
        ];

        if ($preferPackageModel && isset($coreMap[$modelName]) && class_exists($coreMap[$modelName])) {
            return $coreMap[$modelName];
        }

        $namespace = rtrim((string) config('asasflow.modules.namespace', 'Modules'), '\\');
        $fqcn = "{$namespace}\\{$moduleName}\\Models\\{$modelName}";

        if (class_exists($fqcn)) {
            return $fqcn;
        }

        return $coreMap[$modelName] ?? null;
    }

    protected function populateModelFromRecord(
        array $modelTemplate,
        array $recordData,
        array $schemaPropertyKeys = []
    ): array {
        if (empty($modelTemplate) && !empty($schemaPropertyKeys)) {
            $populated = [];
            foreach ($schemaPropertyKeys as $key) {
                $populated[$key] = $recordData[$key] ?? null;
            }
            return $populated;
        }

        $filledModel = [];

        foreach ($modelTemplate as $key => $defaultValue) {
            if (is_array($defaultValue)) {
                if (empty($defaultValue) && isset($recordData[$key])) {
                    $filledModel[$key] = $recordData[$key];
                    continue;
                }

                $sourceData = array_key_exists($key, $recordData) && is_array($recordData[$key])
                    ? $recordData[$key]
                    : [];

                $filledModel[$key] = $this->populateModelFromRecord($defaultValue, $sourceData);
            } else {
                $filledModel[$key] = array_key_exists($key, $recordData)
                    ? $recordData[$key]
                    : $defaultValue;
            }
        }

        return $filledModel;
    }

    protected function findDefinitionByLink(
        array $definitions,
        string $normalizedLink
    ): ?MenuDefinition {
        foreach ($definitions as $definition) {
            if (!$definition->isGroup() && $definition->routePath() === $normalizedLink) {
                return $definition;
            }

            if ($definition->isResource()) {
                foreach (array_keys($definition->customActions()) as $actionName) {
                    if ($definition->customActionRoutePath($actionName) === $normalizedLink) {
                        return $definition;
                    }
                }
            }

            if (!empty($definition->children)) {
                $found = $this->findDefinitionByLink($definition->children, $normalizedLink);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    protected function normalizeLink(string $link): string
    {
        $normalized = trim($link, '/');

        if (Str::startsWith($normalized, 'api/')) {
            $normalized = substr($normalized, 4);
        }

        return trim($normalized, '/');
    }
}
