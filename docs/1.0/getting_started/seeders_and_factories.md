# Database Seeders & Factories

AsasFlow provides first-class commands to scaffold and execute database seeders and Eloquent factories isolated within your application modules.

---

## 🏗️ Architecture & Conventions

Each module owns its database seeders and factories under standard PSR-4 directories:

```
Modules/
└── Blog/
    └── database/
        ├── factories/
        │   └── PostFactory.php          # Namespace: Modules\Blog\Database\Factories
        └── seeders/
            ├── BlogDatabaseSeeder.php   # Master Seeder
            └── PostSeeder.php           # Namespace: Modules\Blog\Database\Seeders
```

### Autoloading
Ensure your module's `composer.json` registers the database namespaces:

```json
{
    "autoload": {
        "psr-4": {
            "Modules\\Blog\\": "app/",
            "Modules\\Blog\\Database\\Factories\\": "database/factories/",
            "Modules\\Blog\\Database\\Seeders\\": "database/seeders/"
        }
    }
}
```

---

## 🚀 Creating Seeders: `asasflow:make-seeder`

Use the `asasflow:make-seeder` command (alias: `asasflow:make-seed`) to generate a new seeder class in a module.

```bash
php artisan asasflow:make-seeder [Name] [Module] [options]
```

### Examples

#### 1. Standard Seeder
Creates `Modules/Blog/database/seeders/PostSeeder.php`:

```bash
php artisan asasflow:make-seeder Post Blog
```

Generated code:
```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Database\Seeders;

use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
```

#### 2. Master Database Seeder (`--master`)
Creates a module-level root seeder (e.g. `Modules/Blog/database/seeders/BlogDatabaseSeeder.php`):

```bash
php artisan asasflow:make-seeder Blog Blog --master
```

#### 3. Overwriting Existing Seeders (`--force`)
```bash
php artisan asasflow:make-seeder Post Blog --force
```

> **Interactive Selection:** If you omit the module name in an interactive shell, AsasFlow prompts you to select from available modules.

---

## 🏭 Creating Factories: `asasflow:make-factory`

Use `asasflow:make-factory` to scaffold an Eloquent model factory inside the target module.

```bash
php artisan asasflow:make-factory [Name] [Module] [options]
```

### Examples

#### 1. Auto-Derived Model
If `--model` is omitted, AsasFlow automatically derives the model from the factory name (e.g. `PostFactory` -> `Modules\Blog\Models\Post`):

```bash
php artisan asasflow:make-factory Post Blog
```

Generated `Modules/Blog/database/factories/PostFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Blog\Models\Post;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Blog\Models\Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            //
        ];
    }
}
```

#### 2. Explicit Model Option (`--model`)
You can explicitly define a model name or a fully qualified class name:

```bash
# Model in same module (Modules\Blog\Models\BlogPost)
php artisan asasflow:make-factory PostFactory Blog --model=BlogPost

# Fully qualified class name
php artisan asasflow:make-factory UserFactory Blog --model="Modules\Core\Models\Users"
```

---

## ⚡ Running Seeders: `asasflow:seed`

Use `asasflow:seed` (aliases: `asasflow:module:seed`, `asasflow:db:seed`) to execute seeders for a specific module or across all modules.

### Examples

#### 1. Seed a Specific Module
Auto-detects the module's master seeder (`{Module}DatabaseSeeder`, `DatabaseSeeder`, or all seeders under `database/seeders`):

```bash
php artisan asasflow:seed Blog
```

#### 2. Seed a Specific Seeder Class
```bash
php artisan asasflow:seed Blog --class=PostSeeder
```

#### 3. Seed All Enabled Modules
Run seeders across all discovered modules in one command:

```bash
php artisan asasflow:seed
```

#### 4. Additional Options
```bash
# Specify database connection
php artisan asasflow:seed Blog --database=tenant_pgsql

# Force execution in production environments
php artisan asasflow:seed Blog --force
```

---

## 💡 Best Practices

1. **Call Child Seeders in Master Seeder**:
   Use `{Module}DatabaseSeeder` as an orchestrator for all seeders in your module:
   ```php
   namespace Modules\Blog\Database\Seeders;

   use Illuminate\Database\Seeder;

   class BlogDatabaseSeeder extends Seeder
   {
       public function run(): void
       {
           $this->call([
               CategorySeeder::class,
               PostSeeder::class,
           ]);
       }
   }
   ```
2. **Re-generate Composer Autoload**:
   After creating new seeders or factories, remember to run `composer dump-autoload` if class names are not immediately resolved by PHP.
