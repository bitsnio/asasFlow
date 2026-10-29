<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\AsasFlow\Foundation\Models\Permission;
use Bitsnio\AsasFlow\Foundation\Models\Role;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    protected string $cacheKey = 'asasflow_module_permissions';
    protected int $cacheDuration = 3600;

    public function __construct(
        protected MenuService $menuService,
    ) {}

    /**
     * Build all permission definitions.
     *
     * @return PermissionDefinition[]
     */
    public function getDefinitions(
        ?string $moduleName = null
    ): array {
        $definitions = [];

        foreach (
            $this->menuService->getDefinitions(
                $moduleName
            ) as $menu
        ) {
            $this->buildPermissions(
                $menu,
                $definitions
            );
        }

        return $definitions;
    }

    protected function buildPermissions(
        MenuDefinition $definition,
        array &$permissions
    ): void {
        if ($definition->isResource()) {
            foreach (
                [
                    'view' => 'View',
                    'create' => 'Create',
                    'update' => 'Update',
                    'delete' => 'Delete',
                ] as $action => $label
            ) {
                $permissions[] = $this->makePermission(
                    $definition,
                    $action,
                    $label
                );
            }

            foreach ($definition->customActions() as $name => $actionConfig) {
                $permissions[] = new PermissionDefinition(
                    name: $definition->customActionPermissionKey($name),
                    description: $definition->customActionPermissionLabel($name),
                    guard: 'api',
                    module: $definition->module,
                    menuPath: $definition->permissionKey(),
                    method: $definition->customActionMethod($name),
                );
            }
        }

        foreach ($definition->children as $child) {
            $this->buildPermissions($child, $permissions);
        }
    }

    protected function makePermission(
        MenuDefinition $definition,
        string $action,
        string $label
    ): PermissionDefinition {
        $name = $definition->permissionKey() . '.' . $action;

        $custom = $definition->permissions[$action] ?? null;

        $description = is_string($custom)
            ? $custom
            : (
                is_array($custom)
                ? (
                    $custom['description']
                    ?? "{$label} {$definition->title}"
                )
                : "{$label} {$definition->title}"
            );

        return new PermissionDefinition(
            name: $name,
            description: $description,
            guard: 'api',
            module: $definition->module,
            menuPath: $definition->permissionKey(),
            method: $definition->isAction()
                ? $definition->actionMethod()
                : null,
        );
    }

    /**
     * Sync menu permissions to Spatie.
     */
    public function syncPermissions(
        ?string $moduleName = null
    ): void {
        foreach (
            $this->getDefinitions($moduleName)
            as $definition
        ) {
            Permission::updateOrCreate(
                [
                    'name' => $definition->name,
                    'guard_name' => $definition->guard,
                ],
                [
                    'description' =>
                    $definition->description,
                ]
            );
        }

        $this->clearCache($moduleName ? [$moduleName] : []);
    }

    /**
     * Get all permissions.
     */
    public function getAllPermissions(
        ?string $moduleName = null,
        bool $labelValueFormat = false
    ): array {
        $cacheKey = $this->cacheKey
            . ($moduleName ? '_' . strtolower($moduleName) : '')
            . ($labelValueFormat ? '_lv' : '');

        return Cache::remember($cacheKey, $this->cacheDuration, function () use (
            $moduleName,
            $labelValueFormat
        ) {
            $definitions = $this->getDefinitions($moduleName);

            if (!$labelValueFormat) {
                return array_map(
                    fn(PermissionDefinition $permission) => $permission->toArray(),
                    $definitions
                );
            }

            return array_map(
                fn(PermissionDefinition $permission) => [
                    'label' => $permission->description,
                    'value' => $permission->name,
                ],
                $definitions
            );
        });
    }

    /**
     * Get the permission required for a route.
     */
    public function getRequiredPermission(
        string $route,
        string $method
    ): ?string {
        return app(
            PermissionResolver::class
        )->resolve(
            $route,
            $method
        );
    }

    /**
     * Get permissions assigned to a role (by ID or name).
     */
    public function getRolePermissions(
        int|string $role,
        string $guardName = 'api'
    ): Collection {
        $roleModel = $this->resolveRole($role, $guardName);

        return $roleModel
            ->permissions()
            ->orderBy('name')
            ->get();
    }

    /**
     * Assign or sync permissions to a role.
     */
    public function assignPermissionsToRole(
        int|string $role,
        array $permissionNames,
        bool $sync = true,
        string $guardName = 'api'
    ): Role {
        $roleModel = $this->resolveRole($role, $guardName);

        $this->ensurePermissionNamesExist($permissionNames, $guardName);

        $permissions = Permission::whereIn('name', $permissionNames)
            ->where('guard_name', $guardName)
            ->get();

        if ($sync) {
            $roleModel->syncPermissions($permissions);
        } else {
            $roleModel->givePermissionTo($permissions);
        }

        $this->clearCache();

        return $roleModel;
    }

    /**
     * Replace all permissions on a role.
     */
    public function updateRolePermissions(
        int|string $role,
        array $permissionNames,
        string $guardName = 'api'
    ): Role {
        return $this->assignPermissionsToRole(
            $role,
            $permissionNames,
            true,
            $guardName
        );
    }

    /**
     * Add permissions to an existing role without removing existing ones.
     */
    public function addPermissionsToRole(
        int|string $role,
        array $permissionsToAdd,
        string $guardName = 'api'
    ): array {
        $roleModel = $this->resolveRole($role, $guardName);

        $this->ensurePermissionNamesExist($permissionsToAdd, $guardName);

        $currentPermissions = $roleModel->permissions()->pluck('name')->toArray();
        $newPermissions = array_values(array_diff($permissionsToAdd, $currentPermissions));

        if (!empty($newPermissions)) {
            $roleModel->givePermissionTo($newPermissions);
        }

        $this->clearCache();

        return [
            'added' => $newPermissions,
            'already_existed' => array_values(array_intersect($permissionsToAdd, $currentPermissions)),
            'total_added' => count($newPermissions),
            'current_permissions' => array_values(array_unique(array_merge($currentPermissions, $newPermissions))),
        ];
    }

    /**
     * Revoke specific permissions from a role.
     */
    public function revokePermissionsFromRole(
        int|string $role,
        array $permissionsToRemove,
        string $guardName = 'api'
    ): array {
        $roleModel = $this->resolveRole($role, $guardName);

        $currentPermissions = $roleModel->permissions()->pluck('name')->toArray();
        $existingPermissionsToRemove = array_values(array_intersect($permissionsToRemove, $currentPermissions));

        if (!empty($existingPermissionsToRemove)) {
            $roleModel->revokePermissionTo($existingPermissionsToRemove);
        }

        $this->clearCache();

        return [
            'removed' => $existingPermissionsToRemove,
            'not_assigned' => array_values(array_diff($permissionsToRemove, $currentPermissions)),
            'total_removed' => count($existingPermissionsToRemove),
            'current_permissions' => array_values(array_diff($currentPermissions, $existingPermissionsToRemove)),
        ];
    }

    /**
     * Create or update a role with module-based permissions and optional inclusions/exclusions.
     */
    public function createRoleWithModuleBasedPermissions(
        string $roleName,
        ?string $roleDescription,
        string|array $moduleNames,
        array $permissionNames = [],
        array $exceptPermissions = [],
        string $guardName = 'api'
    ): array {
        $modules = is_array($moduleNames) ? $moduleNames : [$moduleNames];

        $allModulePermissions = [];
        foreach ($modules as $module) {
            $perms = $this->getAllPermissions($module, true);
            foreach ($perms as $perm) {
                $allModulePermissions[$perm['value']] = $perm;
            }
        }

        if (!empty($exceptPermissions)) {
            $allModulePermissions = array_filter(
                $allModulePermissions,
                fn(array $perm) => !in_array($perm['value'], $exceptPermissions, true)
            );
        }

        if (!empty($permissionNames)) {
            $allModulePermissions = array_filter(
                $allModulePermissions,
                fn(array $perm) => in_array($perm['value'], $permissionNames, true)
            );
        }

        $this->ensurePermissionsExist(array_values($allModulePermissions), $guardName);

        $roleAttributes = ['name' => $roleName, 'guard_name' => $guardName];
        $role = Role::updateOrCreate(
            $roleAttributes,
            ['description' => $roleDescription]
        );

        $role->syncPermissions(array_column($allModulePermissions, 'value'));

        $this->clearCache($modules);

        return [
            'role' => $role,
            'assigned_permissions' => array_values($allModulePermissions),
            'excluded_permissions' => $exceptPermissions,
            'total_permissions' => count($allModulePermissions),
            'modules' => $modules,
        ];
    }

    /**
     * Assign or sync roles to one or more users.
     */
    public function assignRoleToUsers(
        array|string|int $roles,
        array|int|string $userIds,
        bool $replaceExisting = true,
        string $guardName = 'api'
    ): array {
        $roleList = Arr::wrap($roles);
        $ids = array_filter($roleList, 'is_numeric');
        $names = array_filter($roleList, fn($r) => !is_numeric($r));

        $roleModels = Role::where('guard_name', $guardName)
            ->where(function ($query) use ($ids, $names) {
                if (!empty($ids)) {
                    $query->orWhereIn('id', $ids);
                }
                if (!empty($names)) {
                    $query->orWhereIn('name', $names);
                }
            })
            ->get();

        if ($roleModels->isEmpty()) {
            throw new \InvalidArgumentException('No matching roles found');
        }

        $userModelClass = $this->resolveUserModelClass();
        $arrayUserIds = Arr::wrap($userIds);
        $results = [];

        foreach ($arrayUserIds as $userId) {
            $user = $userModelClass::find($userId);

            if (!$user) {
                $results[$userId] = [
                    'success' => false,
                    'message' => 'User not found',
                ];
                continue;
            }

            $replaceExisting
                ? $user->syncRoles($roleModels)
                : $user->assignRole($roleModels);

            $results[$userId] = [
                'success' => true,
                'user_name' => $user->name ?? trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')),
                'assigned_roles' => $roleModels->pluck('name')->toArray(),
            ];
        }

        $this->menuService->clearUserMenuCache($arrayUserIds);

        return $results;
    }

    /**
     * Clear Spatie and module permission caches.
     */
    public function clearCache(array $modules = []): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Cache::forget($this->cacheKey);
        Cache::forget($this->cacheKey . '_lv');

        $targetModules = !empty($modules)
            ? $modules
            : $this->menuService->allModules();

        foreach ($targetModules as $moduleName) {
            $lower = strtolower($moduleName);
            Cache::forget($this->cacheKey . '_' . $lower);
            Cache::forget($this->cacheKey . '_' . $lower . '_lv');
        }
    }

    protected function resolveRole(
        int|string $role,
        string $guardName = 'api'
    ): Role {
        $query = Role::where('guard_name', $guardName);

        if (is_numeric($role)) {
            return $query->where('id', (int) $role)->firstOrFail();
        }

        return $query->where('name', $role)->firstOrFail();
    }

    protected function ensurePermissionsExist(
        array $permissions,
        string $guardName = 'api'
    ): void {
        foreach ($permissions as $perm) {
            $name = $perm['value'] ?? $perm['name'] ?? null;
            if (!$name) {
                continue;
            }

            $description = $perm['label'] ?? $perm['description'] ?? $name;

            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => $guardName],
                ['description' => $description]
            );
        }
    }

    protected function ensurePermissionNamesExist(
        array $permissionNames,
        string $guardName = 'api'
    ): void {
        if (empty($permissionNames)) {
            return;
        }

        $definedMap = [];
        foreach ($this->getDefinitions() as $def) {
            $definedMap[$def->name] = $def->description;
        }

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => $guardName],
                ['description' => $definedMap[$name] ?? $name]
            );
        }
    }

    protected function resolveUserModelClass(): string
    {
        $configured = config('asasflow.install.jwt.user_model');
        if (is_string($configured) && class_exists($configured)) {
            return $configured;
        }

        $authModel = config('auth.providers.users.model', \App\Models\User::class);
        if (is_string($authModel) && class_exists($authModel)) {
            return $authModel;
        }

        return \App\Models\User::class;
    }
}
