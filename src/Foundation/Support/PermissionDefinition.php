<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

class PermissionDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $guard = 'api',
        public readonly ?string $module = null,
        public readonly ?string $menuPath = null,
        public readonly ?string $method = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'guard_name' => $this->guard,
            'module' => $this->module,
            'menu_path' => $this->menuPath,
            'method' => $this->method,
        ];
    }
}
