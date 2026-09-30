<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Bitsnio\Modules\Contracts\RepositoryInterface;

class PermissionService
{
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
                $permissions[] =
                    $this->makePermission(
                        $definition,
                        $action,
                        $label
                    );
            }
        }

        if ($definition->isAction()) {
            $permissions[] =
                $this->makePermission(
                    $definition,
                    'execute',
                    'Execute'
                );
        }

        foreach (
            $definition->children
            as $child
        ) {
            $this->buildPermissions(
                $child,
                $permissions
            );
        }
    }

    protected function makePermission(
        MenuDefinition $definition,
        string $action,
        string $label
    ): PermissionDefinition {
        $name =
            $definition->permissionKey()
            . '.'
            . $action;

        $custom =
            $definition->permissions[$action]
            ?? null;

        $description =
            is_string($custom)
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

        app(
            PermissionRegistrar::class
        )->forgetCachedPermissions();
    }

    /**
     * Get all permissions.
     */
    public function getAllPermissions(
        ?string $moduleName = null,
        bool $labelValueFormat = false
    ): array {
        $definitions =
            $this->getDefinitions(
                $moduleName
            );

        if (!$labelValueFormat) {
            return array_map(
                fn(PermissionDefinition $permission) =>
                $permission->toArray(),
                $definitions
            );
        }

        return array_map(
            fn(PermissionDefinition $permission) => [
                'label' =>
                $permission->description,

                'value' =>
                $permission->name,
            ],
            $definitions
        );
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

    public function getRolePermissions(
        string $roleName
    ): Collection {
        $role = Role::where(
            'name',
            $roleName
        )->firstOrFail();

        return $role
            ->permissions()
            ->orderBy('name')
            ->get();
    }

    public function updateRolePermissions(
        string $roleName,
        array $permissionNames
    ): Role {
        $role = Role::where(
            'name',
            $roleName
        )->firstOrFail();

        $role->syncPermissions(
            Permission::whereIn(
                'name',
                $permissionNames
            )
                ->where(
                    'guard_name',
                    'api'
                )
                ->get()
        );

        return $role;
    }
}
