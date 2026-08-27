<?php

namespace Bitsnio\AsasFlow\Console;

use Illuminate\Support\ServiceProvider;
use Bitsnio\AsasFlow\Console\Commands\ModuleCommands\ModuleMakeCommand;
class ConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Module Commands
                ModuleMakeCommand::class,
                
                // Controller Commands
                \Bitsnio\AsasFlow\Console\Commands\ControllerCommands\GenerateControllersCommand::class,

                // Other Commands
                \Bitsnio\AsasFlow\Console\Commands\Install::class,
                \Bitsnio\AsasFlow\Console\Commands\UpdateDocs::class,
            ]);
        }
    }
}
