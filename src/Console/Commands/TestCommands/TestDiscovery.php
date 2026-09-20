<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\TestCommands;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class TestDiscovery
{
    public function __construct(
        protected Filesystem $filesystem
    ) {}

    public function features(
        ?string $path = null,
        ?string $feature = null
    ): array {
        $basePath = $path
            ? $this->absolutePath($path)
            : $this->packageRoot() . '/src/Features';

        if (! $this->filesystem->isDirectory($basePath)) {
            throw new RuntimeException(
                "AsasFlow feature path does not exist: {$basePath}"
            );
        }

        $features = [];

        foreach ($this->filesystem->directories($basePath) as $directory) {
            $featureName = basename($directory);

            if (
                $feature !== null
                && strtolower($featureName) !== strtolower($feature)
            ) {
                continue;
            }

            $testsPath = $directory . '/Tests';

            if (! $this->filesystem->isDirectory($testsPath)) {
                continue;
            }

            // Use Symfony Finder directly to guarantee we only get files
            // and to get a reliable relative path per file.
            $finder = new \Symfony\Component\Finder\Finder();
            $finder
                ->files()
                ->in($testsPath)
                ->name('*.php')
                ->sortByName();

            foreach ($finder as $file) {
                /** @var \Symfony\Component\Finder\SplFileInfo $file */
                $relative = $this->normalize(
                    $file->getRelativePathname()
                );

                // Skip Pest.php / TestCase.php if they ever live here.
                if (
                    $relative === 'Pest.php'
                    || $relative === 'TestCase.php'
                ) {
                    continue;
                }

                $features[$featureName][] = [
                    'source'   => $file->getPathname(),
                    'relative' => $relative,
                ];
            }
        }

        ksort($features);

        return $features;
    }

    protected function packageRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    protected function absolutePath(string $path): string
    {
        if (
            str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
        ) {
            return $path;
        }

        return base_path($path);
    }

    protected function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}