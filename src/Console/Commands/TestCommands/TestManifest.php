<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\TestCommands;

use Illuminate\Filesystem\Filesystem;

class TestManifest
{
    protected string $filename = '.asasflow-tests.json';

    public function __construct(
        protected Filesystem $filesystem
    ) {}

    public function path(): string
    {
        return base_path(
            'tests/Feature/AsasFlow/' . $this->filename
        );
    }

    public function load(): array
    {
        $path = $this->path();

        if (! $this->filesystem->exists($path)) {
            return [
                'version' => '1.0.0',
                'tests' => [],
            ];
        }

        $contents = $this->filesystem->get($path);

        $manifest = json_decode(
            $contents,
            true
        );

        if (! is_array($manifest)) {
            return [
                'version' => '1.0.0',
                'tests' => [],
            ];
        }

        $manifest['version'] ??= '1.0.0';
        $manifest['tests'] ??= [];

        return $manifest;
    }

    public function save(array $manifest): void
    {
        $path = $this->path();

        $this->filesystem->ensureDirectoryExists(
            dirname($path)
        );

        $this->filesystem->put(
            $path,
            json_encode(
                $manifest,
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
            ) . PHP_EOL
        );
    }

    public function hash(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }
        return hash_file('sha256', $path);
    }

    public function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
