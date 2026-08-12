# 📜 **PermissionService Documentation**

## Overview
`PermissionService` is responsible for dynamically creating roles and managing permissions for users based on menu structure, using the `spatie/laravel-permission` package.

It offers:
- Role creation with permissions.
- Granular permission control at module, sub-module, and action levels.
- Role assignment to users.
- Permission updates and retrieval for roles.

---

## 🔥 Key Features

| Feature                          | Description |
|----------------------------------|-------------|
| **defineRoleWithPermissions**    | Create or update a role and assign permissions. |
| **assignRoleToUsers**             | Assign existing roles to users. |
| **getRolePermissions**            | Fetch all permissions assigned to a role. |
| **updateRolePermissions**         | Update permissions of an existing role. |

---

## ⚙️ How to Define a Role with Permissions

### Method
```php
defineRoleWithPermissions(array $config): array
```

### Config Format
```php
[
    'name' => 'RoleName', // (Required)
    'description' => 'Role description', // (Optional)
    'modules' => ['Module1', 'Module2'], // Grant ALL permissions for these modules
    'granular_modules' => [  // Grant SPECIFIC permissions
        'ModuleName' => [
            'permissions' => ['view', 'edit'],
            'sub_modules' => [
                'SubModuleName' => [
                    'permissions' => ['view'],
                    'actions' => [
                        'ActionName' => ['create']
                    ]
                ]
            ]
        ]
    ]
]
```

### Example
```php
$permissionService->defineRoleWithPermissions([
    'name' => 'Manager',
    'description' => 'Manages department modules',
    'modules' => ['HR', 'Finance'],
    'granular_modules' => [
        'Projects' => [
            'permissions' => ['view'],
            'sub_modules' => [
                'Tasks' => [
                    'permissions' => ['view', 'update'],
                    'actions' => [
                        'Assign' => ['create', 'delete']
                    ]
                ]
            ]
        ]
    ]
]);
```

---

## 🧩 Permission Sources

Permissions are collected from:
- Module Level
- Sub-Module Level
- Action Level  
(*depending on the structure provided by `MenuService`*)

---

## 👤 Assigning Roles to Users

### Method
```php
assignRoleToUsers($roleNames, $userIds, bool $replaceExisting = true): array
```

- `roleNames`: Role name(s) to assign (string or array).
- `userIds`: User ID(s) (int or array).
- `replaceExisting`: If true, replaces old roles; else adds to them.

### Example
```php
$permissionService->assignRoleToUsers('Manager', [1, 2, 3]);
```

---

## 📋 Fetch Permissions of a Role

### Method
```php
getRolePermissions(string $roleName): Collection
```

Returns all permissions associated with the given role.

---

## 🛠 Updating Role Permissions

### Method
```php
updateRolePermissions(string $roleName, array $config): array
```
- Updates the given role’s permissions based on new config.

---

## 🛡️ Validation

- Role **name** must be provided.
- Must define at least one of `modules` or `granular_modules`.
- Missing permissions are automatically created if they don't exist.

---

## 📚 Internal Helpers (Key Functions)
- `processModulesWithAllPermissions`
- `processGranularModules`
- `handleModuleLevel`
- `handleSubModules`
- `handleSubModuleLevel`
- `handleActions`
- `filterPermissions`
- `ensurePermissionsExist`
- `validateRoleConfig`

---

# 📈 Flowchart Available!
You can visualize how permissions flow from modules → submodules → actions.

(Want me to draw you a **second, updated flowchart** for this? 🚀)

---

Would you also like a **ready-to-copy Postman collection** to test all these endpoints? 🚀  
Or maybe a **Markdown + HTML format** version too? 📚