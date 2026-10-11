<?php

namespace Bitsnio\AsasFlow\Console;

use Bitsnio\AsasFlow\Console\Commands\Module\MakeModuleCommand;
use Illuminate\Support\Collection;
use Bitsnio\AsasFlow\Console\Commands\Install;
use Bitsnio\AsasFlow\Console\Commands\ControllerCommands\GenerateControllersCommand;
use Bitsnio\AsasFlow\console\Commands\ControllerCommands\GenerateFromSchemaCommand;

use Bitsnio\AsasFlow\Console\Commands\UpdateDocs;
use Bitsnio\AsasFlow\Console\Commands\InstallTests;

class ConsoleServiceProvider 
{
     public static function commands(): Collection
    {
        return collect([
            // Core
            Install::class,
            InstallTests::class,
            UpdateDocs::class,
            GenerateControllersCommand::class,
            GenerateFromSchemaCommand::class,
            // Module overrides
            MakeModuleCommand::class,
        ]);
    }

}
