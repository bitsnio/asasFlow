<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Settings;

use Spatie\LaravelSettings\Settings;

abstract class ModuleSettings extends Settings
{
    /**
     * Actual persisted values for this module.
     *
     * The structure/metadata of these values is defined in
     * the module's config/settings.php.
     */
    public array $values = [];

    /**
     * Module identifier.
     */
    abstract public static function module(): string;

    /**
     * Spatie settings group.
     */
    public static function group(): string
    {
        return static::module();
    }
}