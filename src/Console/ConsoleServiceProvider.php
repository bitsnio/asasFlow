<?php

namespace Bitsnio\AsasFlow\Console;

use Illuminate\Support\Collection;
use Bitsnio\AsasFlow\Console\Commands\Install;
use Bitsnio\AsasFlow\Console\Commands\ModuleCommands\ModuleMakeCommand;

class ConsoleServiceProvider
{
    public static function commands(): Collection
    {
        return collect([
            // Core
            Install::class,

            // Module overrides
            ModuleMakeCommand::class,
        ]);
    }
}