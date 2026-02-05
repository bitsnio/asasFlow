<?php

namespace Bitsnio\Modules\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Bitsnio\Modules\Contracts\RepositoryInterface;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class MenuService
{
    protected $repository;
    protected $cacheKey = 'module_menu_permissions';
    protected $cacheDuration = 1440; // 24 hours

    public function __construct(RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getMenus(?string $moduleName = null, bool $filterByUserPermissions = false): array
    {
        $user = null;

        if ($filterByUserPermissions) {
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch (\Exception $e) {
                // Token invalid or not present
            }
        }

        $allMenus = [];

        // If module name is specified, only process that module
        if ($moduleName) {
            $module = $this->repository->find($moduleName);

            if ($module) {
                $menuPath = $module->getPath() . '/config/menu.php';
                if (file_exists($menuPath)) {
                    $menu = require $menuPath;
                    if (isset($menu['module'])) {
                        $processed = $this->processModuleMenu($menu['module']);

                        if ($filterByUserPermissions && $user) {
                            $processed = $this->filterMenuByUserPermissions($processed, $user);
                        }

                        if (!$filterByUserPermissions || !empty($processed)) {
                            $allMenus[$module->getName()] = $processed;
                        }
                    }
                }
            }
        }
        // Otherwise process all modules
        else {
            $modules = $this->repository->all();

            foreach ($modules as $module) {
                $menuPath = $module->getPath() . '/config/menu.php';
                if (file_exists($menuPath)) {
                    $menu = require $menuPath;
                    if (isset($menu['module'])) {
                        $processed = $this->processModuleMenu($menu['module']);

                        if ($filterByUserPermissions && $user) {
                            $processed = $this->filterMenuByUserPermissions($processed, $user);
                        }

                        if (!$filterByUserPermissions || !empty($processed)) {
                            $allMenus[$module->getName()] = $processed;
                        }
                    }
                }
            }
        }

        return $allMenus;
    }

    /**
     * Get formatted menu items
     */
    public function getFormattedMenu(
        ?string $moduleName = null,
        bool $filterByUserPermissions = false,
        bool $onlyVisible = false,
        $user = null // Add optional user parameter
    ): array {
        $cacheKey = $this->buildCacheKey($moduleName, $filterByUserPermissions, $onlyVisible, $user);

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($moduleName, $filterByUserPermissions, $onlyVisible, $user) {
            // Use provided user or get authenticated user if none provided
            $user = $user ?? ($filterByUserPermissions ? $this->getAuthenticatedUser() : null);

            $menus = [];
            $modules = $moduleName ? [$this->repository->find($moduleName)] : $this->repository->all();

            foreach ($modules as $module) {
                if (!$module) continue;

                $menuData = $this->getModuleMenuData($module, $filterByUserPermissions, $user);
                if ($menuData) {
                    $formatted = $this->formatMenu($menuData, $onlyVisible);
                    if ($formatted) {
                        $menus[] = $formatted;
                    }
                }
            }

            usort($menus, fn($a, $b) => $a['order'] <=> $b['order']);
            return $menus;
        });
    }

    protected function getModuleMenuData($module, bool $filterByPermissions, $user = null): ?array
    {
        $menuPath = $module->getPath() . '/config/menu.php';
        if (!file_exists($menuPath)) return null;

        $menu = require $menuPath;
        if (!isset($menu['module'])) return null;

        $processed = $this->processModuleMenu($menu['module']);

        if ($filterByPermissions) {
            $user = $user ?? $this->getAuthenticatedUser();
            if ($user) {
                $processed = $this->filterMenuByUserPermissions($processed, $user);
                if (empty($processed)) return null;
            }
        }

        return $processed;
    }

    protected function formatMenu(array $menu, bool $onlyVisible): ?array
    {
        // if ($this->shouldSkip($menu, $onlyVisible)) return null;

        $formatted = [
            'title' => $menu['title'] ?? $menu['name'],
            'icon' => $menu['icon'] ?? null,
            'order' => $menu['order'] ?? 999,
            'home' => $menu['home'] ?? false,
            'expanded' => $menu['expanded'] ?? false,
            // Include all other non-structural fields
            'routes_type' => $menu['routes_type'] ?? null,
            'model' => $menu['model'] ?? false,
            'hidden' => $menu['hidden'] ?? false,
        ];

        if (isset($menu['sub_module'])) {
            $formatted['children'] = [];

            foreach ($menu['sub_module'] as $subModule) {
                $subItem = $this->formatSubModule($menu['name'], $subModule, $onlyVisible);
                if ($subItem) {
                    $formatted['children'][] = $subItem;
                }
            }

            usort($formatted['children'], fn($a, $b) => $a['order'] <=> $b['order']);

            // Flatten if single child with no grandchildren
            if (count($formatted['children']) === 1 && empty($formatted['children'][0]['children'])) {
                $formatted['link'] = $formatted['children'][0]['link'];
                unset($formatted['children']);
            }
        }

        return $formatted;
    }

    protected function formatSubModule(string $moduleName, array $subModule, bool $onlyVisible): ?array
    {
        // if ($this->shouldSkip($subModule, $onlyVisible)) return null;

        $subItem = [
            'title' => $subModule['title'] ?? $subModule['name'],
            'icon' => $subModule['icon'] ?? null,
            'order' => $subModule['order'] ?? 999,
            'link' => $this->generateRoutePath($moduleName, $subModule['name']),
            'expanded' => $subModule['expanded'] ?? false,
            // Include other fields
            'routes_type' => $subModule['routes_type'] ?? null,
            'model' => $subModule['model'] ?? false,
            'hidden' => $subModule['hidden'] ?? false,
        ];

        if (isset($subModule['actions'])) {
            $subItem['children'] = [];

            foreach ($subModule['actions'] as $action) {
                $actionItem = $this->formatAction($moduleName, $subModule['name'], $action, $onlyVisible);
                if ($actionItem) {
                    $subItem['children'][] = $actionItem;
                }
            }

            usort($subItem['children'], fn($a, $b) => $a['order'] <=> $b['order']);
        }

        return $subItem;
    }

