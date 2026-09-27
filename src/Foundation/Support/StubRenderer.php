<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use RuntimeException;

class StubRenderer
{
    public function __construct(
        protected FileHandler $files,
    ) {}

    protected function basePath(): string
    {
        return dirname(__DIR__, 2)
            . '/Generators/Stubs';
    }

    public function render(
        string $stub,
        array $replacements = []
    ): string {
        foreach ($replacements as $key => $value) {
            $stub = str_replace(
                '{{ ' . $key . ' }}',
                (string) $value,
                $stub
            );

            $stub = str_replace(
                '{{' . $key . '}}',
                (string) $value,
                $stub
            );
        }

        return $stub;
    }

    public function renderFile(
        string $stubName,
        array $replacements = []
    ): string {
        $path = rtrim($this->basePath(), '/\\')
            . '/'
            . ltrim($stubName, '/\\');

        if (!$this->files->exists($path)) {
            throw new RuntimeException(
                "Stub not found: {$path}"
            );
        }

        return $this->render(
            $this->files->read($path),
            $replacements
        );
    }
}