# Installing AsasFlow

This guide covers installing the AsasFlow package into an existing Laravel application. Use the installation method that matches how the package is distributed by your team.

## Prerequisites

* A Laravel application compatible with the package's `composer.json` requirements.
* Composer.
* A supported database configured in `.env`.
* The Laravel Modules fork/version required by your project.
* Spatie Laravel Permission if it is not already installed as a package dependency.

Check the package's `composer.json` for the authoritative PHP and Laravel version constraints.

## Option A: Install from a local path repository

Use this when developing AsasFlow alongside the host application.

Add a path repository to the host application's `composer.json`. Adjust the relative path to the actual package directory:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../AsasFlow",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Then require the actual Composer package name declared in AsasFlow's `composer.json`:

```bash
composer require vendor/package-name:@dev
```

Replace `vendor/package-name` with the package's real Composer name. Do not use the placeholder literally.

If Composer reports a package version constraint issue, check the package's `version`/branch aliases and the host project's stability settings.

## Option B: Install from a VCS repository

If the package is hosted in Git, add its repository to the host application's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://your-git-host/your-team/asasflow.git"
    }
  ]
}
```

Then require the real Composer package name and a valid branch, tag, or version:

```bash
composer require vendor/package-name:dev-main
```

Replace the URL, package name, and branch with your project's actual values.

## Publish package configuration and stubs

If the package registers publishable configuration or stubs, inspect its service provider and run the tag actually registered by the package. For example, first discover available tags:

```bash
php artisan vendor:publish
```

Do not assume a publish tag or config filename; use the options displayed by the installed package.

## Verify installation

```bash
composer show | grep -i asasflow
php artisan list
```

Confirm that the expected AsasFlow commands appear in Artisan's command list. The command names in this documentation should be checked against the installed version.

## Module support

Create modules using the Laravel Modules command supplied by your installed fork. In a typical installation:

```bash
php artisan module:make Admin
```

Check `php artisan list` if your fork uses a different command signature.

## After installation

1. Create or select a module.
2. Define its menu in `Modules/{ModuleName}/config/menu.php`.
3. Run the menu/controller generation command supported by your installed package.
4. Review generated files before committing them.
5. Define the module's JSON Schemas.
6. Test generated routes, validation, and database migrations in a development database.

## Important

Model and migration generation from schema is currently considered experimental in this documentation until it has been tested in the target project. Do not run unverified generated migrations against production data.
