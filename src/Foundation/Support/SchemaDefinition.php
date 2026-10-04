<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use InvalidArgumentException;
use RuntimeException;

class SchemaDefinition
{
    protected array $document;
    protected array $schema;
    protected array $modelDefaults = [];

    public function __construct(array $document)
    {
        $this->document = $document;

        // Support both a raw JSON Schema and the AsasFlow wrapper.
        $this->schema = is_array($document['schema'] ?? null)
            ? $document['schema']
            : $document;

        $this->modelDefaults = is_array($document['model'] ?? null)
            ? $document['model']
            : [];

        $this->schema = $this->expandSchema($this->schema);
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(
                "Schema file not found: {$path}"
            );
        }

        $document = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($document)) {
            throw new \InvalidArgumentException(
                "Invalid schema document: {$path}"
            );
        }

        return new self($document);
    }

    public function schema(): array
    {
        return $this->schema;
    }

    public function originalDocument(): array
    {
        return $this->document;
    }

    public function modelDefaults(): array
    {
        return $this->modelDefaults;
    }

    public function title(): string
    {
        return (string) ($this->schema['title'] ?? '');
    }

    public function properties(): array
    {
        return is_array($this->schema['properties'] ?? null)
            ? $this->schema['properties']
            : [];
    }

    public function required(): array
    {
        return is_array($this->schema['required'] ?? null)
            ? $this->schema['required']
            : [];
    }

    public function property(string $name): array
    {
        $property = $this->properties()[$name] ?? null;

        return is_array($property) ? $property : [];
    }

    /**
     * Return Laravel rules keyed by dot-notation paths.
     *
     * Example:
     * [
     *     'name' => ['required', 'string'],
     *     'address' => ['nullable', 'array'],
     *     'address.city' => ['required_with:address', 'string'],
     *     'contacts.*.email' => ['required', 'email'],
     * ]
     */
    public function validationRules(): array
    {
        $rules = [];

        $this->buildObjectRules(
            $this->schema,
            '',
            $rules,
            []
        );

        return $rules;
    }

    /**
     * Eloquent casts apply to top-level database columns.
     */
    public function casts(): array
    {
        $casts = [];

        foreach ($this->properties() as $name => $property) {
            $type = $this->primaryType($property);

            $cast = match ($type) {
                'integer' => 'integer',
                'number' => 'decimal:4',
                'boolean' => 'boolean',
                'array', 'object' => 'array',
                default => null,
            };

            if ($cast !== null) {
                $casts[$name] = $cast;
            }

            if (($property['format'] ?? null) === 'date-time') {
                $casts[$name] = 'datetime';
            } elseif (($property['format'] ?? null) === 'date') {
                $casts[$name] = 'date';
            }
        }

        return $casts;
    }

    /**
     * Return top-level columns only.
     *
     * Nested objects and arrays become JSON columns by default.
     */
    public function migrationColumns(): array
    {
        $columns = [];

        foreach ($this->properties() as $name => $property) {
            $columns[$name] = [
                'name' => $name,
                'type' => $this->primaryType($property),
                'nullable' => $this->isNullable($property)
                    || !in_array($name, $this->required(), true),
                'default' => $property['default'] ?? null,
                'enum' => $property['enum'] ?? null,
                'format' => $property['format'] ?? null,
            ];
        }

        return $columns;
    }

    protected function buildObjectRules(
        array $schema,
        string $path,
        array &$rules,
        array $ancestors
    ): void {
        $properties = $schema['properties'] ?? [];

        if (!is_array($properties)) {
            return;
        }

        $required = is_array($schema['required'] ?? null)
            ? $schema['required']
            : [];

        foreach ($properties as $name => $property) {
            if (!is_array($property)) {
                continue;
            }

            $field = $path === ''
                ? (string) $name
                : $path . '.' . $name;

            $isRequired = in_array((string) $name, $required, true);

            $fieldRules = $this->rulesForProperty(
                $property,
                $isRequired,
                $path,
                $ancestors
            );

            $rules[$field] = $fieldRules;

            $type = $this->primaryType($property);

            if ($type === 'object') {
                $this->buildObjectRules(
                    $property,
                    $field,
                    $rules,
                    [...$ancestors, $field]
                );
            }

            if ($type === 'array' && is_array($property['items'] ?? null)) {
                $items = $property['items'];
                $itemPath = $field . '.*';

                $rules[$itemPath] = $this->rulesForProperty(
                    $items,
                    true,
                    $field,
                    [...$ancestors, $field]
                );

                if ($this->primaryType($items) === 'object') {
                    $this->buildObjectRules(
                        $items,
                        $itemPath,
                        $rules,
                        [...$ancestors, $field, $itemPath]
                    );
                }
            }
        }
    }


    protected function rulesForProperty(
        string $path,
        array $property,
        bool $required = false,
        ?string $parentPath = null
    ): array {
        $rules = [];

        /*
        * Required / nullable handling.
        */
        if ($required) {
            if ($parentPath !== null && $parentPath !== '') {
                $rules[] = 'required_with:' . $parentPath;
            } else {
                $rules[] = 'required';
            }
        } else {
            $rules[] = 'nullable';
        }

        /*
        * Resolve the property's type.
        * Supports both "type": "string" and
        * "type": ["string", "null"].
        */
        $rawType = $property['type'] ?? 'string';
        $nullable = false;

        if (is_array($rawType)) {
            $nullable = in_array('null', $rawType, true);

            $types = array_values(array_filter(
                $rawType,
                static fn($type) => $type !== 'null'
            ));

            $type = $types[0] ?? 'string';
        } else {
            $type = $rawType;
        }

        if (
            $nullable
            && !$required
            && !in_array('nullable', $rules, true)
        ) {
            $rules[] = 'nullable';
        }

        /*
        * Type-specific Laravel validation rules.
        */
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

                if (isset($property['minItems'])) {
                    $rules[] = 'min:' . $property['minItems'];
                }

                if (isset($property['maxItems'])) {
                    $rules[] = 'max:' . $property['maxItems'];
                }

                break;

            case 'object':
                $rules[] = 'array';

                if (isset($property['minProperties'])) {
                    $rules[] = 'min:' . $property['minProperties'];
                }

                if (isset($property['maxProperties'])) {
                    $rules[] = 'max:' . $property['maxProperties'];
                }

                break;

            case 'string':
            default:
                $rules[] = 'string';

                if (isset($property['minLength'])) {
                    $rules[] = 'min:' . $property['minLength'];
                }

                if (isset($property['maxLength'])) {
                    $rules[] = 'max:' . $property['maxLength'];
                }

                $format = $property['format'] ?? null;

                switch ($format) {
                    case 'email':
                        $rules[] = 'email';
                        break;

                    case 'date':
                        $rules[] = 'date';
                        break;

                    case 'date-time':
                        $rules[] = 'date';
                        break;

                    case 'uri':
                        $rules[] = 'url';
                        break;

                    case 'uuid':
                        $rules[] = 'uuid';
                        break;
                }

                if (isset($property['pattern'])) {
                    $pattern = str_replace(
                        '/',
                        '\\/',
                        $property['pattern']
                    );

                    $rules[] = 'regex:/^' . $pattern . '$/u';
                }

                break;
        }

        /*
     * Numeric constraints apply only to integer and number.
     */
        if (in_array($type, ['integer', 'number'], true)) {
            if (isset($property['minimum'])) {
                $rules[] = 'min:' . $property['minimum'];
            }

            if (isset($property['maximum'])) {
                $rules[] = 'max:' . $property['maximum'];
            }

            if (
                isset($property['exclusiveMinimum'])
                && is_numeric($property['exclusiveMinimum'])
            ) {
                $rules[] = 'gt:' . $property['exclusiveMinimum'];
            }

            if (
                isset($property['exclusiveMaximum'])
                && is_numeric($property['exclusiveMaximum'])
            ) {
                $rules[] = 'lt:' . $property['exclusiveMaximum'];
            }
        }

        /*
     * Enum and constant constraints.
     */
        if (isset($property['enum']) && is_array($property['enum'])) {
            $rules[] = 'in:' . implode(
                ',',
                array_map('strval', $property['enum'])
            );
        }

        if (array_key_exists('const', $property)) {
            $rules[] = 'in:' . (string) $property['const'];
        }

        return array_values(array_unique($rules));
    }

    protected function primaryType(array $schema): string
    {
        $type = $schema['type'] ?? null;

        if (is_array($type)) {
            $types = array_values(array_filter(
                $type,
                static fn($item) => $item !== 'null'
            ));

            return (string) ($types[0] ?? 'string');
        }

        if (is_string($type)) {
            return $type === 'null' ? 'string' : $type;
        }

        if (isset($schema['properties'])) {
            return 'object';
        }

        if (isset($schema['items'])) {
            return 'array';
        }

        return 'string';
    }

    protected function isNullable(array $schema): bool
    {
        $type = $schema['type'] ?? null;

        return is_array($type) && in_array('null', $type, true)
            || ($schema['nullable'] ?? false) === true;
    }

    /**
     * Resolve local references and merge composition keywords for
     * structural generation. Conditional and branch-specific validation
     * is left to the optional standards-compliant validator.
     */
    protected function expandSchema(
        array $schema,
        array $refStack = [],
        int $depth = 0
    ): array {
        if ($depth > 40) {
            return $schema;
        }

        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $ref = $schema['$ref'];

            if (!in_array($ref, $refStack, true)) {
                $resolved = $this->resolveLocalReference($ref);

                if ($resolved !== null) {
                    $schema = array_replace_recursive(
                        $this->expandSchema(
                            $resolved,
                            [...$refStack, $ref],
                            $depth + 1
                        ),
                        array_diff_key($schema, ['$ref' => true])
                    );
                }
            }
        }

        foreach (['allOf', 'anyOf', 'oneOf'] as $composition) {
            if (!is_array($schema[$composition] ?? null)) {
                continue;
            }

            foreach ($schema[$composition] as $branch) {
                if (!is_array($branch)) {
                    continue;
                }

                $expanded = $this->expandSchema(
                    $branch,
                    $refStack,
                    $depth + 1
                );

                $schema = $this->mergeSchemaStructures(
                    $schema,
                    $expanded
                );
            }

            unset($schema[$composition]);
        }

        if (is_array($schema['properties'] ?? null)) {
            foreach ($schema['properties'] as $name => $property) {
                if (is_array($property)) {
                    $schema['properties'][$name] = $this->expandSchema(
                        $property,
                        $refStack,
                        $depth + 1
                    );
                }
            }
        }

        if (is_array($schema['items'] ?? null)) {
            $schema['items'] = $this->expandSchema(
                $schema['items'],
                $refStack,
                $depth + 1
            );
        }

        return $schema;
    }

    protected function mergeSchemaStructures(
        array $base,
        array $branch
    ): array {
        if (isset($branch['properties']) && is_array($branch['properties'])) {
            $base['properties'] = array_replace(
                $base['properties'] ?? [],
                $branch['properties']
            );
        }

        if (isset($branch['required']) && is_array($branch['required'])) {
            $base['required'] = array_values(array_unique([
                ...($base['required'] ?? []),
                ...$branch['required'],
            ]));
        }

        foreach (
            [
                'type',
                'items',
                'enum',
                'const',
                'format',
                'minimum',
                'maximum',
                'minLength',
                'maxLength',
            ] as $key
        ) {
            if (!array_key_exists($key, $base) && array_key_exists($key, $branch)) {
                $base[$key] = $branch[$key];
            }
        }

        return $base;
    }

    protected function resolveLocalReference(string $ref): ?array
    {
        if (!str_starts_with($ref, '#/')) {
            // External references are deliberately handled by Opis at validation time.
            return null;
        }

        $segments = explode('/', substr($ref, 2));

        $candidates = [
            $this->schema,
            $this->document,
        ];

        foreach ($candidates as $candidate) {
            $value = $candidate;

            foreach ($segments as $segment) {
                $segment = str_replace(
                    ['~1', '~0'],
                    ['/', '~'],
                    $segment
                );

                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    $value = null;
                    break;
                }

                $value = $value[$segment];
            }

            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }
}
