<?php

namespace Bitsnio\AsasFlow\Generators\Cache;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class CacheObserverGenerator
{
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Generate cache observer for a module.
     */
    public function generate(string $module, string $model, array $config = []): string
    {
        $moduleSlug = Str::snake($module);
        $modelClass = Str::studly($model);
        $observerClass = "{$modelClass}CacheObserver";
        
        $namespace = "Modules\\{$module}\\Observers";
        $modelNamespace = "Modules\\{$module}\\Models";
        
        $path = base_path("Modules/{$module}/Observers/{$observerClass}.php");
        
        // Build relationship tags
        $relationshipTags = $this->buildRelationshipTags($config['relationships'] ?? [], $moduleSlug);
        
        $stub = $this->getStub();
        
        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ model_namespace }}',
                '{{ class }}',
                '{{ model }}',
                '{{ module_slug }}',
                '{{ relationship_tags }}',
            ],
            [
                $namespace,
                $modelNamespace,
                $observerClass,
                $modelClass,
                $moduleSlug,
                $relationshipTags,
            ],
            $stub
        );
        
        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        
        return $path;
    }

    /**
     * Build relationship invalidation tags.
     */
    protected function buildRelationshipTags(array $relationships, string $moduleSlug): string
    {
        if (empty($relationships)) {
            return '// No relationships configured';
        }

        $lines = [];
        foreach ($relationships as $relation => $config) {
            $foreignKey = $config['foreign_key'] ?? Str::snake($relation) . '_id';
            $relatedModule = $config['module'] ?? Str::plural($relation);
            
            $lines[] = "        if (\\$model->isDirty('{$foreignKey}')) {";
            $lines[] = "            \\$relationshipTags[] = \"module:{$relatedModule}:{\\$model->getOriginal('{$foreignKey}')}:{$moduleSlug}\";";
            $lines[] = "            \\$relationshipTags[] = \"module:{$relatedModule}:{\\$model->{$foreignKey}}:{$moduleSlug}\";";
            $lines[] = "        }";
        }

        return implode("\n", $lines);
    }

    /**
     * Get observer stub.
     */
    protected function getStub(): string
    {
        $stubPath = __DIR__ . '/../../Features/Cache/Console/Commands/Stubs/cache-observer.stub';
        
        if ($this->files->exists($stubPath)) {
            return $this->files->get($stubPath);
        }

        // Fallback stub
        return $this->getFallbackStub();
    }

    /**
     * Get fallback stub content.
     */
    protected function getFallbackStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

use {{ model_namespace }}\{{ model }};
use Bitsnio\AsasFlow\Features\Cache\Services\CacheObserverManager;
use Bitsnio\AsasFlow\Features\Cache\Services\ModuleCacheManager;

class {{ class }}
{
    protected ModuleCacheManager $cacheManager;
    protected CacheObserverManager $observerManager;

    public function __construct(
        ModuleCacheManager $cacheManager,
        CacheObserverManager $observerManager
    ) {
        $this->cacheManager = $cacheManager;
        $this->observerManager = $observerManager;
    }

    public function created({{ model }} $model): void
    {
        $this->observerManager->handleCreated($model, [
            'module:{{ module_slug }}',
            'module:{{ module_slug }}:list',
            'module:{{ module_slug }}:count',
        ]);
    }

    public function updated({{ model }} $model): void
    {
        $this->observerManager->handleUpdated($model, [
            'module:{{ module_slug }}',
            "module:{{ module_slug }}:{$model->id}",
            'module:{{ module_slug }}:list',
        ]);
    }

    public function deleted({{ model }} $model): void
    {
        $this->observerManager->handleDeleted($model, [
            'module:{{ module_slug }}',
            "module:{{ module_slug }}:{$model->id}",
            'module:{{ module_slug }}:list',
            'module:{{ module_slug }}:count',
        ]);
    }
}
STUB;
    }

    /**
     * Ensure directory exists.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }
}
