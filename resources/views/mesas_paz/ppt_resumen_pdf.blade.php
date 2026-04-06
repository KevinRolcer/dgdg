<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Vista previa PDF - Mesas de Paz</title>
    <style>
        @page { margin: 20px 26px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #17202a;
            font-size: 12px;
            margin: 0;
        }
        .header {
            border-bottom: 2px solid #8b1f3a;
            margin-bottom: 14px;
            padding-bottom: 8px;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 3px 0;
            color: #8b1f3a;
        }
        .subtitle {
            margin: 0;
            color: #4b5563;
            font-size: 11px;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 16px;
        }
        .grid td {
            border: 1px solid #d1d5db;
            padding: 10px;
            width: 33.33%;
            vertical-align: top;
        }
        .metric {
            font-size: 30px;
            font-weight: 700;
            line-height: 1.1;
            margin: 0 0 2px;
            color: #111827;
        }
        .label {
            margin: 0;
            font-size: 11px;
            color: #6b7280;
        }
        .notes {
            margin: 0 0 14px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fafafa;
        }
        .notes p {
            margin: 0 0 4px;
        }
        .notes p:last-child { margin-bottom: 0; }
        .chart {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            background: #fff;
        }
        .chart img {
            max-width: 100%;
            max-height: 330px;
        }
        .muted {
            color: #6b7280;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Mesas de Paz y Seguridad</h1>
        <p class="subtitle">{{ (string) ($resumen['texto_semana_analizada'] ?? 'Reporte semanal') }}</p>
    </div>

    <table class="grid" aria-label="Resumen de métricas">
        <tr>
            <td>
                <p class="metric">{{ (int) ($resumen['total_mesas'] ?? 0) }}</p>
                <p class="label">Mesas de seguridad</p>
            </td>
            <td>
                <p class="metric">{{ (int) ($resumen['mesas_con_asistencia'] ?? 0) }}</p>
                <p class="label">Asistencias</p>
            </td>
            <td>
                <p class="metric">{{ (int) ($resumen['mesas_con_inasistencia'] ?? 0) }}</p>
                <p class="label">Inasistencias</p>
            </td>
        </tr>
    </table>

    <div class="notes">
        <p><strong>Cumplimiento:</strong> {{ number_format((float) ($resumen['porcentaje_cumplimiento'] ?? 0), 2) }}%</p>
        <p><strong>Mesas sin registro semanal:</strong> {{ (int) ($resumen['mesas_sin_registro_semanal'] ?? 0) }}</p>
        <p><strong>Meta esperada:</strong> {{ (int) ($resumen['meta_mesas'] ?? 0) }}</p>
    </div>

    <div class="chart">
        @if(is_string($chartDataUri) && $chartDataUri !== '')
            <img src="{{ $chartDataUri }}" alt="Grafica de asistencia">
        @else
            <p class="muted">No fue posible adjuntar la grafica en esta vista previa.</p>
        @endif
    </div>
</body>
</html>
