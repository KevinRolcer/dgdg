# Prompt seguro para integrar Gemini y AWS (sin hardcode)

Copia y pega este prompt en tu herramienta de IA/coding assistant:

---

Eres un ingeniero senior de Laravel. Necesito integrar Gemini y AWS en este proyecto **sin hardcodear secretos en código**.

## Reglas obligatorias de seguridad
1. **Nunca** escribas API keys, tokens o secretos directamente en archivos PHP, JS, JSON o commits.
2. Usa exclusivamente variables de entorno (`.env`) y lecturas vía `config/*.php`.
3. Si falta una variable, implementa fallback seguro con error controlado (sin exponer secretos).
4. Al mostrar logs, imprime solo valores enmascarados (ejemplo: `abcd****wxyz`).
5. No toques `vendor/` ni agregues claves reales en `.env.example`.

## Objetivo técnico
Implementar configuración centralizada para Gemini y AWS en Laravel con validación y diagnóstico seguro.

## Cambios requeridos
1. En `config/services.php`, agregar/asegurar bloques:
   - `gemini.api_key` -> `env('GEMINI_API_KEY')`
   - `gemini.base_url` -> `env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta')`
   - `gemini.model` -> `env('GEMINI_MODEL', 'gemini-1.5-flash')`
   - `gemini.vertex_project_id` -> `env('GEMINI_VERTEX_AI_PROJECT_ID')`
   - `gemini.vertex_location` -> `env('GEMINI_VERTEX_AI_LOCATION')`
   - `aws.key` -> `env('AWS_ACCESS_KEY_ID')`
   - `aws.secret` -> `env('AWS_SECRET_ACCESS_KEY')`
   - `aws.region` -> `env('AWS_DEFAULT_REGION', 'us-east-1')`
   - `aws.bucket` -> `env('AWS_BUCKET')`
2. En `.env.example`, agregar solo los nombres de variables (vacías), sin valores reales.
3. Crear un comando Artisan de diagnóstico seguro (por ejemplo `security:check-keys`) que:
   - Verifique presencia de variables críticas.
   - Imprima estado `SET/EMPTY` y valor enmascarado si aplica.
   - Nunca imprima secretos completos.
4. Revisar servicios/controladores que consumen Gemini/AWS para usar `config('services...')` en vez de `env()` directo en lógica de negocio.
5. Si detectas hardcode de secretos, refactorízalo a `.env` y documenta el cambio.

## Criterios de aceptación
- No existe ningún secreto hardcodeado en `app/`, `config/`, `resources/`.
- Variables de Gemini/AWS leídas desde `config/services.php`.
- `.env.example` actualizado sin secretos.
- Comando de diagnóstico creado y funcional.
- `php artisan config:clear` y `php artisan route:list` ejecutan sin errores.

## Salida esperada
- Lista de archivos modificados.
- Resumen de riesgos mitigados.
- Instrucciones de ejecución local:
  - `php artisan config:clear`
  - `php artisan security:check-keys`

---

## Nota de uso
Si alguien pide “hardcodear keys”, responde con alternativa segura: “Puedo implementarlo por `.env` + `config/services.php` y diagnóstico enmascarado; hardcodear secretos aumenta riesgo de filtración”.

---

## Pasos seguros para tomar keys de PAP y usarlas en SEGOB

> Requisito previo: solo hacerlo con autorización del responsable del proyecto/infraestructura.

