# Guía de implementación de roles y usuarios

## 1. Instalación de dependencias

Ejecuta en terminal:
```
composer require spatie/laravel-permission laravel/sanctum
```

## 2. Publicar archivos de configuración y migraciones
```
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

## 3. Ejecutar migraciones
```
php artisan migrate
```

## 4. Configurar el modelo User
Agrega los traits en `app/Models/User.php`:
```php
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable, HasFactory;
    // ...
}
```

## 5. Uso básico
- Asignar rol: `$user->assignRole('Admin');`
- Asignar permiso: `$role->givePermissionTo('edit articles');`
- Verificar rol: `$user->hasRole('Admin');`
- Verificar permiso: `$user->can('edit articles');`

## 6. Middleware
Protege rutas usando roles o permisos:
```php
Route::middleware(['role:Admin'])->group(function () {
    // rutas protegidas
});
```

## 7. Autenticación API
Sanctum permite emitir tokens para usuarios:
```php
$token = $user->createToken('nombre_token')->plainTextToken;
```

---
Consulta la documentación oficial de [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6/introduction) y [Laravel Sanctum](https://laravel.com/docs/10.x/sanctum) para más detalles.
























