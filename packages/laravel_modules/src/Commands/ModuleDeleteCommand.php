<?php

namespace Bitsnio\Modules\Commands;

use Illuminate\Support\Facades\File;

class ModuleDeleteCommand extends BaseCommand
{
    protected $name = 'module:delete';
    protected $description = 'Delete a module from the application';

    public function executeAction($name): void
    {
        $module = $this->getModuleModel($name);
        $this->components->task("Deleting <fg=cyan;options=bold>{$module->getName()}</> Module", function () use ($module) {
            $module->delete();
        });
        if(file_exists('Schema/'.$name)) File::deleteDirectory('Schema/'.$name);
    }

    public function getInfo(): string|null
    {
        return 'deleting module ...';
    }

}