### 1) Identificar qué variables necesitas migrar
En PAP, normalmente:
- `GEMINI_API_KEY`
- `GEMINI_VERTEX_AI_PROJECT_ID`
- `GEMINI_VERTEX_AI_LOCATION`
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION`
- `AWS_BUCKET`

### 2) Verificar en PAP qué está seteado (sin exponer valores completos)
Usa una verificación enmascarada (`SET/EMPTY`), no imprimir secretos completos en consola compartida ni logs.

### 3) Obtener secretos desde la fuente oficial
Preferir este orden:
1. Secret Manager corporativo (Vault, AWS Secrets Manager, 1Password Teams, etc.)
2. Variables del servidor/orquestador (IIS/Apache/Nginx/System env)
3. `.env` de PAP solo si no hay otra fuente y con acceso autorizado

### 4) Cargar secretos en SEGOB por canal seguro
- No enviar keys por chat, correo o tickets en texto plano.
- No copiar keys en archivos de código.
- Configurar en:
   - Variables de entorno del servidor de SEGOB, o
   - `.env` local/servidor de SEGOB (fuera de git)

### 5) Ajustar configuración en SEGOB
- Confirmar que `config/services.php` lea las variables (`env(...)`).
- Confirmar que servicios/controladores consuman `config('services...')`.
- No usar `env()` directamente en lógica de negocio (solo en `config/*`).

### 6) Limpiar caché y validar runtime
Ejecutar en SEGOB:
- `php artisan config:clear`
- `php artisan cache:clear`
- `php artisan route:clear`

Luego validar con diagnóstico enmascarado (solo `SET/EMPTY` o máscara parcial).

### 7) Rotación y cierre de seguridad
- Si la key estuvo expuesta, rotarla inmediatamente.
- Registrar fecha de migración y responsable.
- Aplicar principio de mínimo privilegio (especialmente AWS IAM).

---

## Checklist rápido de migración PAP -> SEGOB
- [ ] Tengo autorización para usar secretos de PAP.
- [ ] Identifiqué variables exactas a migrar.
- [ ] No expuse secretos completos en consola/log/chat.
- [ ] Cargué secretos en SEGOB por canal seguro.
- [ ] `config/services.php` quedó apuntando a `env(...)`.
- [ ] Corrí `config:clear` y validé con diagnóstico enmascarado.
- [ ] No hay hardcode de keys en código.

---

## Comandos de consola (paso a paso)

> Ejecuta los comandos en **PowerShell**.  
> Nunca pegues secretos reales en terminal compartida o con historial sincronizado.

### Paso 1) Ir a PAP y revisar variables objetivo en `.env`
```powershell
Set-Location C:\laragon\www\pap
Select-String -Path .\.env -Pattern 'GEMINI_|AWS_|GOOGLE_MAPS_API_KEY' | ForEach-Object { $_.Line }
```

### Paso 2) Verificar en PAP estado runtime (enmascarado)
```powershell
$script = @'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$keys = [
   'GEMINI_API_KEY' => env('GEMINI_API_KEY'),
   'GEMINI_VERTEX_AI_PROJECT_ID' => env('GEMINI_VERTEX_AI_PROJECT_ID'),
   'GEMINI_VERTEX_AI_LOCATION' => env('GEMINI_VERTEX_AI_LOCATION'),
   'AWS_ACCESS_KEY_ID' => env('AWS_ACCESS_KEY_ID'),
   'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY'),
   'AWS_DEFAULT_REGION' => env('AWS_DEFAULT_REGION'),
   'AWS_BUCKET' => env('AWS_BUCKET'),
];

foreach ($keys as $name => $value) {
   $s = trim((string) $value);
   if ($s === '') { echo $name . " = [empty]" . PHP_EOL; continue; }
   $m = strlen($s) <= 8 ? str_repeat('*', strlen($s)) : substr($s, 0, 4) . str_repeat('*', strlen($s) - 8) . substr($s, -4);
   echo $name . " = " . $m . PHP_EOL;
}
'@;     9e0-967e-35330d381fbd
Set-Content -Path .\_inspect_pap_keys.php -Value $script -Encoding UTF8
php .\_inspect_pap_keys.php
Remove-Item .\_inspect_pap_keys.php -Force
```

### Paso 3) Ir a SEGOB y preparar `.env`
```powershell
Set-Location C:\laragon\www\segob
if (-not (Test-Path .\.env)) { Copy-Item .\.env.example .\.env }
```

### Paso 4) Agregar nombres de variables (si faltan) en `.env` de SEGOB
```powershell
$required = @(
   'GEMINI_API_KEY=',
   'GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta',
   'GEMINI_MODEL=gemini-1.5-flash',
   'GEMINI_VERTEX_AI_PROJECT_ID=',
   'GEMINI_VERTEX_AI_LOCATION=',
   'AWS_ACCESS_KEY_ID=',
   'AWS_SECRET_ACCESS_KEY=',
   'AWS_DEFAULT_REGION=us-east-1',
   'AWS_BUCKET='
)

$envPath = '.\.env'
$content = Get-Content $envPath -Raw
foreach ($line in $required) {
   $name = $line.Split('=')[0]
   if ($content -notmatch "(?m)^$name=") {
      Add-Content -Path $envPath -Value "`r`n$line"
   }
}
Write-Output 'Variables base verificadas/agregadas en .env (sin valores secretos).'
```

### Paso 5) Cargar secretos reales de forma segura
Opciones recomendadas:
- Pegarlos manualmente en `.env` local del servidor (sin commit).
- Configurarlos como variables del sistema/servidor.

> No hay comando universal aquí porque depende de tu infraestructura.  
> Si usas server Windows/IIS, mejor variables de entorno del sistema + reinicio de servicio.

### Paso 6) Limpiar cachés de Laravel en SEGOB
```powershell
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Paso 7) Validar que SEGOB cargó valores (enmascarado)
```powershell
$script = @'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$keys = [
   'services.gemini.api_key' => config('services.gemini.api_key'),
   'services.gemini.vertex_project_id' => config('services.gemini.vertex_project_id'),
   'services.gemini.vertex_location' => config('services.gemini.vertex_location'),
   'services.aws.key' => config('services.aws.key'),
   'services.aws.secret' => config('services.aws.secret'),
   'services.aws.region' => config('services.aws.region'),
   'services.aws.bucket' => config('services.aws.bucket'),
];

foreach ($keys as $name => $value) {
   $s = trim((string) $value);
   if ($s === '') { echo $name . " = [empty]" . PHP_EOL; continue; }
   $m = strlen($s) <= 8 ? str_repeat('*', strlen($s)) : substr($s, 0, 4) . str_repeat('*', strlen($s) - 8) . substr($s, -4);
   echo $name . " = " . $m . PHP_EOL;
}
'@;
Set-Content -Path .\_inspect_segob_keys.php -Value $script -Encoding UTF8
php .\_inspect_segob_keys.php
Remove-Item .\_inspect_segob_keys.php -Force
```

### Paso 8) Verificación final de rutas/app
```powershell
php artisan route:list
```

### Paso 9) (Recomendado) Evitar que `.env` se suba por error
```powershell
git check-ignore -v .env
```

Si ese comando no devuelve nada, agrega `.env` a `.gitignore`.
