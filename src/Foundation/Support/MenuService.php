<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\Modules\Contracts\RepositoryInterface;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class MenuService
{
    public function __construct(
        protected RepositoryInterface $repository,
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
                $user->can($permission)
            ) {
                $result[] = $definition;
            }
        }

        return $result;
    }

    protected function viewPermission(
        MenuDefinition $definition
    ): ?string {
        return $definition->permissionKey()
            . '.view';
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
}
