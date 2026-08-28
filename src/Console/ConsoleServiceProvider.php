<?php

namespace Bitsnio\AsasFlow\Console;

use Bitsnio\AsasFlow\Console\Commands\ModuleCommands\ModuleMakeCommand;
use Illuminate\Support\Collection;
use Bitsnio\AsasFlow\Console\Commands\Install;
use Bitsnio\AsasFlow\Console\Commands\ControllerCommands\GenerateControllersCommand;
use Bitsnio\AsasFlow\Console\Commands\UpdateDocs;

class ConsoleServiceProvider 
{
     public static function commands(): Collection
    {
        return collect([
            // Core
            Install::class,
            UpdateDocs::class,
            GenerateControllersCommand::class,
            // Module overrides
            ModuleMakeCommand::class,
        ]);
    }

}
