<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array modules()
 * @method static array definitions(string $module)
 * @method static array defaults(string $module)
 * @method static array get(string $module)
 * @method static \Bitsnio\AsasFlow\Core\Settings\ModuleSettings update(string $module, array $data)
 * @method static \Bitsnio\AsasFlow\Core\Settings\ModuleSettings set(string $module, string $key, mixed $value)
 */
class ModuleSettings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'module-settings';
    }
}