<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReasignacionMicrorregionesSeeder extends Seeder
{
    public function run(): void
    {
        $fecha = Carbon::now();
        $plan = $this->planMicrorregiones();

        $microIdsByCode = [];
        foreach ($plan as $item) {
            $codigo = $item['codigo'];
            $cabecera = $item['cabecera'];
            $municipios = $item['municipios'];

            $existing = DB::table('microrregiones')
                ->where('microrregion', $codigo)
                ->first();

            if ($existing) {
                DB::table('microrregiones')
                    ->where('id', $existing->id)
                    ->update([
                        'cabecera' => $cabecera,
                        'municipios' => count($municipios),
                        'updated_at' => $fecha,
                    ]);

                $microIdsByCode[$codigo] = (int) $existing->id;
                continue;
            }

            $microIdsByCode[$codigo] = (int) DB::table('microrregiones')->insertGetId([
                'microrregion' => $codigo,
                'cabecera' => $cabecera,
                'municipios' => count($municipios),
                'juntas_auxiliaeres' => 0,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ]);
        }

        $municipiosDb = DB::table('municipios')
            ->select('id', 'municipio')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'nombre' => (string) $row->municipio,
                    'norm' => $this->normalize((string) $row->municipio),
                ];
            })
            ->values()
            ->all();

        $municipiosByNorm = [];
        foreach ($municipiosDb as $row) {
            $municipiosByNorm[$row['norm']] ??= [];
            $municipiosByNorm[$row['norm']][] = $row['id'];
        }

        $missingMunicipios = [];
        $updatedMunicipios = 0;

        foreach ($plan as $item) {
            $codigo = $item['codigo'];
            $microId = $microIdsByCode[$codigo] ?? null;
            if (!$microId) {
                continue;
            }

            foreach ($item['municipios'] as $municipio) {
                $municipioId = $this->resolveMunicipioId($municipio, $municipiosByNorm, $municipiosDb);
                if (!$municipioId) {
                    $missingMunicipios[] = [
                        'codigo' => $codigo,
                        'cabecera' => $item['cabecera'],
                        'municipio' => $municipio,
                    ];
                    continue;
                }

                $updatedMunicipios += DB::table('municipios')
                    ->where('id', $municipioId)
                    ->where('microrregion_id', '!=', $microId)
                    ->update([
                        'microrregion_id' => $microId,
                        'updated_at' => $fecha,
                    ]);
            }
        }

        $this->ensureMunicipiosEspeciales($fecha);

        $delegadosDb = DB::table('delegados')
            ->select('id', 'user_id', 'nombre', 'ap_paterno', 'ap_materno', 'telefono')
            ->get()
            ->map(function ($row) {
                $full = trim(implode(' ', array_filter([
                    (string) $row->nombre,
                    (string) $row->ap_paterno,
                    (string) $row->ap_materno,
                ])));

                return [
                    'id' => (int) $row->id,
                    'user_id' => $row->user_id ? (int) $row->user_id : null,
                    'full' => $full,
                    'full_norm' => $this->normalize($full),
                    'phone_digits' => $this->digits((string) ($row->telefono ?? '')),
                ];
            })
            ->values()
            ->all();

        $missingDelegados = [];
        $updatedDelegados = 0;
        $createdDelegados = 0;
        $createdUsers = 0;
        $updatedUsers = 0;
        $assignments = [];
        $activeUserIds = [];

        foreach ($plan as $item) {
            $codigo = $item['codigo'];
            $microId = $microIdsByCode[$codigo] ?? null;
            if (!$microId) {
                continue;
            }

            $delegadoNombre = $item['delegado']['nombre'];
            $delegadoTelefono = $item['delegado']['telefono'];

            $delegadoId = $this->resolveDelegadoId($delegadoNombre, $delegadoTelefono, $delegadosDb);
            if (!$delegadoId) {
                $delegadoId = $this->createDelegado($delegadoNombre, $delegadoTelefono, $microId, $fecha);
                if ($delegadoId) {
                    $createdDelegados++;
                    $delegadosDb[] = [
                        'id' => $delegadoId,
                        'user_id' => null,
                        'full' => $delegadoNombre,
                        'full_norm' => $this->normalize($delegadoNombre),
                        'phone_digits' => $this->digits($delegadoTelefono),
                    ];
                } else {
                    $missingDelegados[] = [
                        'codigo' => $codigo,
                        'cabecera' => $item['cabecera'],
                        'delegado' => $delegadoNombre,
                        'telefono' => $delegadoTelefono,
                    ];
                    continue;
                }
            }

            $updatedDelegados += DB::table('delegados')
                ->where('id', $delegadoId)
                ->where('microrregion_id', '!=', $microId)
                ->update([
                    'microrregion_id' => $microId,
                    'updated_at' => $fecha,
                ]);

            $userSync = $this->ensureUserForDelegado(
                $delegadoId,
                $delegadoNombre,
                $delegadoTelefono,
                $microId,
                $codigo,
                $fecha
            );

            $createdUsers += (int) ($userSync['created'] ?? 0);
            $updatedUsers += (int) ($userSync['updated'] ?? 0);
            if (!empty($userSync['user_id'])) {
                $activeUserIds[] = (int) $userSync['user_id'];
            }

            $assignments[] = [
                'codigo' => $codigo,
                'cabecera' => $item['cabecera'],
                'delegado' => $delegadoNombre,
                'delegado_id' => $delegadoId,
                'user_id' => $userSync['user_id'] ?? null,
                'user_email' => $userSync['email'] ?? null,
            ];
        }

        $activationStats = $this->applyUserActivationPolicy($activeUserIds, $fecha);

        if (Schema::hasTable('mesas_paz_asistencias')) {
            DB::statement(
                'UPDATE mesas_paz_asistencias mpa INNER JOIN municipios m ON m.id = mpa.municipio_id SET mpa.microrregion_id = m.microrregion_id WHERE mpa.municipio_id IS NOT NULL'
            );
        }

        $this->writeMarkdownReport(
            $plan,
            $microIdsByCode,
            $missingMunicipios,
            $missingDelegados,
            $updatedMunicipios,
            $updatedDelegados,
            $createdDelegados,
            $createdUsers,
            $updatedUsers,
            $activationStats,
            $assignments
        );
    }

    private function writeMarkdownReport(
        array $plan,
        array $microIdsByCode,
        array $missingMunicipios,
        array $missingDelegados,
        int $updatedMunicipios,
        int $updatedDelegados,
        int $createdDelegados,
        int $createdUsers,
        int $updatedUsers,
        array $activationStats,
        array $assignments
    ): void
    {
        $lines = [];
        $lines[] = '# Reasignación de microrregiones, municipios y delegados';
        $lines[] = '';
        $lines[] = '- Fecha: '.now()->format('Y-m-d H:i:s');
        $lines[] = '- Microrregiones en plan: '.count($plan);
        $lines[] = '- Municipios actualizados en BD: '.$updatedMunicipios;
        $lines[] = '- Delegados actualizados en BD: '.$updatedDelegados;
        $lines[] = '- Delegados creados en BD: '.$createdDelegados;
        $lines[] = '- Usuarios creados en BD: '.$createdUsers;
        $lines[] = '- Usuarios actualizados en BD: '.$updatedUsers;
        $lines[] = '- Área aplicada a usuarios: 8';
        $lines[] = '- Cargo aplicado a usuarios: 250';
        $lines[] = '- Usuarios desactivados (`activo=0`): '.($activationStats['deactivated'] ?? 0);
        $lines[] = '- Usuarios activados (`activo=1`): '.($activationStats['activated'] ?? 0);
        $lines[] = '- Total usuarios permitidos activos: '.($activationStats['allowlist_total'] ?? 0);
        $lines[] = '';
        $lines[] = '## Relación microrregión → delegado → municipios';
        $lines[] = '';

        $assignmentByCode = [];
        foreach ($assignments as $assignment) {
            $assignmentByCode[$assignment['codigo']] = $assignment;
        }

        foreach ($plan as $item) {
            $codigo = $item['codigo'];
            $microId = $microIdsByCode[$codigo] ?? null;
            $delegado = $item['delegado'];

            $lines[] = '### '.$codigo.' '.$item['cabecera'].' (ID BD: '.($microId ?: 'N/D').')';
            $lines[] = '';
            $lines[] = '- Delegado: '.$delegado['nombre'];
            $lines[] = '- Teléfono: '.$delegado['telefono'];
            $lines[] = '- Delegado ID BD: '.($assignmentByCode[$codigo]['delegado_id'] ?? 'N/D');
            $lines[] = '- User ID BD: '.($assignmentByCode[$codigo]['user_id'] ?? 'N/D');
            $lines[] = '- User email: '.($assignmentByCode[$codigo]['user_email'] ?? 'N/D');
            $lines[] = '- Municipios ('.count($item['municipios']).'):';

            foreach ($item['municipios'] as $municipio) {
                $lines[] = '  - '.$municipio;
            }

            $lines[] = '';
        }

        $lines[] = '## Validación de coincidencias';
        $lines[] = '';

        if (empty($missingMunicipios)) {
            $lines[] = '- Municipios sin coincidencia: 0';
        } else {
            $lines[] = '- Municipios sin coincidencia: '.count($missingMunicipios);
            foreach ($missingMunicipios as $item) {
                $lines[] = '  - MR '.$item['codigo'].' '.$item['cabecera'].': '.$item['municipio'];
            }
        }

        $lines[] = '';

        if (empty($missingDelegados)) {
            $lines[] = '- Delegados sin coincidencia: 0';
        } else {
            $lines[] = '- Delegados sin coincidencia: '.count($missingDelegados);
            foreach ($missingDelegados as $item) {
                $lines[] = '  - MR '.$item['codigo'].' '.$item['cabecera'].': '.$item['delegado'].' ('.$item['telefono'].')';
            }
        }

        $lines[] = '';

        file_put_contents(base_path('MICRORREGIONES_REASIGNACION.md'), implode(PHP_EOL, $lines).PHP_EOL);
    }

    private function ensureMunicipiosEspeciales(Carbon $fecha): void
    {
        $reglas = [
            [
                'municipio' => 'PUEBLA',
                'microrregiones' => ['09', '10', '11', '16', '17', '19'],
            ],
            [
                'municipio' => 'CUAUTLANCINGO',
                'microrregiones' => ['09'],
            ],
        ];

        foreach ($reglas as $regla) {
            $municipio = (string) $regla['municipio'];

            $source = DB::table('municipios')
                ->whereRaw('UPPER(municipio) = ?', [Str::upper($municipio)])
                ->orderBy('id')
                ->first();

            if (!$source) {
                continue;
            }

            foreach ($regla['microrregiones'] as $codigoMr) {
                $microId = DB::table('microrregiones')
                    ->where('microrregion', $codigoMr)
                    ->value('id');

                if (!$microId) {
                    continue;
                }

                $exists = DB::table('municipios')
                    ->where('microrregion_id', (int) $microId)
                    ->whereRaw('UPPER(municipio) = ?', [Str::upper($municipio)])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('municipios')->insert([
                    'microrregion_id' => (int) $microId,
                    'cve_ine' => $source->cve_ine ?? '000',
                    'cve_inegi' => $source->cve_inegi,
                    'cve_edo' => $source->cve_edo ?? '21',
                    'municipio' => $municipio,
                    'df' => $source->df,
                    'dl' => $source->dl,
                    'dj' => $source->dj,
                    'region' => $source->region,
                    'micro_region' => $source->micro_region,
                    'www' => $source->www,
                    'presidencia_domicilio' => $source->presidencia_domicilio,
                    'presidencia_telefono' => $source->presidencia_telefono,
                    'presidente_nombre' => $source->presidente_nombre,
                    'presidente_ap_paterno' => $source->presidente_ap_paterno,
                    'presidente_ap_materno' => $source->presidente_ap_materno,
                    'foto_presidente' => $source->foto_presidente,
                    'telefono_presidente' => $source->telefono_presidente,
                    'email_presidente' => $source->email_presidente,
                    'filiacion' => $source->filiacion,
                    'glifo' => $source->glifo,
                    'toponimia' => $source->toponimia,
                    'agenero' => $source->agenero,
                    'prioridad' => $source->prioridad,
                    'secciones' => $source->secciones,
                    'padron' => $source->padron,
                    'padron_h' => $source->padron_h,
                    'padron_m' => $source->padron_m,
                    'ln' => $source->ln,
                    'ln_h' => $source->ln_h,
                    'ln_m' => $source->ln_m,
                    'metapj' => $source->metapj,
                    'created_at' => $fecha,
                    'updated_at' => $fecha,
                ]);
            }
        }
    }

    private function resolveMunicipioId(string $municipio, array $municipiosByNorm, array $municipiosDb): ?int
    {
        $norm = $this->normalize($municipio);
        if (isset($municipiosByNorm[$norm][0])) {
            return (int) $municipiosByNorm[$norm][0];
        }

        foreach ($this->municipioAliases($norm) as $alias) {
            if (isset($municipiosByNorm[$alias][0])) {
                return (int) $municipiosByNorm[$alias][0];
            }
        }

        $matches = [];
        foreach ($municipiosDb as $row) {
            if (str_contains($row['norm'], $norm) || str_contains($norm, $row['norm'])) {
                $matches[] = (int) $row['id'];
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    private function resolveDelegadoId(string $nombreCompleto, string $telefono, array $delegadosDb): ?int
    {
        $nameNorm = $this->normalize($nombreCompleto);
        $phone = $this->digits($telefono);

        $sameName = array_values(array_filter($delegadosDb, function ($row) use ($nameNorm) {
            return $row['full_norm'] === $nameNorm;
        }));

        foreach ($sameName as $row) {
            if ($this->phoneLooksSame($row['phone_digits'], $phone)) {
                return (int) $row['id'];
            }
        }

        if ($phone === '' && count($sameName) === 1) {
            return (int) $sameName[0]['id'];
        }

        return null;
    }

    private function phoneLooksSame(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        return str_ends_with($a, $b) || str_ends_with($b, $a);
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function normalize(string $value): string
    {
        $value = Str::upper(Str::ascii($value));
        $value = preg_replace('/[^A-Z0-9 ]+/', ' ', $value) ?: '';
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        return trim($value);
    }

    private function municipioAliases(string $normalized): array
    {
        return match ($normalized) {
            'XOCHIAPUILCO' => ['XOCHIAPULCO'],
            'XOCHITLAN TODOS' => ['XOCHITLAN TODOS SANTOS'],
            'ALBINO ZERTUCHE MORENA' => ['ALBINO ZERTUCHE'],
            'TLACOTEPEC DE BENITO' => ['TLACOTEPEC DE BENITO JUAREZ'],
            'TEPATALXCO DE HIDALGO' => ['TEPATLAXCO DE HIDALGO'],
            'SAN JOSE MIAHUATLAN' => ['SAN JOSE MIAHUATLAN'],
            default => [],
        };
    }

    private function createDelegado(string $nombreCompleto, string $telefono, int $microrregionId, Carbon $fecha): ?int
    {
        $parts = array_values(array_filter(explode(' ', trim($nombreCompleto))));
        if (empty($parts)) {
            return null;
        }

        $apMaterno = '';
        $apPaterno = '';
        $nombre = '';

        if (count($parts) === 1) {
            $nombre = $parts[0];
        } elseif (count($parts) === 2) {
            $nombre = $parts[0];
            $apPaterno = $parts[1];
        } else {
            $apMaterno = array_pop($parts) ?? '';
            $apPaterno = array_pop($parts) ?? '';
            $nombre = implode(' ', $parts);
        }

        return (int) DB::table('delegados')->insertGetId([
            'user_id' => null,
            'nombre' => $nombre,
            'ap_paterno' => $apPaterno,
            'ap_materno' => $apMaterno,
            'microrregion_id' => $microrregionId,
            'dependencia_gob_id' => null,
            'telefono' => $this->digits($telefono),
            'email' => null,
            'foto' => null,
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);
    }

    private function ensureUserForDelegado(int $delegadoId, string $nombreCompleto, string $telefono, int $microrregionId, string $codigo, Carbon $fecha): array
    {
        $delegado = DB::table('delegados')
            ->select('id', 'user_id')
            ->where('id', $delegadoId)
            ->first();

        $existingUserId = $delegado?->user_id ? (int) $delegado->user_id : null;
        $user = $existingUserId
            ? DB::table('users')->where('id', $existingUserId)->first()
            : null;

        $email = $user?->email;
        $phoneDigits = $this->digits($telefono);

        $payload = [
            'name' => $nombreCompleto,
            'telefono' => $phoneDigits,
            'cargo_id' => 250,
            'area_id' => 8,
            'micro_region' => $microrregionId,
            'updated_at' => $fecha,
        ];

        if ($user) {
            if (empty($email)) {
                $email = $this->buildUniqueEmail($nombreCompleto, $codigo, $phoneDigits, null);
                $payload['email'] = $email;
            }

            DB::table('users')
                ->where('id', (int) $user->id)
                ->update($payload);

            DB::table('delegados')
                ->where('id', $delegadoId)
                ->update([
                    'user_id' => (int) $user->id,
                    'microrregion_id' => $microrregionId,
                    'telefono' => $phoneDigits,
                    'email' => $email,
                    'updated_at' => $fecha,
                ]);

            return [
                'created' => 0,
                'updated' => 1,
                'user_id' => (int) $user->id,
                'email' => $email,
            ];
        }

        $email = $this->buildUniqueEmail($nombreCompleto, $codigo, $phoneDigits, null);
        $userId = (int) DB::table('users')->insertGetId([
            'name' => $nombreCompleto,
            'email' => $email,
            'password' => Hash::make('asdf1234'),
            'telefono' => $phoneDigits,
            'cargo_id' => 250,
            'area_id' => 8,
            'micro_region' => $microrregionId,
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);

        DB::table('delegados')
            ->where('id', $delegadoId)
            ->update([
                'user_id' => $userId,
                'microrregion_id' => $microrregionId,
                'telefono' => $phoneDigits,
                'email' => $email,
                'updated_at' => $fecha,
            ]);

        return [
            'created' => 1,
            'updated' => 0,
            'user_id' => $userId,
            'email' => $email,
        ];
    }

    private function buildUniqueEmail(string $nombreCompleto, string $codigo, string $telefono, ?int $exceptUserId): string
    {
        $base = Str::slug($nombreCompleto, '.');
        if ($base === '') {
            $base = 'delegado.mr'.$codigo;
        }

        $suffixPhone = $telefono !== '' ? substr($telefono, -4) : $codigo;
        $candidate = $base.'.mr'.$codigo.'.'.$suffixPhone.'@segob.local';
        $index = 2;

        while (true) {
            $query = DB::table('users')->where('email', $candidate);
            if ($exceptUserId !== null) {
                $query->where('id', '!=', $exceptUserId);
            }

            if (!$query->exists()) {
                return $candidate;
            }

            $candidate = $base.'.mr'.$codigo.'.'.$suffixPhone.'.'.$index.'@segob.local';
            $index++;
        }
    }

    private function applyUserActivationPolicy(array $delegateUserIds, Carbon $fecha): array
    {
        if (!Schema::hasColumn('users', 'activo')) {
            return [
                'deactivated' => 0,
                'activated' => 0,
                'allowlist_total' => 0,
            ];
        }

        $allowlist = collect($delegateUserIds)
            ->filter(fn ($id) => is_int($id) && $id > 0)
            ->values();

        if (Schema::hasTable('temporary_module_entries')) {
            $moduleUserIds = DB::table('temporary_module_entries')
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

            $allowlist = $allowlist->merge($moduleUserIds)->values();
        }

        $allowlist = $allowlist->unique()->values();

        $deactivated = DB::table('users')
            ->where('activo', '!=', 0)
            ->update([
                'activo' => 0,
                'updated_at' => $fecha,
            ]);

        $activated = 0;
        if ($allowlist->isNotEmpty()) {
            $activated = DB::table('users')
                ->whereIn('id', $allowlist->all())
                ->update([
                    'activo' => 1,
                    'updated_at' => $fecha,
                ]);
        }

        return [
            'deactivated' => (int) $deactivated,
            'activated' => (int) $activated,
            'allowlist_total' => $allowlist->count(),
        ];
    }

    private function planMicrorregiones(): array
    {
        return [
            [
                'codigo' => '01',
                'cabecera' => 'XICOTEPEC',
                'delegado' => ['nombre' => 'ALFREDO BARRÓN GARCÍA', 'telefono' => '7761064415'],
                'municipios' => ['XICOTEPEC', 'VENUSTIANO CARRANZA', 'PANTEPEC', 'PAHUATLÁN', 'FRANCISCO Z. MENA', 'TLACUILOTEPEC', 'ZIHUATEUTLA', 'JOPALA', 'JALPAN', 'NAUPAN', 'HONEY', 'TLAPACOYA', 'TLAXCO'],
            ],
            [
                'codigo' => '02',
                'cabecera' => 'HUAUCHINANGO',
                'delegado' => ['nombre' => 'ELID ALVARADO GONZALEZ', 'telefono' => '7761053451'],
                'municipios' => ['HUAUCHINANGO', 'ZACATLÁN', 'TLAOLA', 'CHICONCUAUTLA', 'AHUAZOTEPEC', 'JUAN GALINDO'],
            ],
            [
                'codigo' => '03',
                'cabecera' => 'CHIGNAHUAPAN',
                'delegado' => ['nombre' => 'LUIS ANGEL CARRASCO GASCA', 'telefono' => '7971068184'],
                'municipios' => ['CHIGNAHUAPAN', 'TETELA DE OCAMPO', 'IXTACAMAXTITLÁN', 'ZAUTLA', 'AQUIXTLA', 'XOCHIAPUILCO'],
            ],
            [
                'codigo' => '04',
                'cabecera' => 'ZACAPOAXTLA',
                'delegado' => ['nombre' => 'HEBERT ENRIQUE VAQUERO OLIVARES', 'telefono' => '2225795039'],
                'municipios' => ['ZACAPOAXTLA', 'HUEHUETLA', 'OLINTLA', 'IXTEPEC', 'HUEYTLALPAN', 'JONOTLA', 'CAXHUACAN', 'NAUZONTLA', 'ZOQUIAPAN', 'ATLEQUIZAYAN'],
            ],
            [
                'codigo' => '05',
                'cabecera' => 'LIBRES',
                'delegado' => ['nombre' => 'DAVID GONZALEZ REYES', 'telefono' => '2761005033'],
                'municipios' => ['XIUTETELCO', 'LIBRES', 'TLACHICHUCA', 'CHICHIQUILA', 'QUIMIXTLÁN', 'TEPEYAHUALCO', 'CHILCHOTLA', 'GUADALUPE VICTORIA', 'CUYOACO', 'LAFRAGUA', 'OCOTEPEC'],
            ],
            [
                'codigo' => '06',
                'cabecera' => 'TEZIUTLÁN',
                'delegado' => ['nombre' => 'JOSE MANUEL BELLO MORA', 'telefono' => '2311063527'],
                'municipios' => ['TEZIUTLÁN', 'CHIGNAUTLA', 'ATEMPAN', 'HUEYTAMALCO', 'ACATENO', 'AYOTOXCO DE GUERRERO', 'TENAMPULCO', 'TETELES DE ÁVILA CASTILLO'],
            ],
            [
                'codigo' => '07',
                'cabecera' => 'SAN MARTÍN TEXMELUCAN',
                'delegado' => ['nombre' => 'GERARDO SANCHEZ AGUILAR', 'telefono' => '2224131129'],
                'municipios' => ['SAN MARTÍN TEXMELUCAN', 'TLAHUAPAN', 'SAN SALVADOR EL VERDE', 'SAN MATÍAS TLALANCALECA', 'SAN FELIPE TEOTLALCINGO'],
            ],
            [
                'codigo' => '08',
                'cabecera' => 'HUEJOTZINGO',
                'delegado' => ['nombre' => 'ROBERTO HUERTA PÉREZ', 'telefono' => '2481073326'],
                'municipios' => ['HUEJOTZINGO', 'CORONANGO', 'JUAN C. BONILLA', 'CHIAUTZINGO', 'CALPAN', 'SAN MIGUEL XOXTLA', 'DOMINGO ARENAS', 'TLALTENANGO'],
            ],
            [
                'codigo' => '09',
                'cabecera' => 'PUEBLA',
                'delegado' => ['nombre' => 'STEPHANI PAMELA RODRÍGUEZ PÉREZ', 'telefono' => '2228140338'],
                'municipios' => ['PUEBLA', 'CUAUTLANCINGO'],
            ],
            [
                'codigo' => '10',
                'cabecera' => 'PUEBLA',
                'delegado' => ['nombre' => 'MARIA SOFIA HERNANDEZ SANTOS', 'telefono' => '2212508460'],
                'municipios' => ['PUEBLA'],
            ],
            [
                'codigo' => '11',
                'cabecera' => 'PUEBLA',
                'delegado' => ['nombre' => 'JAIME IBARRA GUZMAN', 'telefono' => '2211051936'],
                'municipios' => ['PUEBLA'],
            ],
            [
                'codigo' => '12',
                'cabecera' => 'AMOZOC',
                'delegado' => ['nombre' => 'MARTHA ALICIA LARA HERNANDEZ', 'telefono' => '2227919270'],
                'municipios' => ['AMOZOC', 'ACAJETE', 'TECALI DE HERRERA', 'TEPATALXCO DE HIDALGO', 'CUAUTINCHAN'],
            ],
            [
                'codigo' => '13',
                'cabecera' => 'TEPEACA',
                'delegado' => ['nombre' => 'JOSE OMAR PORRAS BRETON', 'telefono' => '2451038857'],
                'municipios' => ['TEPEACA', 'SANTO TOMAS HUEYOTLIPAN', 'ATOYATEMPAN', 'TLANEPANTLA', 'MIXTLA'],
            ],
            [
                'codigo' => '14',
                'cabecera' => 'CHALCHICOMULA DE SESMA',
                'delegado' => ['nombre' => 'MARCO ANTONIO SAUCEDO MENDIETA', 'telefono' => '2224236433'],
                'municipios' => ['CHALCHICOMULA DE SESMA', 'SAN SALVADOR EL SECO', 'NOPALUCAN', 'GENERAL FELIPE ÁNGELES', 'RAFAEL LARA GRAJALES', 'ORIENTAL', 'ESPERANZA', 'SOLTEPEC', 'SAN JOSÉ CHIAPA', 'SAN NICOLÁS BUENOS AIRES', 'ATZITZINTLA', 'ALJOJUCA', 'SAN JUAN ATENCO', 'MAZAPILTEPEC DE JUÁREZ'],
            ],
            [
                'codigo' => '15',
                'cabecera' => 'TECAMACHALCO',
                'delegado' => ['nombre' => 'CESAR LOPEZ MENDOZA', 'telefono' => '2491013186'],
                'municipios' => ['TECAMACHALCO', 'QUECHOLAC', 'PALMAR DE BRAVO', 'YEHUALTEPEC', 'SAN SALVADOR HUIXCOLOTLA', 'CAÑADA MORELOS'],
            ],
            [
                'codigo' => '16',
                'cabecera' => 'PUEBLA',
                'delegado' => ['nombre' => 'RAMON MARIO CARVAJAL RAMOS', 'telefono' => '2222651023'],
                'municipios' => ['PUEBLA'],
            ],
            [
                'codigo' => '17',
                'cabecera' => 'PUEBLA',
                'delegado' => ['nombre' => 'VIRGINIA SOTO GOMEZ', 'telefono' => '2491825963'],
                'municipios' => ['PUEBLA'],
            ],
            [
                'codigo' => '18',
                'cabecera' => 'CHOLULA',
                'delegado' => ['nombre' => 'BERENICE PORQUILLO SALGADO', 'telefono' => '2213252952'],
                'municipios' => ['SAN PEDRO CHOLULA', 'SAN ANDRÉS CHOLULA'],
            ],
            [
                'codigo' => '19',
                'cabecera' => 'PUEBLA',
                'delegado' => ['nombre' => 'ARTURO JIMENEZ RODRIGUEZ', 'telefono' => '2221270160'],
                'municipios' => ['PUEBLA'],
            ],
            [
                'codigo' => '20',
                'cabecera' => 'PUEBLA',
                'delegado' => ['nombre' => 'EVA HERNÁNDEZ CRUZ', 'telefono' => '2227516876'],
                'municipios' => ['PUEBLA'],
            ],
            [
                'codigo' => '21',
                'cabecera' => 'ATLIXCO',
                'delegado' => ['nombre' => 'MYRIAM CELINA HERNANDEZ RODRIGUEZ', 'telefono' => '2223782509'],
                'municipios' => ['ATLIXCO', 'OCOYUCAN', 'NEALTICAN', 'TIANGUISMANALCO', 'SANTA ISABEL CHOLULA', 'SAN GREGORIO ATZOMPA', 'SAN JERÓNIMO TECUANIPAN'],
            ],
            [
                'codigo' => '22',
                'cabecera' => 'IZUCAR DE MATAMOROS',
                'delegado' => ['nombre' => 'OMAR ANTONIO ESCAMILLA PLIEGO', 'telefono' => '2431267606'],
                'municipios' => ['IZÚCAR DE MATAMOROS', 'CHIETLA', 'HUAQUECHULA', 'TOCHIMILCO', 'ATZITZIHUACÁN', 'SAN NICOLÁS DE LOS RANCHOS', 'TILAPA', 'TLAPANALÁ', 'TEPEOJUMA', 'TEPEXCO', 'COHUECÁN', 'EPATLÁN', 'ACTEOPAN', 'ATZALA', 'TEPEMAXALCO'],
            ],
            [
                'codigo' => '23',
                'cabecera' => 'ACATLÁN DE OSORIO',
                'delegado' => ['nombre' => 'DANTE RAMIREZ Y MARQUEZ', 'telefono' => '9531092742'],
                'municipios' => ['ACATLÁN', 'TEHUITZINGO', 'TULCINGO', 'PETLALCINGO', 'GUADALUPE', 'TECOMATLÁN', 'PIAXTLA', 'CHILA', 'SAN JERONIMO XAYACATLÁN', 'SAN PABLO ANICANO', 'SAN PEDRO YELOIXTLAHUACA', 'CHINANTLA', 'AHUEHUETITLA', 'XAYACATLÁN DE BRAVO', 'AXUTLA', 'TOTOLTEPEC DE GUERRERO', 'SAN MIGUEL IXITLÁN'],
            ],
            [
                'codigo' => '24',
                'cabecera' => 'TEHUACÁN',
                'delegado' => ['nombre' => 'JUAN HUMBERTO SÁNCHEZ OTAÑEZ', 'telefono' => '2381127280'],
                'municipios' => ['TEHUACÁN', 'TLACOTEPEC DE BENITO', 'TEPANCO DE LÓPEZ', 'CALTEPEC', 'ZAPOTITLÁN', 'IXCAQUIXTLA', 'MOLCAXAC', 'XOCHITLÁN TODOS', 'JUAN N. MÉNDEZ', 'ATEXCAL', 'COYOTEPEC'],
            ],
            [
                'codigo' => '25',
                'cabecera' => 'TEHUACÁN',
                'delegado' => ['nombre' => 'EDUARDO FUENTES CRUZ', 'telefono' => '2382199459'],
                'municipios' => ['MOLCAXAC', 'XOCHITLÁN TODOS', 'JUAN N. MÉNDEZ', 'ATEXCAL', 'COYOTEPEC', 'TEHUACÁN', 'SANTIAGO MIAHUATLÁN', 'CHAPULCO', 'NICOLÁS BRAVO'],
            ],
            [
                'codigo' => '26',
                'cabecera' => 'AJALPAN',
                'delegado' => ['nombre' => 'EDUARDO VAZQUEZ MARQUEZ', 'telefono' => '2381337603'],
                'municipios' => ['AJALPAN', 'VICENTE GUERRERO', 'COXCATLÁN', 'ALTEPEXI', 'ZOQUITLÁN', 'ZINACATEPEC', 'SAN GABRIEL CHILAC', 'ELOXOCHITLÁN', 'SAN JOSE MIAHUATLÁN', 'COYOMEAPAN', 'SAN SEBASTIÁN TLACOTEPEC', 'SAN ANTONIO CAÑADA'],
            ],
            [
                'codigo' => '27',
                'cabecera' => 'CUAUTEMPAN',
                'delegado' => ['nombre' => 'ARMANDO GARRIDO CARRERA', 'telefono' => '7761064415'],
                'municipios' => ['AHUACATLÁN', 'HUITZILAN DE SERDÁN', 'XOCHITLÁN DE VICENTE SUÁREZ', 'TEPETZINTLA', 'CUAUTEMPAN', 'HERMENEGILDO GALEANA', 'ZAPOTITLÁN DE MÉNDEZ', 'AMIXTLÁN', 'ZONGOZOTLA', 'CAMOCUAUTLA', 'TEPANGO DE RODRÍGUEZ', 'SAN FELIPE TEPATLÁN', 'COATEPEC'],
            ],
            [
                'codigo' => '28',
                'cabecera' => 'CHIAUTLA',
                'delegado' => ['nombre' => 'ANTONIO JAVANA GARCIA', 'telefono' => '2223466909'],
                'municipios' => ['CHIAUTLA', 'JOLALPAN', 'HUEHUETLÁN EL CHICO', 'IXCAMILPA DE GUERRERO', 'TEOTLALCO', 'ALBINO ZERTUCHE MORENA', 'CHILA DE LA SAL', 'COHETZALA', 'XICOTLÁN'],
            ],
            [
                'codigo' => '29',
                'cabecera' => 'TEPEXI DE RODRÍGUEZ',
                'delegado' => ['nombre' => 'EDUARDO FUENTES CRUZ', 'telefono' => '2381326616'],
                'municipios' => ['TEPEXI DE RODRÍGUEZ', 'TZICATLACOYAN', 'SANTA INÉS AHUATEMPAN', 'HUEHUETLAN EL GRANDE', 'HUATLATLAUCA', 'ZACAPALA', 'TEOPANTLÁN', 'CUAYUCA DE ANDRADE', 'XOCHILTEPEC', 'COATZINGO', 'AHUATLÁN', 'CHIGMECATITLÁN', 'SAN DIEGO LA MESA TOCHIMILTZINGO', 'SAN JUAN ATZOMPA', 'SANTA CATARINA TLALTEMPAN', 'SAN MARTÍN TOTOLTEPEC', 'LA MAGDALENA TLATLAUQUITEPEC'],
            ],
            [
                'codigo' => '30',
                'cabecera' => 'ACATZINGO',
                'delegado' => ['nombre' => 'DANIEL PAVIA CONDE', 'telefono' => '2222080728'],
                'municipios' => ['ACATZINGO', 'LOS REYES DE JUÁREZ', 'TOCHTEPEC', 'CUAPIAXTLA DE MADERO', 'HUITZILTEPEC', 'TEPEYAHUALCO DE CUAUHTÉMOC'],
            ],
            [
                'codigo' => '31',
                'cabecera' => 'TLATLAUQUITEPEC',
                'delegado' => ['nombre' => 'MARIO ALBERTO CASTRO JIMENEZ', 'telefono' => '2311167330'],
                'municipios' => ['TLATLAUQUITEPEC', 'CUETZALAN DEL PROGRESO', 'ZARAGOZA', 'HUEYAPAN', 'YAONÁHUAC', 'TUZAMAPAN DE GALEANA'],
            ],
        ];
    }
}
