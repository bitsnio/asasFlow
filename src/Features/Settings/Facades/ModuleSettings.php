<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(
 *     string $module,
 *     string $key,
 *     mixed $default = null,
 *     ?int $companyId = null,
 *     ?int $siteId = null
 * )
 *
 * @method static array all(
 *     string $module,
 *     ?int $companyId = null,
 *     ?int $siteId = null
 * )
 *
 * @method static array update(
 *     string $module,
 *     array $values,
 *     ?int $companyId = null,
 *     ?int $siteId = null
 * )
 *
 * @method static array schema(
 *     string $module,
 *     ?int $companyId = null,
 *     ?int $siteId = null
 * )
 */
class ModuleSettings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'module-settings';
    }
}