# Documentación completa — Enlaces con acceso multi-microrregión

## 1) Objetivo

Implementar soporte para usuarios con rol **Enlace** que puedan operar sobre **múltiples microrregiones (1..N)**, manteniendo compatibilidad con el flujo existente de Delegado (1..1), y habilitando su uso en:

- Captura y guardado de Mesas de Paz.
- Supervisión de Mesas de Paz.
- Selección de usuarios en Módulos Temporales.

## 2) Alcance funcional

### Reglas para Enlace

- `cargo_id = 8` (ANALISTA)
- `area_id = 8`
- `name = 'N N.'`
- `activo = 1`
- Rol `Enlace` en guards `web` y `sanctum`
- Permisos asignados:
  - `Mesas-Paz`
  - `Modulos-Temporales`

### Correos definidos

1. `fernanda.martinezlazo@gmail.com`
2. `adamberick818@gmail.com`
3. `rousserrano4982@gmail.com`
4. `andrea.montiel@puebla.gob.mx`
5. `anettcruz.dgd@gmail.com`
6. `kevinastridroldan@gmail.com`

### Distribución de microrregiones aplicada

- Fernanda (`fernanda.martinezlazo@gmail.com`): `01, 02, 03, 04, 27`
- Kevin (`kevinastridroldan@gmail.com`): `05, 06, 14, 31`
- Adán (`adamberick818@gmail.com`): `09, 10, 11, 16, 17, 19, 20`
- Anett (`anettcruz.dgd@gmail.com`): `07, 08, 18, 21`
- Rosalia (`rousserrano4982@gmail.com`): `22, 23, 28, 29`
- Andrea (`andrea.montiel@puebla.gob.mx`): `12, 13, 15, 24, 25, 26, 30`

## 3) Cambios de modelo de datos

### 3.1 Tabla pivote `user_microrregion`

Archivo: `database/migrations/2026_03_04_194500_create_user_microrregion_table.php`

Estructura principal:

- `id` (PK)
- `user_id` (`unsignedInteger`, FK a `users.id`)
- `microrregion_id` (`unsignedBigInteger`, FK a `microrregiones.id`)
- timestamps
- índice único: `uq_user_microrregion (user_id, microrregion_id)`
- índice adicional: `idx_user_microrregion_micro (microrregion_id)`

### 3.2 Relación en `User`

Archivo: `app/Models/User.php`

Se agregó relación `belongsToMany` para microrregiones asignadas (N:N), usada por servicios para resolver alcance por usuario.

## 4) Cambios en seeders

### 4.1 `EnlacesSeeder`

Archivo: `database/seeders/EnlacesSeeder.php`

Responsabilidades:

- Crear/actualizar los 6 usuarios Enlace con los atributos obligatorios.
- Crear/garantizar rol `Enlace` en ambos guards.
- Crear/garantizar permisos `Mesas-Paz` y `Modulos-Temporales` en ambos guards.
- Asignar rol a cada usuario.
- Reemplazar asignaciones existentes en `user_microrregion` e insertar solo las MR definidas para cada correo según la distribución oficial.

### 4.2 `DatabaseSeeder`

Archivo: `database/seeders/DatabaseSeeder.php`

Incluye ejecución de `EnlacesSeeder` dentro del flujo de seeding general.

## 5) Cambios en lógica de negocio

### 5.1 Acceso a captura Mesas de Paz

Archivo: `app/Http/Controllers/MesasPazController.php`

Se ajustó validación de acceso para permitir:

- Delegado con microrregión directa, y
- Enlace con al menos una entrada en `user_microrregion`.

### 5.2 Servicio de Mesas de Paz

Archivo: `app/Services/MesasPaz/MesasPazService.php`

Se incorporó soporte multi-MR para Enlace en:

- Resolución de microrregiones permitidas por usuario.
- Resolución de municipios permitidos por usuario.
- Validación de pertenencia al guardar municipio/acuerdos.
- Compatibilidad de flujos de modo especial y guardado masivo.

### 5.3 Supervisión de Mesas de Paz

Archivos:

- `app/Http/Controllers/MesasPazSupervisionController.php`
- `app/Services/MesasPaz/MesasPazSupervisionService.php`

Ajustes:

- El controlador pasa el usuario autenticado al servicio.
- El servicio permite rol `Enlace` y filtra resultados por sus microrregiones asignadas.

### 5.4 Módulos Temporales

Archivo: `app/Services/TemporaryModules/TemporaryModuleAccessService.php`

Cambios:

- Conserva listado de delegados activos válidos (área/cargo y MR 1..31).
- Agrega usuarios Enlace activos (`cargo_id=8`) al listado de selección.
- Expone etiquetas de microrregiones asociadas para despliegue en UI.

Vistas relacionadas:

