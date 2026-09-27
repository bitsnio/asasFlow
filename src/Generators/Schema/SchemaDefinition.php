<?php

namespace Bitsnio\AsasFlow\Generators\Schema;

use InvalidArgumentException;

class SchemaDefinition
{
    public function __construct(
        protected array $schema
    ) {}

    public static function fromFile(
        string $path
    ): self {
        if (!is_file($path)) {
            throw new InvalidArgumentException(
                "Schema file not found: {$path}"
            );
        }

        $data = json_decode(
            file_get_contents($path),
            true
        );

        if (!is_array($data)) {
            throw new InvalidArgumentException(
                "Invalid JSON schema: {$path}"
            );
        }

        return new self($data);
    }

    public function title(): string
    {
        return $this->schema['title']
            ?? '';
    }

    public function properties(): array
    {
        return is_array(
            $this->schema['properties'] ?? null
        )
            ? $this->schema['properties']
            : [];
    }

    public function required(): array
    {
        return is_array(
            $this->schema['required'] ?? null
        )
            ? $this->schema['required']
            : [];
    }

    public function property(
        string $name
    ): array {
        return is_array(
            $this->properties()[$name] ?? null
        )
            ? $this->properties()[$name]
            : [];
    }

    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->properties() as $name => $property) {
            $rules[$name] =
                $this->rulesFor(
                    $name,
                    $property
                );
        }

        return $rules;
    }

    public function casts(): array
    {
        $casts = [];

        foreach ($this->properties() as $name => $property) {
            $type = $property['type'] ?? 'string';

            $cast = match ($type) {
                'integer' => 'integer',
                'number' => 'decimal:4',
                'boolean' => 'boolean',
                'array',
                'object' => 'array',
                default => null,
            };

            if ($cast !== null) {
                $casts[$name] = $cast;
            }
        }

        return $casts;
    }

    public function migrationColumns(): array
    {
        $columns = [];

        foreach ($this->properties() as $name => $property) {
            $columns[$name] =
                $this->migrationColumn(
                    $name,
                    $property
                );
        }

        return $columns;
    }

    protected function rulesFor(
        string $name,
        array $property
    ): array {
        $rules = [];

        if (
            in_array(
                $name,
                $this->required(),
                true
            )
        ) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $type = $property['type'] ?? 'string';

        switch ($type) {
            case 'integer':
                $rules[] = 'integer';
                break;

            case 'number':
                $rules[] = 'numeric';
                break;

            case 'boolean':
                $rules[] = 'boolean';
                break;

            case 'array':
                $rules[] = 'array';
                break;

            case 'object':
                $rules[] = 'array';
                break;

            case 'string':
            default:
                $rules[] = 'string';

                if (
                    isset($property['minLength'])
                ) {
                    $rules[] =
                        'min:'
                        . $property['minLength'];
                }

                if (
                    isset($property['maxLength'])
                ) {
                    $rules[] =
                        'max:'
                        . $property['maxLength'];
                }

                $format =
                    $property['format'] ?? null;

                if ($format === 'email') {
                    $rules[] = 'email';
                }

                if ($format === 'date') {
                    $rules[] = 'date';
                }

                if ($format === 'date-time') {
                    $rules[] = 'date';
                }

                if ($format === 'uri') {
                    $rules[] = 'url';
                }

                break;
        }

        if (
            isset($property['minimum'])
        ) {
            $rules[] =
                'min:'
                . $property['minimum'];
        }

        if (
            isset($property['maximum'])
        ) {
            $rules[] =
                'max:'
                . $property['maximum'];
        }

        if (
            isset($property['enum']) &&
            is_array($property['enum'])
        ) {
            $rules[] =
                'in:'
                . implode(
                    ',',
                    array_map(
                        'strval',
                        $property['enum']
                    )
                );
        }

        return $rules;
    }

    protected function migrationColumn(
        string $name,
        array $property
    ): array {
        $type = $property['type'] ?? 'string';

        return [
            'name' => $name,
            'type' => $type,
            'nullable' =>
            !in_array(
                $name,
                $this->required(),
                true
            ),
            'default' =>
            $property['default']
                ?? null,
            'enum' =>
            $property['enum']
                ?? null,
        ];
    }
}
