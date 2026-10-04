<?php

namespace Tests;

use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use PHPUnit\Framework\TestCase;

class SchemaDefinitionTest extends TestCase
{
    public function test_it_reads_the_asasflow_schema_wrapper(): void
    {
        $schema = new SchemaDefinition([
            'schema' => [
                'title' => 'Organization',
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['active', 'inactive'],
                    ],
                ],
                'required' => ['name', 'status'],
            ],
            'model' => [
                'name' => '',
                'status' => 'active',
            ],
        ]);

        $this->assertSame('Organization', $schema->title());
        $this->assertArrayHasKey('name', $schema->properties());
        $this->assertSame(['name', 'status'], $schema->required());
        $this->assertSame('active', $schema->modelDefaults()['status']);
    }

    public function test_it_generates_nested_validation_rules(): void
    {
        $schema = new SchemaDefinition([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'address' => [
                        'type' => 'object',
                        'properties' => [
                            'city' => ['type' => 'string'],
                        ],
                        'required' => ['city'],
                    ],
                    'contacts' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'email' => [
                                    'type' => 'string',
                                    'format' => 'email',
                                ],
                            ],
                            'required' => ['email'],
                        ],
                    ],
                ],
            ],
        ]);

        $rules = $schema->validationRules();

        $this->assertArrayHasKey('address.city', $rules);
        $this->assertArrayHasKey('contacts.*', $rules);
        $this->assertArrayHasKey('contacts.*.email', $rules);
        $this->assertContains('email', $rules['contacts.*.email']);
    }

    public function test_it_maps_json_types_to_array_casts(): void
    {
        $schema = new SchemaDefinition([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'metadata' => ['type' => 'object'],
                    'items' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'enabled' => ['type' => 'boolean'],
                ],
            ],
        ]);

        $casts = $schema->casts();

        $this->assertSame('array', $casts['metadata']);
        $this->assertSame('array', $casts['items']);
        $this->assertSame('boolean', $casts['enabled']);
    }

    public function test_it_accepts_nullable_union_types(): void
    {
        $schema = new SchemaDefinition([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => ['string', 'null']],
                ],
            ],
        ]);

        $column = $schema->migrationColumns()['email'];

        $this->assertTrue($column['nullable']);
    }
}