- `resources/views/temporary_modules/admin/create.blade.php`
- `resources/views/temporary_modules/admin/edit.blade.php`

Muestran contexto de alcance (MR/cabecera/email) al seleccionar usuario destino.

## 6) Evidencia de estado actual (BD)

Validación ejecutada el 2026-03-04:

- Usuarios Enlace encontrados: **6**
- Asignaciones en `user_microrregion` para esos usuarios: **31**
- Cobertura conjunta: **31 microrregiones (01..31)**
- Composición en selector de Módulos Temporales: **37 total (31 Delegado + 6 Enlace)**

### Usuarios confirmados

| user_id | email | activo | area_id | cargo_id | MR asignadas |
|---:|---|---:|---:|---:|---:|
| 193 | fernanda.martinezlazo@gmail.com | 1 | 8 | 8 | 5 (01, 02, 03, 04, 27) |
| 175 | kevinastridroldan@gmail.com | 1 | 8 | 8 | 4 (05, 06, 14, 31) |
| 194 | adamberick818@gmail.com | 1 | 8 | 8 | 7 (09, 10, 11, 16, 17, 19, 20) |
| 197 | anettcruz.dgd@gmail.com | 1 | 8 | 8 | 4 (07, 08, 18, 21) |
| 195 | rousserrano4982@gmail.com | 1 | 8 | 8 | 4 (22, 23, 28, 29) |
| 196 | andrea.montiel@puebla.gob.mx | 1 | 8 | 8 | 7 (12, 13, 15, 24, 25, 26, 30) |

## 7) Ejecución técnica

### 7.1 Migración (solo pivote)

```bash
php artisan migrate --path=database/migrations/2026_03_04_194500_create_user_microrregion_table.php --force
```

### 7.2 Seeder

```bash
php artisan db:seed --class=EnlacesSeeder --force
```

## 8) Consultas de auditoría

### 8.1 Ver usuarios Enlace y total de MRs por usuario

```sql
SELECT u.id, u.email, u.name, u.activo, u.area_id, u.cargo_id,
       COUNT(um.microrregion_id) AS total_mr
FROM users u
LEFT JOIN user_microrregion um ON um.user_id = u.id
WHERE u.email IN (
  'fernanda.martinezlazo@gmail.com',
  'adamberick818@gmail.com',
  'rousserrano4982@gmail.com',
  'andrea.montiel@puebla.gob.mx',
  'anettcruz.dgd@gmail.com',
  'kevinastridroldan@gmail.com'
)
GROUP BY u.id, u.email, u.name, u.activo, u.area_id, u.cargo_id
ORDER BY u.id;
```

### 8.2 Ver detalle de microrregiones por usuario

```sql
SELECT u.email, m.microrregion, m.cabecera
FROM users u
JOIN user_microrregion um ON um.user_id = u.id
JOIN microrregiones m ON m.id = um.microrregion_id
WHERE u.email IN (
  'fernanda.martinezlazo@gmail.com',
  'adamberick818@gmail.com',
  'rousserrano4982@gmail.com',
  'andrea.montiel@puebla.gob.mx',
  'anettcruz.dgd@gmail.com',
  'kevinastridroldan@gmail.com'
)
ORDER BY u.email, CAST(m.microrregion AS UNSIGNED);
```

### 8.3 Ver composición del selector de Módulos Temporales

```sql
-- Conteo de Enlaces activos con cargo 8
SELECT COUNT(*) AS enlaces_activos
FROM users
WHERE activo = 1 AND cargo_id = 8
  AND email IN (
    'fernanda.martinezlazo@gmail.com',
    'adamberick818@gmail.com',
    'rousserrano4982@gmail.com',
    'andrea.montiel@puebla.gob.mx',
    'anettcruz.dgd@gmail.com',
    'kevinastridroldan@gmail.com'
  );
```

## 9) Riesgos/consideraciones

- El seeder define contraseña fija (`asdf1234`) para estos usuarios; se recomienda cambio inmediato en entorno productivo.
- Si en futuro un Enlace no debe ver todas las MRs, basta ajustar sus filas en `user_microrregion` sin cambiar código.
- La migración completa del proyecto puede fallar en entornos con tablas legacy ya creadas; usar migración dirigida para esta pieza cuando aplique.

## 10) Checklist de aceptación

- [x] Existe tabla pivote `user_microrregion` con FKs válidas.
- [x] Existen 6 usuarios Enlace con atributos solicitados.
- [x] Cada Enlace tiene 31 MRs asignadas.
- [x] Enlace puede entrar a Mesas de Paz conforme a sus asignaciones.
- [x] Supervisión filtra por alcance de usuario.
- [x] Enlaces aparecen en listado de Módulos Temporales.

---

Documento generado con estado real de BD y código al **2026-03-04**.
