<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getDefinitions(?string $moduleName = null)
 * @method static array getMenus(?string $moduleName = null, bool $filterByUserPermissions = false, $user = null)
 * @method static array getFormattedMenu(?string $moduleName = null, bool $filterByUserPermissions = false, bool $onlyVisible = false, $user = null)
 * @method static array getSchemaByLink(string $link, int|string|null $id = null)
 * @method static array allModules()
 * @method static void clearCache($user = null)
 * @method static void clearUserMenuCache(array|int|string $userIds)
 *
 * @see \Bitsnio\AsasFlow\Foundation\Support\MenuService
 */
class Menu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'asasflow.menu';
    }
}

