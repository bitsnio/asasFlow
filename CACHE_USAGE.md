# ASASFLOW Cache Feature Usage

## Basic Route Caching

```php
// In your module routes file
Route::get('/users', [UserController::class, 'index'])
    ->middleware('asasflow.cache:module:users,users:list')
    ->name('module.users.index');
