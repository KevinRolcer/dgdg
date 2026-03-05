# SECURITY.md

## Objetivo
Este documento define el checklist de seguridad para SEGOB (Laravel) y consolida los **faltantes** detectados durante la revisión.

---

## Alcance
- Aplicación web Laravel (`routes/web.php`, controladores, modelos, vistas Blade).
- Subida de archivos (módulos temporales).
- Configuración de entorno (`.env`) y encabezados HTTP.

---

## Estado actual (resumen)
### Controles implementados
- Validación de entrada en controladores (`$request->validate(...)`).
- Protección CSRF en formularios (`@csrf`) y flujo `web` autenticado.
- Uso de Blade escapado (sin uso detectado de salida cruda `{!! !!}` en vistas auditadas).
- Sin consultas SQL crudas peligrosas detectadas (`DB::raw`, `whereRaw`, `selectRaw`, etc.) en el código auditado.
- Cabeceras de seguridad reforzadas en middleware (`X-Frame-Options`, `X-Content-Type-Options`, CSP, etc.).
- Restricción de tipos de imagen permitidos en carga (`jpg`, `jpeg`, `png`, `webp`).

### Riesgos aún abiertos
- Configuración de entorno no endurecida para producción en `.env` actual (`APP_ENV=local`, `APP_DEBUG=true`).
- `SESSION_ENCRYPT=false` en `.env` actual.
- No hay escaneo antivirus/antimalware de archivos subidos.
- CSP aún permite `'unsafe-inline'` (riesgo controlado, pero mejorable).

---

## Checklist de pruebas de seguridad
Usar este checklist en cada release y previo a despliegue a producción.

### 1) Mass Assignment
**Objetivo:** Evitar modificación de campos sensibles por asignación masiva.

- [ ] Verificar que todos los modelos de escritura tengan `fillable` explícito (o `guarded` correcto).
- [ ] Confirmar que no se use `Model::create($request->all())`.
- [ ] Confirmar que no se use `update($request->all())`.
- [ ] Confirmar que payloads validados no contengan campos privilegiados (`is_admin`, `role_id`, `created_by` no autorizado).
- [ ] Probar intento manual de sobreescritura:
  - Enviar campo sensible no permitido en POST/PUT.
  - Resultado esperado: ignorado o validación/rechazo.

**Evidencia mínima:** capturas de request/response + diff de DB antes/después.

---

### 2) CSRF (Cross-Site Request Forgery)
**Objetivo:** Asegurar que acciones con estado no puedan ser forzadas desde sitios externos.

- [ ] Confirmar `@csrf` en todos los formularios `POST/PUT/PATCH/DELETE`.
- [ ] Probar envío de formulario sin token CSRF.
  - Resultado esperado: `419 Page Expired`.
- [ ] Probar envío con token alterado.
  - Resultado esperado: `419`.
- [ ] Confirmar cookies de sesión con `HttpOnly` y `SameSite` apropiado (`lax`/`strict`).
- [ ] En producción HTTPS: verificar cookie `Secure` activa.

**Comando útil:**
- `php artisan route:list`

---

### 3) XSS (Cross-Site Scripting)
**Objetivo:** Evitar ejecución de scripts inyectados por datos de usuario.

- [ ] Verificar que las vistas usen `{{ }}` para salida de usuario.
- [ ] Buscar y justificar cada uso de `@php` (evitar construir HTML dinámico inseguro).
- [ ] Inyectar payload de prueba en campos texto:
  - `<script>alert(1)</script>`
  - `"><img src=x onerror=alert(1)>`
  - Resultado esperado: renderizado escapado, sin ejecución.
- [ ] Verificar presencia de CSP en respuestas HTML.
- [ ] Revisar consola del navegador por violaciones CSP y ajustar fuentes legítimas.

**Comando útil (búsqueda):**
- `grep`/buscador del IDE sobre patrones `{!!` y `onerror=`.

---

### 4) SQL Injection
**Objetivo:** Garantizar consultas parametrizadas y sin concatenación insegura.

