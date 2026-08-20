<?php

namespace Bitsnio\AsasFlow\Console\Commands\ModuleCommands;

use Bitsnio\Modules\Commands\Make\ModuleMakeCommand as BaseModuleMakeCommand;
use Bitsnio\Modules\Contracts\ActivatorInterface;
use Bitsnio\Modules\Generators\ModuleGenerator;
use Bitsnio\AsasFlow\Generators\MenuGenerator;
use Bitsnio\AsasFlow\Generators\ModuleSettingsGenerator;
use Bitsnio\AsasFlow\Console\Commands\Traits\HandlesComposerDump;

class ModuleMakeCommand extends BaseModuleMakeCommand
{
    use HandlesComposerDump;

    protected const FIXED_AUTHOR_VENDOR = 'yourcompany';
    protected const FIXED_MODULE_TYPE = 'api';
    protected const DEFAULT_AUTHOR_NAME = 'Your Company Name';
    protected const DEFAULT_AUTHOR_EMAIL = 'dev@yourcompany.com';

    public function handle(): int
    {
        $names = $this->argument('name');
        $success = true;

        $authorName = $this->option('author-name') ?: self::DEFAULT_AUTHOR_NAME;
        $authorEmail = $this->option('author-email') ?: self::DEFAULT_AUTHOR_EMAIL;

        $this->components->info(sprintf(
            'Creating API module(s) with Author: %s <%s>, Vendor: %s',
            $authorName,
            $authorEmail,
            self::FIXED_AUTHOR_VENDOR
        ));

        foreach ($names as $name) {
            $code = with(new ModuleGenerator($name))
                ->setFilesystem($this->laravel['files'])
                ->setModule($this->laravel['modules'])
                ->setConfig($this->laravel['config'])
                ->setActivator($this->laravel[ActivatorInterface::class])
                ->setConsole($this)
                ->setComponent($this->components)
                ->setForce($this->option('force'))
                ->setType(self::FIXED_MODULE_TYPE)
                ->setInertia(false)
                ->setActive(! $this->option('disabled'))
                ->setVendor(self::FIXED_AUTHOR_VENDOR)
                ->setAuthor($authorName, $authorEmail)
                ->generate();

            if ($code === E_ERROR) {
                $success = false;
                $this->components->error("Module scaffolding failed for [{$name}].");
                break;
            }

            $this->runPostGenerators($name);
        }

        if ($success) {
            $this->runComposerDumpAutoload();
        }

        return $success ? 0 : E_ERROR;
    }

    protected function runPostGenerators(string $moduleName): void
    {
        try {
            $this->components->info("Running post-generators for [{$moduleName}]...");

            (new MenuGenerator($this->laravel['modules'], $moduleName, $this->components))->generate();
            (new ModuleSettingsGenerator($this->laravel['modules'], $moduleName, $this->components))->generate();

            $this->components->info("✓ Post-generators completed for [{$moduleName}].");
        } catch (\Exception $e) {
            $this->components->error("Post-generators failed for [{$moduleName}]: " . $e->getMessage());
            throw $e;
        }
    }
}