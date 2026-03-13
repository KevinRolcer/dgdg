# Cola de exportaciones (Excel y Word)

Las exportaciones de **módulos temporales** (Excel de registros e informe Word de análisis) se encolan y las procesa un **worker**. Sin worker activo, los trabajos quedan en la tabla `jobs` y la notificación sigue en “Generando…”.

## Requisitos

1. **`.env`**
   ```env
   QUEUE_CONNECTION=database
   ```
2. **Migraciones** (tablas `jobs` y `failed_jobs`):
   ```bash
   php artisan migrate
   ```

## Desarrollo local

Opción A — todo en uno (servidor + cola + logs + Vite):

```bash
composer run dev
```

Incluye `php artisan queue:listen --tries=1 --timeout=0`.

Opción B — solo worker:

```bash
composer run queue-work
```

O manual:

```bash
php artisan queue:work database --tries=2 --timeout=3600
```

## Producción

1. Mantener **un proceso** (o más) ejecutando el worker de forma permanente.
2. Ejemplo con **Supervisor**: copiar `deploy/supervisor-segob-queue.conf`, ajustar rutas y usuario, luego:

   ```bash
   sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start segob-queue:*
   ```

3. Tras cada deploy con cambios de código:

   ```bash
   php artisan queue:restart
   ```

## Notificaciones y descarga

- Al exportar se crea una notificación **pendiente** (spinner) con `export_request_id`.
- El front hace **polling** a `export-status/{uuid}` hasta que el worker termina.
- Al completar, la misma notificación pasa a **lista** con enlace **url** al archivo (`.xlsx` o `.docx`) en `temporary-exports`.
- Los archivos se sirven por la ruta `modulos-temporales/admin/exportaciones/{archivo}` (solo admin).

## Fallos

- Trabajos fallidos en `failed_jobs`; reintentar:

  ```bash
  php artisan queue:retry all
  ```

- Log del worker: `storage/logs/laravel.log` y, con Supervisor, `storage/logs/queue-worker.log`.
