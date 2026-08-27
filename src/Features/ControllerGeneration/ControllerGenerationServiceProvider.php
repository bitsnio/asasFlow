<?php

namespace AsasFlow\Features\ControllerGeneration;

use Illuminate\Support\ServiceProvider;
use AsasFlow\Features\ControllerGeneration\Commands\GenerateControllersCommand;
use AsasFlow\Features\ControllerGeneration\Contracts\ControllerGeneratorInterface;
use AsasFlow\Features\ControllerGeneration\Contracts\RouteGeneratorInterface;
use AsasFlow\Features\ControllerGeneration\Contracts\MenuParserInterface;
use AsasFlow\Features\ControllerGeneration\Generators\Controller\ControllerWithAttributesGenerator;
use AsasFlow\Features\ControllerGeneration\Generators\Route\RouteWithAttributesGenerator;
use AsasFlow\Features\ControllerGeneration\Parsers\MenuStructureParser;
use AsasFlow\Features\ControllerGeneration\Services\ControllerGenerationService;
use AsasFlow\Features\ControllerGeneration\Services\FileHandlerService;
use AsasFlow\Features\ControllerGeneration\Services\RouteNameTruncator;

class ControllerGenerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/controller-generation.php',
            'controller-generation'
        );

        $this->registerBindings();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\GenerateControllersCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__ . '/config/controller-generation.php' => config_path('controller-generation.php'),
        ]);
    }

    protected function registerBindings(): void
    {
        // Core services
        $this->app->singleton(ControllerGeneratorInterface::class, 
            ControllerWithAttributesGenerator::class
        );
        
        $this->app->singleton(RouteGeneratorInterface::class, 
            RouteWithAttributesGenerator::class
        );
        
        $this->app->singleton(MenuParserInterface::class, 
            MenuStructureParser::class
        );
        
        $this->app->singleton(FileHandlerService::class);
        $this->app->singleton(RouteNameTruncator::class);
        
        // Main service
        $this->app->singleton(ControllerGenerationService::class, 
            function ($app) {
                return new ControllerGenerationService(
                    $app->make(ControllerGeneratorInterface::class),
                    $app->make(RouteGeneratorInterface::class),
                    $app->make(MenuParserInterface::class),
                    $app->make(FileHandlerService::class),
                    $app->make(RouteNameTruncator::class)
                );
            }
        );
    }
}
