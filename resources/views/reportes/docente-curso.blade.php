<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Curso</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; color: #1e293b; line-height: 1.4; padding: 25px 30px; }
        .header { border-top: 4px solid #0369a1; margin-bottom: 20px; padding-top: 15px; }
        h1 { font-size: 22px; font-weight: bold; color: #0f172a; margin-bottom: 4px; }
        .subtitle { font-size: 12px; color: #64748b; margin-bottom: 18px; }
        .info-grid { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .info-item { flex: 1; min-width: 140px; background: #f8fafc; padding: 10px 14px; border-radius: 6px; border-left: 3px solid #0369a1; }
        .info-label { font-size: 10px; color: #94a3b8; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .info-value { font-size: 13px; color: #1e293b; font-weight: 500; }
        .stats { display: flex; gap: 15px; margin: 18px 0; }
        .stat { flex: 1; text-align: center; padding: 12px; border-radius: 8px; }
        .stat-green { background: #d1fae5; color: #059669; }
        .stat-red { background: #fee2e2; color: #dc2626; }
        .stat-amber { background: #fef3c7; color: #d97706; }
        .stat-val { font-size: 24px; font-weight: 800; }
        .stat-label { font-size: 10px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        thead { background: #0369a1; color: white; }
        th { padding: 10px 12px; text-align: left; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 8px 12px; font-size: 12px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        .estado { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .estado-aprobado { background: #d1fae5; color: #059669; }
        .estado-reprobado { background: #fee2e2; color: #dc2626; }
        .estado-cursando { background: #fef3c7; color: #d97706; }
        .footer { margin-top: 22px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #64748b; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Calificaciones</h1>
        <p class="subtitle">Documento generado el {{ $fecha }}</p>
    </div>

    <div class="info-grid">
        <div class="info-item"><div class="info-label">Materia</div><div class="info-value">{{ $curso['materia'] }} - Grupo {{ $curso['codigoGrupo'] }}</div></div>
        <div class="info-item"><div class="info-label">Docente</div><div class="info-value">{{ $curso['docente'] }}</div></div>
        <div class="info-item"><div class="info-label">Periodo</div><div class="info-value">{{ $curso['periodo'] }}</div></div>
        <div class="info-item"><div class="info-label">Carrera</div><div class="info-value">{{ $curso['carrera'] }}</div></div>
    </div>

    @php
        $aprobados = collect($estudiantes)->where('estado', 'Aprobado')->count();
        $reprobados = collect($estudiantes)->where('estado', 'Reprobado')->count();
        $cursando = collect($estudiantes)->where('estado', 'Cursando')->count();
        $promedio = collect($estudiantes)->whereNotNull('notaFinal')->avg('notaFinal');
    @endphp

    <div class="stats">
        <div class="stat stat-green"><div class="stat-val">{{ $aprobados }}</div><div class="stat-label">Aprobados</div></div>
        <div class="stat stat-red"><div class="stat-val">{{ $reprobados }}</div><div class="stat-label">Reprobados</div></div>
        <div class="stat stat-amber"><div class="stat-val">{{ $cursando }}</div><div class="stat-label">Cursando</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:18%">Matrícula</th>
                <th style="width:38%">Estudiante</th>
                <th style="width:14%;text-align:center">Nota</th>
                <th style="width:18%;text-align:center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($estudiantes as $e)
            <tr>
                <td>{{ $e['matricula'] ?? '—' }}</td>
                <td>{{ $e['estudiante'] }}</td>
                <td style="text-align:center;font-weight:700">
                    {{ $e['notaFinal'] !== null ? number_format($e['notaFinal'], 1) : '—' }}
                </td>
                <td style="text-align:center">
                    <span class="estado estado-{{ $e['estado'] === 'Aprobado' ? 'aprobado' : ($e['estado'] === 'Reprobado' ? 'reprobado' : 'cursando') }}">
                        {{ $e['estado'] }}
                    </span>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px">No hay estudiantes registrados</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span><strong>Promedio General:</strong> {{ $promedio ? number_format($promedio, 1) : '—' }}</span>
        <span><strong>Total estudiantes:</strong> {{ count($estudiantes) }}</span>
    </div>
</body>
</html>
