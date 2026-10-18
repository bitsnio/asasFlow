<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Route;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;

class RouteGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected RouteFileBuilder $builder,
        protected RouteFileSynchronizer $synchronizer,
    ) {}

    public function key(): string
    {
        return 'routes';
    }

    public function generate($module, array $definitions, array $options = []): array
    {
        $routeFile = $this->files->getRoutesPath($module);
        $exists = $this->files->exists($routeFile);
        $force = (bool) ($options['force'] ?? false);

        if (!$exists || $force) {
            $content = $this->builder->build($module, $definitions);
            $this->files->writeFile($routeFile, $content, true);

            return [
                [
                    'path' => $routeFile,
                    'action' => $exists ? 'regenerated' : 'created',
                ],
            ];
        }

        $document = $this->files->read($routeFile);
        [$updatedDocument, $addedRoutes] = $this->synchronizer->sync($module, $document, $definitions);

        if (!empty($addedRoutes)) {
            $this->files->writeFile($routeFile, $updatedDocument, true);

            return [
                [
                    'path' => $routeFile,
                    'action' => 'updated (added: ' . implode(', ', $addedRoutes) . ')',
                ],
            ];
        }

        return [
            [
                'path' => $routeFile,
                'action' => 'skipped (up-to-date)',
            ],
        ];
    }
}