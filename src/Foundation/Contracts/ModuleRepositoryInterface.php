<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Contracts;

use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;

interface ModuleRepositoryInterface
{
    public function find(string $name): ?ModuleDefinition;

    /** @return array<string, ModuleDefinition> */
    public function all(): array;
}
