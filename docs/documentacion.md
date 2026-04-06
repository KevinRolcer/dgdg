# Documentación de cambios y anexos

## 02/03/2026

### Implementación de roles y usuarios
- Se instalaron los paquetes `spatie/laravel-permission` y `laravel/sanctum`.
- Se publicaron archivos de configuración y migraciones.
- Se ejecutaron migraciones para crear las tablas de roles, permisos y tokens.
- Se actualizó el modelo `User` para incluir los traits `HasApiTokens` y `HasRoles`.
- Se agregó la guía de uso en `guia-roles-usuarios.md`.

### Estructura y compatibilidad
- La lógica de roles y permisos es compatible con el sistema anterior.
- Se puede asignar roles y permisos a usuarios, y proteger rutas por roles/permisos.
- Se puede emitir tokens para autenticación API con Sanctum.

---
Para futuras integraciones, documentar aquí cualquier cambio relevante en roles, permisos, autenticación o estructura de usuarios.
