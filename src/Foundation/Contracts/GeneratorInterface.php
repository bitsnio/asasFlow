<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Contracts;

interface GeneratorInterface
{
    /**
     * The CLI key for this artifact (e.g., 'controllers', 'requests', 'resources', 'schemas', 'routes').
     */
    public function key(): string;

    /**
     * Generate artifacts across the menu definition tree.
     */
    public function generate($module, array $structure, array $options = []): array;
}