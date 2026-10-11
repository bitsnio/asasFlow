<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Module;

use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Foundation\Support\ModuleScaffolder;
use InvalidArgumentException;

final class ModuleGenerator
{
    public function __construct(
        private readonly ModuleScaffolder $scaffolder,
    ) {}

    /**
     * @return array<int, array{path: string, action: string}>
     */
    public function generate(
        ModuleDefinition $module,
        bool $force = false,
    ): array {
        if (! preg_match(
            '/^[A-Za-z][A-Za-z0-9_]*$/',
            $module->name,
        )) {
            throw new InvalidArgumentException(
                "Invalid module name [{$module->name}]."
            );
        }

        return $this->scaffolder->createFiles(
            $module,
            $force,
        );
    }
}