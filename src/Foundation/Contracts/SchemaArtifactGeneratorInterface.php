<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Contracts;

use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;

interface SchemaArtifactGeneratorInterface
{
    /**
     * The CLI key for this artifact (e.g., 'models', 'migrations', 'requests', 'resources', 'controllers').
     */
    public function key(): string;

    /**
     * Generate or update the artifact from the populated JSON schema.
     */
    public function syncFromSchema(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema,
        array $options = [],
    ): array;
}