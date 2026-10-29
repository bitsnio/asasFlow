<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getDefinitions(?string $moduleName = null)
 * @method static void syncPermissions(?string $moduleName = null)
 * @method static array getAllPermissions(?string $moduleName = null, bool $labelValueFormat = false)
 * @method static ?string getRequiredPermission(string $route, string $method)
 * @method static \Illuminate\Support\Collection getRolePermissions(int|string $role, string $guardName = 'api')
 * @method static \Spatie\Permission\Models\Role updateRolePermissions(int|string $role, array $permissionNames, string $guardName = 'api')
 * @method static \Spatie\Permission\Models\Role assignPermissionsToRole(int|string $role, array $permissionNames, bool $sync = true, string $guardName = 'api')
 * @method static array addPermissionsToRole(int|string $role, array $permissionsToAdd, string $guardName = 'api')
 * @method static array revokePermissionsFromRole(int|string $role, array $permissionsToRemove, string $guardName = 'api')
 * @method static array createRoleWithModuleBasedPermissions(string $roleName, ?string $roleDescription, string|array $moduleNames, array $permissionNames = [], array $exceptPermissions = [], string $guardName = 'api')
 * @method static array assignRoleToUsers(array|string|int $roles, array|int|string $userIds, bool $replaceExisting = true, string $guardName = 'api')
 * @method static void clearCache()
 *
 * @see \Bitsnio\AsasFlow\Foundation\Support\PermissionService
 */
class Permission extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'asasflow.permission';
    }
}

