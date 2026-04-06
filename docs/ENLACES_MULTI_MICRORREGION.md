# Enlaces con acceso multi-microrregión

**Fecha de generación:** 2026-03-04  
**Proyecto:** SEGOB (Laravel)

## Resumen ejecutivo

Se encuentran registrados **6 usuarios Enlace** con configuración homogénea:

- `name`: `N N.`
- `area_id`: `8`
- `cargo_id`: `8` (ANALISTA)
- `activo`: `1`
- Rol: `Enlace` (guards `web` y `sanctum`)
- Permisos: `Mesas-Paz`, `Modulos-Temporales`

La cobertura total de Enlaces mantiene el universo **01 a 31**, distribuido por usuario mediante la tabla pivote `user_microrregion`.

## Usuarios Enlace

| user_id | email | name | activo | area_id | cargo_id | microrregiones asignadas | total |
|---:|---|---|---:|---:|---:|---:|
| 193 | fernanda.martinezlazo@gmail.com | N N. | 1 | 8 | 8 | 01, 02, 03, 04, 27 | 5 |
| 175 | kevinastridroldan@gmail.com | N N. | 1 | 8 | 8 | 05, 06, 14, 31 | 4 |
| 194 | adamberick818@gmail.com | N N. | 1 | 8 | 8 | 09, 10, 11, 16, 17, 19, 20 | 7 |
| 197 | anettcruz.dgd@gmail.com | N N. | 1 | 8 | 8 | 07, 08, 18, 21 | 4 |
| 195 | rousserrano4982@gmail.com | N N. | 1 | 8 | 8 | 22, 23, 28, 29 | 4 |
| 196 | andrea.montiel@puebla.gob.mx | N N. | 1 | 8 | 8 | 12, 13, 15, 24, 25, 26, 30 | 7 |

## Cobertura de microrregiones

- Cobertura conjunta de Enlaces: `01..31`
- Total de asignaciones en pivote para estos 6 usuarios: **31**
- Total de microrregiones en catálogo (`01..31`): **31**

## Distribución solicitada aplicada

- **Fernanda:** 1, 2, 3, 4 y 27
- **Kevin:** 5, 6, 14 y 31
- **Adán:** 9, 10, 11, 16, 17, 19 y 20
- **Anett:** 7, 8, 18 y 21
- **Rosalia:** 22, 23, 28 y 29
- **Andrea:** 12, 13, 15, 24, 25, 26 y 30

## Catálogo de microrregiones 01..31

| MR | Cabecera |
|---|---|
| 01 | XICOTEPEC |
| 02 | HUAUCHINANGO |
| 03 | CHIGNAHUAPAN |
| 04 | ZACAPOAXTLA |
| 05 | LIBRES |
| 06 | TEZIUTLÁN |
| 07 | SAN MARTÍN TEXMELUCAN |
| 08 | HUEJOTZINGO |
| 09 | PUEBLA |
| 10 | PUEBLA |
| 11 | PUEBLA |
| 12 | AMOZOC |
| 13 | TEPEACA |
| 14 | CHALCHICOMULA DE SESMA |
| 15 | TECAMACHALCO |
| 16 | PUEBLA |
| 17 | PUEBLA |
| 18 | CHOLULA |
| 19 | PUEBLA |
| 20 | PUEBLA |
| 21 | ATLIXCO |
| 22 | IZUCAR DE MATAMOROS |
| 23 | ACATLÁN DE OSORIO |
| 24 | TEHUACÁN |
| 25 | TEHUACÁN |
| 26 | AJALPAN |
| 27 | CUAUTEMPAN |
| 28 | CHIAUTLA |
| 29 | TEPEXI DE RODRÍGUEZ |
| 30 | ACATZINGO |
| 31 | TLATLAUQUITEPEC |

## Consulta de verificación rápida

```sql
SELECT u.id, u.email, u.name, u.activo, u.area_id, u.cargo_id,
       COUNT(um.microrregion_id) AS microrregiones
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




APP_NAME=Laravel
APP_ENV=production
APP_KEY=base64:aI2Ax5e1Fk7fcVeq1eWU5kNLb89liz1k8xrZ9lyXd1o=
APP_DEBUG=false
APP_URL=https://https://kevinrolcer.com/
APP_FORCE_HTTPS=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kevinast_segob
DB_USERNAME=kevinast_dgdg_admon
DB_PASSWORD="dgdgAdmon1&"

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.https://kevinrolcer.com/
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax









APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:aI2Ax5e1Fk7fcVeq1eWU5kNLb89liz1k8xrZ9lyXd1o=
APP_DEBUG=true
APP_URL=http://localhost

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
# APP_MAINTENANCE_STORE=database

# PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kevinast_segob
DB_USERNAME=kevinast_dgdg_admon
DB_PASSWORD=dgdgAdmon1&

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=file
# CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

GEMINI_API_KEY=

GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta

GEMINI_MODEL=gemini-1.5-flash

GEMINI_VERTEX_AI_PROJECT_ID=

GEMINI_VERTEX_AI_LOCATION=
