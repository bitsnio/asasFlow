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

    public function generate(string $module, string $model, array $config = []): string
    {
        $modelClass = Str::studly($model);
        $observerClass = "{$modelClass}CacheObserver";
        
        $namespace = "Modules\\{$module}\\Observers";
        $modelNamespace = "Modules\\{$module}\\Models";
        
        $path = base_path("Modules/{$module}/Observers/{$observerClass}.php");
        
        $tags = $this->buildTags($config['tags'] ?? [], $module);
        $relations = $this->buildRelations($config['relations'] ?? []);
        
        $stub = $this->getStub();
        
        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ model_namespace }}',
                '{{ class }}',
                '{{ model }}',
                '{{ tags }}',
                '{{ relations }}',
            ],
            [
                $namespace,
                $modelNamespace,
                $observerClass,
                $modelClass,
                $tags,
                $relations,
            ],
            $stub
        );
        
        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        
        return $path;
    }

    protected function buildTags(array $tags, string $module): string
    {
        $defaults = ["'{$module}-service-{$module}'"];
        
        foreach ($tags as $tag) {
            $defaults[] = "'{$tag}'";
        }
        
        return implode(",\n        ", $defaults);
    }

    protected function buildRelations(array $relations): string
    {
        if (empty($relations)) {
            return '[]';
        }

        $items = [];
        foreach ($relations as $relation => $config) {
            $items[] = "'{$relation}' => ['on_update' => true, 'on_delete' => " . ($config['on_delete'] ?? 'false') . "]";
        }

        return "[\n            " . implode(",\n            ", $items) . "\n        ]";
    }

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

use {{ model_namespace }}\{{ model }};
use Bitsnio\AsasFlow\Features\Cache\Observers\ModelCacheObserver;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheInvalidator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;

class {{ class }} extends ModelCacheObserver
{
    protected array $cacheTags = [
        {{ tags }}
    ];

    protected array $cacheInvalidationRelations = {{ relations }};

    public function __construct(
        CacheInvalidator $invalidator,
        CacheKeyGenerator $keyGenerator,
    ) {
        parent::__construct($invalidator, $keyGenerator);
    }
}
STUB;
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }
}
