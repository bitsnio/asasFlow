<?php

namespace Bitsnio\Modules\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    protected $menuService;
    protected $cacheKey = 'module_permissions';
    protected $cacheDuration = 1440; // 24 hours

    protected $methodPermissionMap = [
        'GET' => 'view',
        'POST' => 'create',
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete'
    ];

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * Generate permissions for a module, submodule, or action
     * 
     * @param string $moduleName Module name
     * @param string $name Submodule or action name
     * @param string $title Display title
     * @param array|null $customPermissions Custom permission descriptions
     * @return array Generated permissions with names and descriptions
     */
    public function generateModulePermissions(string $moduleName, string $name, string $title, ?array $customPermissions = null): array
    {
        $identifier = Str::slug($moduleName . '.' . $name, '.');
        $permissions = [];

        $defaultPermissions = [
            'view' => 'View ' . $title,
            'create' => 'Create ' . $title,
            'update' => 'Update ' . $title,
            'delete' => 'Delete ' . $title,
        ];

        // Use custom permissions if provided, otherwise use defaults
        $permissionDescriptions = $customPermissions ?: $defaultPermissions;

        foreach ($permissionDescriptions as $action => $description) {
            $permissions[$action] = [
                'name' => $identifier . '.' . $action,
                'description' => $description
            ];
        }

        return $permissions;
    }

    /**
     * Get all permissions for all modules or a specific module
     * 
     * @param string|null $moduleName Optional module name to filter by
     * @param bool $labelValueFormat Whether to return in label-value format
     * @return array All permissions organized by module and section or as label-value pairs
     */
    public function getAllPermissions(?string $moduleName = null, bool $labelValueFormat = false): array
    {
        $cacheKey = $this->cacheKey . ($moduleName ? '_' . strtolower($moduleName) : '') . ($labelValueFormat ? '_lv' : '');

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($moduleName, $labelValueFormat) {
            $allMenus = $this->menuService->getMenus($moduleName);

            if ($labelValueFormat) {
                return $this->formatPermissionsForSelect($allMenus);
            }

            return $this->formatPermissionsStandard($allMenus);
        });
    }

    /**
     * Format permissions in standard hierarchical structure
     */
    protected function formatPermissionsStandard(array $allMenus): array
    {
        $allPermissions = [];

        foreach ($allMenus as $module => $menuData) {
            $modulePermissions = [];

            // Module level permissions
            $modulePermissionSet = $this->generateModulePermissions(
                $module,
                $menuData['name'],
                $menuData['title'] ?? $menuData['name'],
                $menuData['permissions'] ?? null
            );
            $modulePermissions[$menuData['name']] = $modulePermissionSet;

            // Process sub-modules
            if (isset($menuData['sub_module']) && is_array($menuData['sub_module'])) {
                foreach ($menuData['sub_module'] as $subModule) {
                    $subModuleName = $subModule['name'];
                    $subModulePermissionSet = $this->generateModulePermissions(
                        $module,
                        $subModuleName,
                        $subModule['title'] ?? $subModuleName,
                        $subModule['permissions'] ?? null
                    );
                    $modulePermissions[$subModuleName] = $subModulePermissionSet;

                    // Process actions
                    if (isset($subModule['actions']) && is_array($subModule['actions'])) {
                        foreach ($subModule['actions'] as $action) {
                            if (!isset($action['name'])) {
                                continue;
                            }

                            $actionName = $subModuleName . '.' . $action['name'];
                            $actionPermissionSet = $this->generateModulePermissions(
                                $module,
                                $actionName,
                                $action['title'] ?? $action['name'],
                                $action['permissions'] ?? null
                            );
                            $modulePermissions[$actionName] = $actionPermissionSet;
                        }
                    }
                }
            }

            $allPermissions[$module] = $modulePermissions;
        }

        return $allPermissions;
    }

    /**
     * Format permissions in label-value structure
     */
    protected function formatPermissionsForSelect(array $allMenus): array
    {
        $formattedPermissions = [];

        foreach ($allMenus as $module => $menuData) {
            // Module level permissions
            $modulePermissionSet = $this->generateModulePermissions(
                $module,
                $menuData['name'],
                $menuData['title'] ?? $menuData['name'],
                $menuData['permissions'] ?? null
            );

            foreach ($modulePermissionSet as $permissionData) {
                $formattedPermissions[] = [
                    'label' => $permissionData['description'],
                    'value' => $permissionData['name']
                ];
            }

            // Process sub-modules
            if (isset($menuData['sub_module']) && is_array($menuData['sub_module'])) {
                foreach ($menuData['sub_module'] as $subModule) {
                    $subModuleName = $subModule['name'];
                    $subModulePermissionSet = $this->generateModulePermissions(
                        $module,
                        $subModuleName,
                        $subModule['title'] ?? $subModuleName,
                        $subModule['permissions'] ?? null
                    );

                    foreach ($subModulePermissionSet as $permissionData) {
                        $formattedPermissions[] = [
                            'label' => $permissionData['description'],
                            'value' => $permissionData['name']
                        ];
                    }

                    // Process actions
                    if (isset($subModule['actions']) && is_array($subModule['actions'])) {
                        foreach ($subModule['actions'] as $action) {
                            if (!isset($action['name'])) {
                                continue;
                            }

                            $actionName = $subModuleName . '.' . $action['name'];
                            $actionPermissionSet = $this->generateModulePermissions(
                                $module,
                                $actionName,
                                $action['title'] ?? $action['name'],
                                $action['permissions'] ?? null
                            );

                            foreach ($actionPermissionSet as $permissionData) {
                                $formattedPermissions[] = [
                                    'label' => $permissionData['description'],
                                    'value' => $permissionData['name']
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $formattedPermissions;
    }
    /**
     * Define role with permissions
     *
     * @param array $config [
     *     'name' => 'role_name',          // Required
     *     'description' => 'Description', // Optional
     *     'modules' => ['Module1', 'Module2'], // All permissions for these modules
     *     'granular_modules' => [        // Specific permissions for these modules
     *         'Module1' => [
     *             'permissions' => ['view', 'create'], // Module-level
     *             'sub_modules' => [
     *                 'SubModule1' => [               // Sub-module level
     *                     'permissions' => ['view'],
     *                     'actions' => [
     *                         'Action1' => ['create'] // Action-level
     *                     ]
     *                 ]
     *             ]
     *         ]
     *     ]
     * ]
     * @return array
     */
    public function defineRoleWithPermissions(array $config): array
    {
        $this->validateRoleConfig($config);
        $modules = $config['modules'];
        $this->clearCache($modules);


        $allMenus = $this->menuService->getMenus();

        // Generate permissions for each module and create a case map
        $modulePermissions = [];
        $moduleNameMap = [];
        foreach ($allMenus as $moduleName => $menuData) {
            $modulePermissions[$moduleName] = $this->generatePermissionsForModule($moduleName, $menuData);
            $moduleNameMap[strtolower($moduleName)] = $moduleName;
        }

        // Process permissions
        $allPermissions = $this->processModulesWithAllPermissions(
            $modulePermissions,
            $config['modules'] ?? [],
            $moduleNameMap
        );

        $granularPermissions = $this->processGranularModules(
            $modulePermissions,
            $config['granular_modules'] ?? [],
            $moduleNameMap
        );

        $permissions = array_merge($allPermissions, $granularPermissions);

        $guardName = $config['guard_name'] ?? 'api';

        $this->ensurePermissionsExist($permissions, $guardName);

        $role = Role::updateOrCreate(
            ['name' => $config['name'], 'guard_name' => $guardName],
            // ['description' => $config['description'] ?? null]
        );

        $role->syncPermissions(
            Permission::whereIn('name', array_column($permissions, 'name'))
                ->where('guard_name', $guardName)
                ->get()
        );

        return [
            'role' => $role->name,
            'permissions_count' => count($permissions),
            'modules' => array_merge(
                array_keys($config['modules'] ?? []),
                array_keys($config['granular_modules'] ?? [])
            )
        ];
    }

    /**
     * Create role with flexible module input and permission exclusion
     * 
     * @param string $roleName
     * @param string|null $roleDescription
     * @param string|array $moduleNames Single module or array of modules
     * @param array $permissionNames Specific permissions to add (optional)
     * @param array $exceptPermissions Permissions to exclude (optional)
     * @param string $guardName
     * @return array
     */
    public function generateOrUpdateMenuPermissions(
        $moduleNames,
        string $guardName = 'api'
    ): array {
        // Normalize modules
        $modules = is_array($moduleNames) ? $moduleNames : [$moduleNames];

        // 1. Gather all permissions (still label/value format)
        $allModulePermissions = [];
        foreach ($modules as $module) {
            $perms = $this->getAllPermissions($module, true); // label/value format
            foreach ($perms as $perm) {
                $allModulePermissions[$perm['value']] = $perm; // keep same format
            }
        }

        // 2. Ensure all remaining permissions exist in DB
        $this->ensurePermissionsExist(array_values($allModulePermissions), $guardName);

        //clear Cache for specific modules
        $this->clearCache($modules);

        return [
            'assigned_permissions' => array_values($allModulePermissions),
            'total_permissions' => count($allModulePermissions),
            'modules' => $modules
        ];
    }

    /**
     * Create role with flexible module input and permission exclusion
     * 
     * @deprecated since version X.X — Use {@see generateOrUpdateMenuPermissions()} instead.
     * @param string $roleName
     * @param string|null $roleDescription
     * @param string|array $moduleNames Single module or array of modules
     * @param array $permissionNames Specific permissions to add (optional)
     * @param array $exceptPermissions Permissions to exclude (optional)
     * @param string $guardName
     * @return array
     */
    public function createRoleWithModuleBasedPermissions(
        string $roleName,
        ?string $roleDescription,
        $moduleNames,
        array $permissionNames = [],
        array $exceptPermissions = [],
        string $guardName = 'api'
    ): array {
        // Normalize modules
        $modules = is_array($moduleNames) ? $moduleNames : [$moduleNames];

        // 1. Gather all permissions (still label/value format)
        $allModulePermissions = [];
        foreach ($modules as $module) {
            $perms = $this->getAllPermissions($module, true); // label/value format
            foreach ($perms as $perm) {
                $allModulePermissions[$perm['value']] = $perm; // keep same format
            }
        }

        // 2. Exclude permissions from exceptPermissions list
        $allModulePermissions = array_filter($allModulePermissions, function ($perm) use ($exceptPermissions) {
            return !in_array($perm['value'], $exceptPermissions);
        });

        // 3. If $permissionNames is provided → filter only valid ones
        if (!empty($permissionNames)) {
            $allModulePermissions = array_filter($allModulePermissions, function ($perm) use ($permissionNames) {
                return in_array($perm['value'], $permissionNames);
            });
        }

        // 4. Ensure all remaining permissions exist in DB
        $this->ensurePermissionsExist(array_values($allModulePermissions), $guardName);

        // 5. Create role or get existing
        $role = Role::firstOrCreate(
            ['name' => $roleName, 'guard_name' => $guardName],
            // ['description' => $roleDescription]
        );

        // 6. Assign permissions by name
        $role->syncPermissions(array_column($allModulePermissions, 'value'));
        $this->clearCache();

        return [
            'role' => $role,
            'assigned_permissions' => array_values($allModulePermissions),
            'excluded_permissions' => $exceptPermissions,
            'total_permissions' => count($allModulePermissions),
            'modules' => $modules
        ];
    }

    /**
     * Update role permissions - only validates against specified module permissions
     * 
     * @param string $roleName
     * @param array $moduleNames Modules to include
     * @param array $permissionNames Specific permissions to add
     * @param string $guardName
     * @return array
     */
    // public function updateRoleModuleBasedPermissions(
    //     string $roleName,
    //     array $moduleNames,
    //     array $permissionNames = [],
    //     string $guardName = 'api'
    // ): array {
    //     // Get the role
    //     $role = Role::where('name', $roleName)
    //         ->where('guard_name', $guardName)
    //         ->firstOrFail();

    //     // 1. Get all permissions for specified modules
    //     $modulePermissions = [];
    //     foreach ($moduleNames as $module) {
    //         $modulePerms = $this->getAllPermissions($module, true);
    //         foreach ($modulePerms as $perm) {
    //             $modulePermissions[$perm['value']] = $perm;
    //         }
    //     }

    //     // 2. Validate specific permissions against module permissions
    //     $validSpecificPermissions = [];
    //     foreach ($permissionNames as $permission) {
    //         if (isset($modulePermissions[$permission])) {
    //             $validSpecificPermissions[$permission] = $modulePermissions[$permission];
    //         }
    //     }

    //     // 3. Merge all valid permissions
    //     $finalPermissions = array_merge($modulePermissions, $validSpecificPermissions);

    //     // 4. Sync permissions
    //     $role->syncPermissions(array_keys($finalPermissions));
    //     $this->clearCache();

    //     return [
    //         'role' => $roleName,
    //         'updated_permissions' => array_values($finalPermissions),
    //         'total_permissions' => count($finalPermissions),
    //         'modules' => $moduleNames
    //     ];
    // }
    /**
     * Normalize module names array for case-insensitive comparison
     * 
     * @param array $moduleNames
     * @return array
     */
    protected function normalizeModuleNames(array $moduleNames): array
    {
        return array_map('strtolower', $moduleNames);
    }

    /**
     * Normalize granular module configuration for case-insensitive comparison
     * 
     * @param array $granularModules
     * @return array
     */
    protected function normalizeGranularModules(array $granularModules): array
    {
        $normalized = [];
        foreach ($granularModules as $moduleName => $moduleConfig) {
            $normalized[strtolower($moduleName)] = $moduleConfig;
        }
        return $normalized;
    }

    /**
     * Normalize module permissions keys for case-insensitive comparison
     * 
     * @param array $modulePermissions
     * @return array
     */
    protected function normalizeModulePermissionsKeys(array $modulePermissions): array
    {
        $normalized = [];
        foreach ($modulePermissions as $moduleName => $permissions) {
            $normalized[strtolower($moduleName)] = $permissions;
        }
        return $normalized;
    }

    /**
     * Generate complete permissions structure for a module
     * 
     * @param string $moduleName Module name
     * @param array $moduleData Module data
     * @return array Permissions structure
     */
    protected function generatePermissionsForModule(string $moduleName, array $moduleData): array
    {
        // Input validation
        if (empty($moduleName)) {
            throw new \InvalidArgumentException('Module name cannot be empty');
        }

        if (empty($moduleData['name'])) {
            throw new \InvalidArgumentException('Module data must contain "name"');
        }

        // $moduleKey = $moduleData['name'];

        // Main module structure
        $result = [
            'name' => $moduleData['name'],
            'title' => $moduleData['title'] ?? $moduleData['name'],
            'permissions' => $this->generateModulePermissions(
                $moduleName,
                $moduleData['name'],
                $moduleData['title'] ?? $moduleData['name'],
                $moduleData['permissions'] ?? null
            ),
            'submodules' => []
        ];

        // Process submodules
        if (!empty($moduleData['sub_module']) && is_array($moduleData['sub_module'])) {
            foreach ($moduleData['sub_module'] as $subModule) {
                if (empty($subModule['name'])) {
                    continue; // skip if no name
                }

                $subModuleKey = $subModule['name'];

                $result['submodules'][$subModuleKey] = [
                    'name' => $subModule['name'],
                    'title' => $subModule['title'] ?? $subModule['name'],
                    'permissions' => $this->generateModulePermissions(
                        $moduleName,
                        $subModule['name'],
                        $subModule['title'] ?? $subModule['name'],
                        $subModule['permissions'] ?? null
                    ),
                    'actions' => []
                ];

                // Process actions
                if (!empty($subModule['actions']) && is_array($subModule['actions'])) {
                    foreach ($subModule['actions'] as $action) {
                        if (empty($action['name'])) {
                            continue;
                        }

                        $actionKey = $action['name'];
                        $actionIdentifier = $subModule['name'] . '.' . $actionKey;

                        $result['submodules'][$subModuleKey]['actions'][$actionKey] = [
                            'identifier' => $actionIdentifier,
                            'title' => $action['title'] ?? $actionKey,
                            'permissions' => $this->generateModulePermissions(
                                $moduleName,
                                $actionIdentifier,
                                $action['title'] ?? $actionKey,
                                $action['permissions'] ?? null
                            )
                        ];
                    }
                }
            }
        }

        return $result;
    }



    protected function processModulesWithAllPermissions(array $modulePermissions, array $moduleNames, array $moduleNameMap): array
    {
        $permissions = [];

        foreach ($moduleNames as $moduleName) {
            $actualModuleName = $moduleNameMap[strtolower($moduleName)] ?? null;
            if (!$actualModuleName || !isset($modulePermissions[$actualModuleName])) {
                continue;
            }

            $permissions = array_merge(
                $permissions,
                $this->collectAllPermissions($modulePermissions[$actualModuleName])
            );
        }

        return $permissions;
    }

    protected function collectAllPermissions(array $moduleData): array
    {
        $permissions = [];

        // Module permissions
        if (isset($moduleData['permissions']) && is_array($moduleData['permissions'])) {
            foreach ($moduleData['permissions'] as $permData) {
                $permissions[] = [
                    'name' => $permData['name'],
                    'description' => $permData['description']
                ];
            }
        }

        // Submodule permissions
        if (isset($moduleData['submodules']) && is_array($moduleData['submodules'])) {
            foreach ($moduleData['submodules'] as $subModule) {
                if (isset($subModule['permissions']) && is_array($subModule['permissions'])) {
                    foreach ($subModule['permissions'] as $permData) {
                        $permissions[] = [
                            'name' => $permData['name'],
                            'description' => $permData['description']
                        ];
                    }
                }

                // Action permissions
                if (isset($subModule['actions']) && is_array($subModule['actions'])) {
                    foreach ($subModule['actions'] as $action) {
                        if (isset($action['permissions']) && is_array($action['permissions'])) {
                            foreach ($action['permissions'] as $permData) {
                                $permissions[] = [
                                    'name' => $permData['name'],
                                    'description' => $permData['description']
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $permissions;
    }

    protected function processGranularModules(array $modulePermissions, array $modulesConfig, array $moduleNameMap): array
    {
        $permissions = [];

        foreach ($modulesConfig as $moduleName => $moduleConfig) {
            // Find the actual module name using the case map
            $actualModuleName = $moduleNameMap[strtolower($moduleName)] ?? null;

            if (!$actualModuleName || !isset($modulePermissions[$actualModuleName])) {
                continue;
            }

            $moduleData = $modulePermissions[$actualModuleName];

            // Handle module-level permissions
            if (!empty($moduleConfig['permissions']) && isset($moduleData['module'])) {
                $permissions = array_merge(
                    $permissions,
                    $this->filterPermissions(
                        $moduleData['module']['permissions'],
                        $moduleConfig['permissions']
                    )
                );
            }

            // Handle sub-modules
            if (!empty($moduleConfig['sub_modules'])) {
                foreach ($moduleConfig['sub_modules'] as $subModuleName => $subModuleConfig) {
                    // Create a map of lowercase submodule names to actual keys
                    $subModuleMap = [];
                    foreach (array_keys($moduleData) as $key) {
                        $subModuleMap[strtolower($key)] = $key;
                    }

                    // Find the actual submodule name
                    $actualSubModuleName = $subModuleMap[strtolower($subModuleName)] ?? null;

                    // Sub-module level permissions
                    if (!empty($subModuleConfig['permissions']) && $actualSubModuleName && isset($moduleData[$actualSubModuleName])) {
                        $permissions = array_merge(
                            $permissions,
                            $this->filterPermissions(
                                $moduleData[$actualSubModuleName]['permissions'],
                                $subModuleConfig['permissions']
                            )
                        );
                    }

                    // Action permissions
                    if (!empty($subModuleConfig['actions'])) {
                        foreach ($subModuleConfig['actions'] as $actionName => $actionPermTypes) {
                            $possibleActionKey = $subModuleName . '.' . $actionName;

                            // Try to find the actual action key
                            $actualActionKey = null;
                            foreach (array_keys($moduleData) as $key) {
                                if (strtolower($key) === strtolower($possibleActionKey)) {
                                    $actualActionKey = $key;
                                    break;
                                }
                            }

                            if ($actualActionKey && isset($moduleData[$actualActionKey])) {
                                $permissions = array_merge(
                                    $permissions,
                                    $this->filterPermissions(
                                        $moduleData[$actualActionKey]['permissions'],
                                        $actionPermTypes
                                    )
                                );
                            }
                        }
                    }
                }
            }
        }

        return $permissions;
    }

    /**
     * Find key in array in a case-insensitive way
     * 
     * @param array $array
     * @param string $search
     * @return string|null
     */
    protected function findCaseInsensitiveKey(array $array, string $search): ?string
    {
        $lowerSearch = strtolower($search);
        foreach ($array as $key => $value) {
            if (strtolower($key) === $lowerSearch) {
                return $key;
            }
        }
        return null;
    }

    protected function filterPermissions(array $availablePermissions, array $requestedTypes): array
    {
        return array_intersect_key(
            $availablePermissions,
            array_flip($requestedTypes)
        );
    }

    protected function ensurePermissionsExist(array $permissions, string $guardName = 'api'): void
    {
        $permissions = array_map(function ($perm) {
            return [
                'name' => $perm['value'] ?? $perm['name'] ?? null,
                'description' => $perm['label'] ?? $perm['description'] ?? ''
            ];
        }, $permissions);

        $permissions = array_filter($permissions, fn($p) => !empty($p['name']));

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name'], 'guard_name' => $guardName],
                ['description' => $permissionData['description']]
            );
        }
    }


    protected function validateRoleConfig(array $config): void
    {
        if (empty($config['name'])) {
            throw new \InvalidArgumentException('Role name is required');
        }

        if (empty($config['modules']) && empty($config['granular_modules'])) {
            throw new \InvalidArgumentException('At least one module must be specified');
        }
    }

    /**
     * Assign role to users
     *
     * @param string|array $roleNames
     * @param int|array $userIds
     * @param bool $replaceExisting
     * @return array
     */
    public function assignRoleToUsers($roleNames, $userIds, bool $replaceExisting = true): array
    {
        $roles = Role::whereIn('name', Arr::wrap($roleNames))->get();
        if ($roles->isEmpty()) {
            throw new \InvalidArgumentException('No matching roles found');
        }

        $arrayUserIds = Arr::wrap($userIds);

        $results = [];
        foreach ($arrayUserIds as $userId) {
            $user = User::find($userId);
            if (!$user) {
                $results[$userId] = ['success' => false, 'message' => 'User not found'];
                continue;
            }

            $replaceExisting
                ? $user->syncRoles($roles)
                : $user->assignRole($roles);

            $results[$userId] = [
                'success' => true,
                'user_name' => $user->name,
                'assigned_roles' => $roles->pluck('name')->toArray()
            ];
        }

        // $this->clearCache();

        $this->menuService->clearUserMenuCache($arrayUserIds);

        return $results;
    }

    /**
     * Get permissions for a role
     *
     * @param string $roleName
     * @return Collection
     */
    public function getRolePermissions(string $roleName): Collection
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        return $role->permissions()->orderBy('name')->get();
    }

    /**
     * Update role permissions
     *
     * @param string $roleName
     * @param array $config (same structure as defineRoleWithPermissions)
     * @return array
     */
    public function updateRolePermissions(string $roleName, array $config): array
    {
        $config['name'] = $roleName;
        return $this->defineRoleWithPermissions($config);
    }

    /**
     * Sync permissions to the database with descriptions
     * 
     * @return void
     */
    public function syncPermissions(): void
    {
        $allPermissions = $this->getAllPermissions();
        $permissionsToSync = [];

        // Flatten the permissions structure
        foreach ($allPermissions as $module => $modulePerm) {
            foreach ($modulePerm as $section => $actions) {
                foreach ($actions as $action => $permissionData) {
                    $permissionsToSync[] = [
                        'name' => $permissionData['name'],
                        'description' => $permissionData['description'],
                        'guard_name' => 'api'
                    ];
                }
            }
        }

        // Upsert permissions
        foreach ($permissionsToSync as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                ['description' => $permission['description']]
            );
        }

        $this->clearCache();
    }
    /**
     * Get required permission for a route and HTTP method
     * 
     * @param string $route The route path
     * @param string $method HTTP method (GET, POST, etc.)
     * @return string|null Permission name or null if not found
     */
    public function getRequiredPermission(string $route, string $method): ?string
    {
        $method = strtoupper($method);
        $permissionType = $this->methodPermissionMap[$method] ?? 'view';

        $routeParts = collect(explode('/', trim($route, '/')));

        // First part is usually the module name
        if ($routeParts->isEmpty()) {
            return null;
        }

        $moduleName = $routeParts->first();

        // Extract the route parts after the module name
        $routePath = $routeParts->slice(1)->values()->implode('.');
        if (empty($routePath)) {
            $routePath = $moduleName;
        }

        // Format the identifier
        $identifier = Str::slug($moduleName) . '.' . Str::slug($routePath, '.');

        return $identifier . '.' . $permissionType;
    }

    public static function getRoles()
    {
        return Role::select('name as role_name')
            ->orderBy('name')
            ->get();
    }

    /**
     * Clear permission cache
     * 
     * @return void
     */
    public function clearCache(array $modules = []): void
    {
       
        //clear Spatie permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

       
        foreach ($modules as $moduleName) {
            $moduleCacheKey = $this->cacheKey . '_' . $moduleName . '_lv';
            Cache::forget($moduleCacheKey);      // Without prefix
        }
    }

    /**
     * Revoke permissions from a role
     * 
     * @param string $roleName Name of the role
     * @param array $permissionsToRemove Array of permission names to remove
     * @param string $guardName
     * @return array Result of operation
     */
    public function revokePermissionsFromRole(
        string $roleName,
        array $permissionsToRemove,
        string $guardName = 'api'
    ): array {
        // Get the role
        $role = Role::where('name', $roleName)
            ->where('guard_name', $guardName)
            ->firstOrFail();

        // Get current permissions
        $currentPermissions = $role->permissions()->pluck('name')->toArray();

        // Filter permissions that are actually assigned
        $existingPermissionsToRemove = array_intersect($permissionsToRemove, $currentPermissions);

        // Revoke the permissions
        if (!empty($existingPermissionsToRemove)) {
            $role->revokePermissionTo($existingPermissionsToRemove);
        }

        $this->clearCache();

        return [
            'removed' => $existingPermissionsToRemove,
            'not_assigned' => array_diff($permissionsToRemove, $currentPermissions),
            'total_removed' => count($existingPermissionsToRemove),
            'current_permissions' => array_diff($currentPermissions, $existingPermissionsToRemove)
        ];
    }

    /**
     * Add permissions to a role
     * 
     * @param string $roleName Name of the role
     * @param array $permissionsToAdd Array of permission names to add
     * @param string $guardName
     * @return array Result of operation
     */
    public function addPermissionsToRole(
        string $roleName,
        array $permissionsToAdd,
        string $guardName = 'api'
    ): array {
        // Get the role
        $role = Role::where('name', $roleName)
            ->where('guard_name', $guardName)
            ->firstOrFail();

        // Get current permissions
        $currentPermissions = $role->permissions()->pluck('name')->toArray();

        // Filter permissions that aren't already assigned
        $newPermissions = array_diff($permissionsToAdd, $currentPermissions);

        // Add the new permissions
        if (!empty($newPermissions)) {
            $role->givePermissionTo($newPermissions);
        }

        $this->clearCache();

        return [
            'added' => $newPermissions,
            'already_existed' => array_intersect($permissionsToAdd, $currentPermissions),
            'total_added' => count($newPermissions),
            'current_permissions' => array_merge($currentPermissions, $newPermissions)
        ];
    }
}