- [ ] Verificar ausencia de `whereRaw/selectRaw/DB::raw` con input usuario.
- [ ] Confirmar uso de Query Builder/Eloquent con bindings.
- [ ] Probar payloads SQLi en parámetros de entrada:
  - `' OR 1=1 --`
  - `" OR "1"="1`
  - Resultado esperado: validación o cero impacto en consulta.
- [ ] Confirmar validación de tipos (`integer`, `date`, `boolean`, `Rule::in`, etc.).

---

### 5) Exposición de variables sensibles (.env)
**Objetivo:** Evitar filtración de secretos y debugging en producción.

- [ ] Confirmar que `.env` no esté versionado ni expuesto por web server.
- [ ] Verificar en **producción**:
  - [ ] `APP_ENV=production`
  - [ ] `APP_DEBUG=false`
  - [ ] `APP_URL=https://...`
  - [ ] `APP_KEY` válido
  - [ ] `SESSION_ENCRYPT=true`
  - [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Confirmar que errores en producción no muestren stack traces.
- [ ] Revisar logs para asegurarse de no registrar secretos (tokens, contraseñas, claves).

---

### 6) Inyección de malware (subida de archivos)
**Objetivo:** Reducir riesgo de carga de archivos peligrosos.

- [ ] Verificar validación de mime/extensión permitida.
- [ ] Verificar tamaño máximo de archivo.
- [ ] Confirmar almacenamiento fuera de rutas ejecutables directas.
- [ ] Probar carga de archivo renombrado malicioso (`.php` disfrazado).
  - Resultado esperado: rechazo.
- [ ] Implementar/validar escaneo antivirus (ClamAV o servicio externo).

---

### 7) Interrupciones del servicio (disponibilidad)
**Objetivo:** Mitigar abuso y degradación.

- [ ] Confirmar `throttle` en login y endpoints sensibles.
- [ ] Probar ráfagas de requests en autenticación.
  - Resultado esperado: bloqueo temporal por rate limit.
- [ ] Verificar backups de DB y prueba de restauración.
- [ ] Verificar monitoreo de errores y alertamiento.
- [ ] Definir runbook de contingencia/incidente.

---

## Faltantes anexadas (priorizadas)

### Prioridad Alta (antes de producción)
- [ ] Cambiar `.env` de despliegue a:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `SESSION_ENCRYPT=true`
  - `SESSION_SECURE_COOKIE=true` (siempre en HTTPS)
- [ ] Forzar HTTPS extremo a extremo (app + proxy/webserver).
- [ ] Activar escaneo antivirus para archivos subidos.
- [ ] Revisar y endurecer permisos de archivos/carpetas en servidor.

### Prioridad Media
- [ ] Migrar CSP para eliminar `'unsafe-inline'` usando nonce/hash.
- [ ] Definir política de retención y saneamiento de archivos temporales.
- [ ] Añadir pruebas automatizadas de seguridad básicas (feature tests):
  - CSRF inválido -> 419.
  - Usuario sin permiso -> 403.
  - Payload XSS -> no ejecuta.

### Prioridad Baja (mejora continua)
- [ ] Integrar SAST en pipeline CI (PHPStan/Larastan + reglas de seguridad).
- [ ] Integrar DAST periódico (OWASP ZAP baseline).
- [ ] Revisión trimestral de dependencias y CVEs.

---

## Criterio de cierre (Definition of Done)
Una versión se considera lista de seguridad cuando:
- [ ] Todos los checks de prioridad alta están completados.
- [ ] No hay findings críticos/altos sin plan de remediación.
- [ ] Se adjuntan evidencias de pruebas (capturas/logs/reportes).
- [ ] Se valida nuevamente después de cualquier cambio de infraestructura.

---

## Comandos operativos sugeridos
- Limpiar cachés de config/rutas:  
  `php artisan config:clear && php artisan route:clear`
- Optimizar config en producción:  
  `php artisan config:cache && php artisan route:cache`
- Ver rutas activas:  
  `php artisan route:list`

---

## Nota final
Este checklist no reemplaza una auditoría pentest formal. Sirve como baseline operativo para prevenir riesgos comunes (Mass Assignment, CSRF, XSS, SQLi, exposición de `.env`, malware y disponibilidad).