    protected function formatAction(string $moduleName, string $subModuleName, array $action, bool $onlyVisible): ?array
    {
        // if ($this->shouldSkip($action, $onlyVisible)) return null;

        return [
            'title' => $action['title'] ?? $action['name'],
            'icon' => $action['icon'] ?? null,
            'order' => $action['order'] ?? 999,
            'link' => $this->generateRoutePath($moduleName, $subModuleName, $action['name']),
            'routes_type' => $action['routes_type'] ?? null,
            'model' => $action['model'] ?? false,
            'hidden' => $action['hidden'] ?? false,
        ];
    }

    protected function shouldSkip(array $item, bool $onlyVisible): bool
    {
        return $onlyVisible && isset($item['hidden']) && !$item['hidden'];
    }

    protected function generateRoutePath(string $module, string $subModule, ?string $action = null): string
    {
        $path = strtolower("{$module}_{$subModule}");
        return $action ? "{$path}_{$action}" : $path;
    }

    // Update cache key generation
    protected function buildCacheKey(
        ?string $moduleName,
        bool $filterByPermissions,
        bool $onlyVisible,
        $user = null
    ): string {
        $key = $this->cacheKey . '_formatted';
        if ($moduleName) $key .= '_' . $moduleName;
        if ($filterByPermissions) {
            $userId = $user ? $user->id : ($this->getAuthenticatedUser()?->id ?? 'guest');
            $key .= '_user_' . $userId;
        }
        if (!$onlyVisible) $key .= '_all';
        return $key;
    }

    /**
     * Get authenticated user
     */
    protected function getAuthenticatedUser()
    {
        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Process module menu (original implementation)
     */
    protected function processModuleMenu(array $moduleMenu): array
    {
        return $moduleMenu;
    }

    /**
     * Filter by user permissions (original implementation)
     */
    protected function filterMenuByUserPermissions(array $menu, $user): array
    {
        if (!$user) return [];

        $moduleName = strtolower(Str::slug($menu['name'], ".") ?? '');
        $modulePermission = strtolower("{$moduleName}.{$moduleName}.view");

        if (!$user->can($modulePermission)) return [];

        if (isset($menu['sub_module'])) {
            foreach ($menu['sub_module'] as $key => $subModule) {
                $subModuleName = strtolower(Str::slug($subModule['name'], ".") ?? '');
                $subModulePermission = strtolower("{$moduleName}.{$subModuleName}.view");

                if (!$user->can($subModulePermission)) {
                    unset($menu['sub_module'][$key]);
                    continue;
                }

                if (isset($subModule['actions'])) {
                    foreach ($subModule['actions'] as $actionKey => $action) {
                        $actionName = strtolower(Str::slug($action['name'], ".") ?? '');
                        $actionPermission = strtolower("{$moduleName}.{$subModuleName}.{$actionName}.view");

                        if (!$user->can($actionPermission)) {
                            unset($menu['sub_module'][$key]['actions'][$actionKey]);
                        }
                    }

                    if (isset($menu['sub_module'][$key]['actions'])) {
                        $menu['sub_module'][$key]['actions'] = array_values($menu['sub_module'][$key]['actions']);
                    }
                }
            }
            $menu['sub_module'] = array_values($menu['sub_module']);
        }

        return $menu;
    }

    public function allModules(): array
    {
        return array_keys($this->repository->all());
    }

    public function clearCache(): void
    {
        $user = $user ?? $this->getAuthenticatedUser();
        if (!$user) return;

        $userId = $user->id;

        $cacheKeys = [
            $this->cacheKey . '_formatted_user_' . $userId,
            $this->cacheKey . '_formatted_user_' . $userId . '_all',
        ];

        // Get all modules to clear module-specific user caches
        $modules = $this->repository->all();
        foreach ($modules as $module) {
            $moduleName = $module->getName();
            $cacheKeys[] = $this->cacheKey . '_formatted_' . $moduleName . '_user_' . $userId;
            $cacheKeys[] = $this->cacheKey . '_formatted_' . $moduleName . '_user_' . $userId . '_all';
        }

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }


    public function clearUserMenuCache($userIds)
    {
        // Convert single user ID to array for consistent processing
        $userIds = is_array($userIds) ? $userIds : [$userIds];

        if (empty($userIds)) {
            return;
        }

        foreach ($userIds as $userId) {
            if (!is_numeric($userId) || $userId <= 0) {
                continue; // Skip invalid user IDs
            }

            $cacheKey = $this->cacheKey . '_formatted_user_' . $userId;
            Cache::forget($cacheKey);
        }
    }

    public function getSubModules(string $moduleName): array
    {
        $menus = $this->getMenus($moduleName);
        return $menus['menus'][$moduleName]['sub_module'] ?? [];
    }

    public function getSubModuleActions(string $moduleName, string $subModuleName): array
    {
        $subModules = $this->getSubModules($moduleName);
        foreach ($subModules as $subModule) {
            if ($subModule['name'] === $subModuleName) {
                return $subModule['actions'] ?? [];
            }
        }
        return [];
    }
}
