# Menu Format and Code Generators

The module's `config/menu.php` is the source of truth for menu hierarchy, resource routes, custom resource actions, and permission descriptions.

## Basic structure

The menu file uses a `module` root object. Each node has a `name`, `title`, and optional `type`.

- A node without `type` is a group by default.
- `type => resource` creates RESTful CRUD routes and one controller for that resource.
- A resource may contain child nodes and a resource-level `actions` map.
- Resource custom actions add methods and routes to the same resource controller.
- Do not define one separate action controller per resource custom action.
- `model => true` indicates that the resource is associated with a model/schema according to the generator implementation.

## Example `Modules/Admin/config/menu.php`

```php
<?php

return [
    'module' => [
        'name' => 'Admin',
        'title' => 'Administration',
        'children' => [
            [
                'name' => 'organization',
                'title' => 'Organization',
                // No type means this node is a group.
                'children' => [
                    [
                        'name' => 'companies',
                        'title' => 'Companies',
                        'type' => 'resource',
                        'model' => true,
                        'permissions' => [
                            'view' => 'View companies',
                            'create' => 'Create companies',
                            'update' => 'Update companies',
                            'delete' => 'Delete companies',
                        ],
                        'actions' => [
                            'approve' => [
                                'method' => 'POST',
                                'permissionLabel' => 'Approve companies',
                            ],
                            'suspend' => [
                                'method' => 'POST',
                                'permissionLabel' => 'Suspend companies',
                            ],
                            'export' => [
                                'method' => 'GET',
                                'permissionLabel' => 'Export companies',
                            ],
                        ],
                    ],
                    [
                        'name' => 'sites',
                        'title' => 'Sites',
                        'type' => 'resource',
                        'model' => true,
                        'permissions' => [
                            'view' => 'View sites',
                            'create' => 'Create sites',
                            'update' => 'Update sites',
                            'delete' => 'Delete sites',
                        ],
                        'actions' => [
                            'activate' => [
                                'method' => 'POST',
                                'permissionLabel' => 'Activate sites',
                            ],
                            'deactivate' => [
                                'method' => 'POST',
                                'permissionLabel' => 'Deactivate sites',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
```

## Node properties

| Property | Purpose |
|---|---|
| `name` | Stable machine name used to derive route segments, class names, and permission paths. |
| `title` | Human-readable display label. |
| `type` | Omit for a group; use `resource` for CRUD resources. Legacy standalone `action` nodes may be supported by some versions. |
| `model` | Enables model/schema association where supported by the generator. |
| `middleware` | Middleware configured for the node, where supported. |
| `permissions` | Human-readable labels for the resource CRUD permissions. |
| `actions` | Resource-level custom action definitions. |
| `children` | Nested groups or resources. |

Only use properties implemented by the installed package version. Unknown properties may be ignored or rejected depending on the version.

## Resource custom actions

An action key is the method name. Its route segment is derived from the key, and its HTTP method is configured with `method`.

```php
'actions' => [
    'resetPassword' => [
        'method' => 'POST',
        'permissionLabel' => 'Reset user password',
    ],
],
```

This is intended to produce a controller method named `resetPassword()` and a URL segment such as `reset-password`. No separate action controller or explicit `path` field is required.

Supported methods should be verified in the installed implementation; the expected set is `GET`, `POST`, `PUT`, `PATCH`, and `DELETE`.

## Permissions

Resource CRUD permission names are derived from the menu path. For example:

```text
organization.companies.view
organization.companies.create
organization.companies.update
organization.companies.delete
```

Custom actions use the resource permission path, the action key, and `.execute`:

```text
organization.companies.approve.execute
organization.companies.suspend.execute
organization.companies.export.execute
```

The `permissionLabel` is the human-readable description saved for the custom action permission. Keep `permissions` and `actions` separate: CRUD labels belong in `permissions`, while custom operation labels belong in each action's `permissionLabel`.

AsasFlow uses Spatie Laravel Permission. Do not change the permission response structure or guard name when modifying the generators unless that is an intentional API change.

## Generate controllers, requests, resources, and routes

The package's menu generation command has changed during development. Discover the exact command in the installed version:

```bash
php artisan list | grep -i asasflow
php artisan list | grep -i menu
```

Run the command documented by that version with the target module name. Review the generated files afterward.

The generation workflow currently covers:

- Resource controllers.
- Form requests.
- API resources.
- `Routes/api.php` (or the route path configured by the installed fork).
- CRUD route registration using `Route::apiResource()` where applicable.
- Custom action routes that point to methods on the resource controller.

## Generated-file ownership

Follow the generator's current ownership rules:

- Controllers, requests, and API resources may be created if they do not exist.
- Schema synchronization should update only the explicitly marked generated blocks.
- Do not assume a generator can safely replace developer-authored code outside those blocks.
- Schema files are developer-owned and should not be overwritten by generation, including force options, unless the implementation explicitly documents otherwise.

## After generation

1. Inspect namespaces and imports.
2. Confirm there are no duplicate controller imports.
3. Run `php artisan route:list` and check the expected URI, method, and controller action.
4. Test validation and authorization.
5. Add tests for every custom action.