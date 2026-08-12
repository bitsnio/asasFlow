# Laravel Project Installation Guide

This guide will walk you through the process of cloning and installing an existing Laravel project using Composer.

## Prerequisites

Before you begin, ensure you have the following installed on your system:

- PHP (8.1 or higher recommended)
- Composer (2.0+ recommended)
- Git
- MySQL, PostgreSQL, or SQLite
- Node.js and NPM (for frontend assets)
- Server requirements:
  - BCMath PHP Extension
  - Ctype PHP Extension
  - Fileinfo PHP Extension
  - JSON PHP Extension
  - Mbstring PHP Extension
  - OpenSSL PHP Extension
  - PDO PHP Extension
  - Tokenizer PHP Extension
  - XML PHP Extension

## Installation Steps

### 1. Clone the Repository

```bash
git clone https://github.com/username/project-name.git
cd project-name
```

### 2. Install Composer Dependencies

```bash
composer install
```

### 3. Create Environment File

Copy the example environment file and generate an application key:

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Environment Variables

Open the `.env` file in your text editor and configure your database connection:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
```

### 5. Run Database Migrations

```bash
php artisan migrate
```

If the project includes seed data, you can run:

```bash
php artisan db:seed
```

### 6. Install Frontend Dependencies (if applicable)

```bash
npm install
npm run dev
```

For production:

```bash
npm run build
```

### 7. Create Storage Link (if needed)

```bash
php artisan storage:link
```

### 8. Set Directory Permissions

```bash
chmod -R 775 storage bootstrap/cache
```

### 9. Clear Configuration Cache

```bash
php artisan config:clear
php artisan cache:clear
```

### 10. Serve the Application

For local development:

```bash
php artisan serve
```

This will start a development server at `http://localhost:8000`.

## Troubleshooting

### Common Issues

1. **Composer Memory Limit**
   
   If Composer runs out of memory:
   ```bash
   COMPOSER_MEMORY_LIMIT=-1 composer install
   ```

2. **Permission Denied Errors**
   
   If you encounter permission issues:
   ```bash
   sudo chown -R $USER:www-data storage
   sudo chown -R $USER:www-data bootstrap/cache
   ```

3. **Database Connection Issues**
   
   Verify your database credentials and ensure the database exists.

4. **Missing Extensions**
   
   If PHP extensions are missing, install them using your system's package manager.

## Next Steps

After installation, you should:

1. Review the project documentation
2. Set up your IDE/editor
3. Configure your local development environment
4. Learn about the project structure and architecture

For more information, refer to the [Laravel documentation](https://laravel.com/docs).