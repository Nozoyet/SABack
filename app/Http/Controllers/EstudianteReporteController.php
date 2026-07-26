<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Estudiante;
use App\Models\HistorialAcademico;
use App\Models\Inscripcion;
use App\Models\Curso;
use Barryvdh\DomPDF\Facade\Pdf;

class EstudianteReporteController extends Controller
{
    public function reporte(Request $request)
    {
        $usuario = $request->user();
        $estudiante = Estudiante::with('carrera')->where('idUsuario', $usuario->id)->where('estadoA', 1)->first();
        if (!$estudiante) {
            return response()->json(['success' => false, 'message' => 'Perfil de estudiante no encontrado'], 404);
        }

        $historial = HistorialAcademico::with([
            'materia',
            'periodoAcademico.carrera',
            'inscripcion.curso.docente',
            'inscripcion.curso.horarios',
        ])
            ->where('idEstudiante', $usuario->id)
            ->where('estadoA', 1)
            ->orderByDesc('idPeriodoAcademico')
            ->get()
            ->map(function ($h) {
                return [
                    'id'          => $h->id,
                    'materia'     => $h->materia ? [
                        'id'       => $h->materia->id,
                        'codigo'   => $h->materia->codigo,
                        'nombre'   => $h->materia->nombre,
                        'creditos' => $h->materia->creditos,
                        'semestre' => $h->materia->semestre ?? null,
                    ] : null,
                    'periodo'     => $h->periodoAcademico ? [
                        'codigo' => $h->periodoAcademico->codigo,
                    ] : null,
                    'notaFinal'   => $h->notaFinal,
                    'estado'      => $h->estado,
                    'docente'     => $h->inscripcion?->curso?->docente
                        ? trim("{$h->inscripcion->curso->docente->nombre1} {$h->inscripcion->curso->docente->apellidoP}")
                        : null,
                ];
            });

        $inscripcionesActivas = Inscripcion::with(['curso.materia', 'curso.docente', 'curso.horarios', 'curso.periodoAcademico'])
            ->where('idEstudiante', $usuario->id)
            ->where('estado', 'Activa')
            ->where('estadoA', 1)
            ->get()
            ->map(function ($ins) {
                return [
                    'id'          => $ins->id,
                    'codigoGrupo' => $ins->curso?->codigoGrupo,
                    'materia'     => $ins->curso?->materia?->nombre,
                    'creditos'    => $ins->curso?->materia?->creditos,
                    'docente'     => $ins->curso?->docente
                        ? trim("{$ins->curso->docente->nombre1} {$ins->curso->docente->apellidoP}")
                        : null,
                    'periodo'     => $ins->curso?->periodoAcademico?->codigo,
                ];
            });

        $resumen = [
            'totalMaterias'     => $historial->count(),
            'aprobadas'         => $historial->where('estado', 'Aprobado')->count(),
            'reprobadas'        => $historial->where('estado', 'Reprobado')->count(),
            'creditosAcumulados'=> $historial->where('estado', 'Aprobado')->sum(fn($h) => $h['materia']['creditos'] ?? 0),
            'promedioGeneral'   => $historial->whereNotNull('notaFinal')->avg('notaFinal'),
            'inscripcionesActivas' => $inscripcionesActivas->count(),
        ];

        return response()->json([
            'success' => true,
            'estudiante' => [
                'nombre'    => trim("{$usuario->nombre1} {$usuario->apellidoP}"),
                'email'     => $usuario->email,
                'matricula' => $estudiante->matricula,
                'carrera'   => $estudiante->carrera?->nombre,
            ],
            'resumen'    => $resumen,
            'historial'  => $historial,
            'activas'    => $inscripcionesActivas,
        ]);
    }

    public function exportarPdf(Request $request)
    {
        $usuario = $request->user();
        $estudiante = Estudiante::with('carrera')->where('idUsuario', $usuario->id)->where('estadoA', 1)->first();

        $historial = HistorialAcademico::with(['materia', 'periodoAcademico', 'inscripcion.curso.docente'])
            ->where('idEstudiante', $usuario->id)->where('estadoA', 1)
            ->orderByDesc('idPeriodoAcademico')->get();

        $nombreEstudiante = trim("{$usuario->nombre1} {$usuario->apellidoP}");
        $matricula = $estudiante?->matricula;
        $carrera = $estudiante?->carrera?->nombre;
        $fecha = now()->format('d/m/Y H:i');

        $totalAprobadas = $historial->where('estado', 'Aprobado')->count();
        $totalReprobadas = $historial->where('estado', 'Reprobado')->count();
        $creditos = $historial->where('estado', 'Aprobado')->sum(fn($h) => $h->materia?->creditos ?? 0);
        $promedio = $historial->whereNotNull('notaFinal')->avg('notaFinal');

        $filas = '';
        foreach ($historial as $idx => $h) {
            $bg = $idx % 2 === 0 ? '#f8fafc' : '#ffffff';
            $estadoColor = $h->estado === 'Aprobado' ? '#059669' : ($h->estado === 'Reprobado' ? '#dc2626' : '#d97706');
            $filas .= "<tr style='background-color: {$bg}; border-bottom: 1px solid #e2e8f0;'>
                <td style='padding:10px 14px;font-size:13px;'>{$h->materia?->codigo}</td>
                <td style='padding:10px 14px;font-size:13px;'>{$h->materia?->nombre}</td>
                <td style='padding:10px 14px;font-size:13px;text-align:center;'>{$h->materia?->creditos}</td>
                <td style='padding:10px 14px;font-size:13px;text-align:center;'>" . number_format($h->notaFinal, 1) . "</td>
                <td style='padding:10px 14px;font-size:13px;text-align:center;color:{$estadoColor};font-weight:600;'>{$h->estado}</td>
                <td style='padding:10px 14px;font-size:13px;'>{$h->periodoAcademico?->codigo}</td>
            </tr>";
        }

        $html = "
        <!DOCTYPE html>
        <html><head><meta charset='UTF-8'><style>
            * { margin:0; padding:0; }
            body { font-family: Arial, sans-serif; color: #1e293b; line-height: 1.4; }
            .container { padding: 30px; }
            .header { border-top: 4px solid #0d9488; margin-bottom: 25px; }
            h1 { font-size: 24px; font-weight: bold; margin-bottom: 5px; color: #0f172a; }
            .subtitle { font-size: 13px; color: #64748b; margin-bottom: 20px; }
            .info-grid { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
            .info-item { flex:1; min-width:140px; background:#f8fafc; padding:12px 15px; border-radius:8px; border-left:3px solid #0d9488; }
            .info-label { font-size:11px; color:#94a3b8; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px; }
            .info-value { font-size:14px; color:#1e293b; font-weight:500; }
            table { width:100%; border-collapse: collapse; margin-top: 15px; }
            thead { background: #0d9488; color: white; }
            th { padding: 12px 14px; text-align: left; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
            .footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; }
            .stats { display: flex; gap: 20px; margin: 20px 0; }
            .stat { flex:1; text-align:center; padding:15px; border-radius:10px; }
            .stat-green { background:#d1fae5; color:#059669; }
            .stat-red { background:#fee2e2; color:#dc2626; }
            .stat-blue { background:#e0f2fe; color:#0284c7; }
            .stat-val { font-size: 28px; font-weight: 800; }
            .stat-label { font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-top: 4px; }
        </style></head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Reporte Académico</h1>
                    <p class='subtitle'>Historial completo de calificaciones</p>
                </div>
                <div class='info-grid'>
                    <div class='info-item'><div class='info-label'>Estudiante</div><div class='info-value'>{$nombreEstudiante}</div></div>
                    <div class='info-item'><div class='info-label'>Matrícula</div><div class='info-value'>{$matricula}</div></div>
                    <div class='info-item'><div class='info-label'>Carrera</div><div class='info-value'>{$carrera}</div></div>
                </div>
                <div class='stats'>
                    <div class='stat stat-green'><div class='stat-val'>{$totalAprobadas}</div><div class='stat-label'>Aprobadas</div></div>
                    <div class='stat stat-red'><div class='stat-val'>{$totalReprobadas}</div><div class='stat-label'>Reprobadas</div></div>
                    <div class='stat stat-blue'><div class='stat-val'>{$creditos}</div><div class='stat-label'>Créditos</div></div>
                </div>
                <table>
                    <thead><tr>
                        <th style='width:12%'>Código</th>
                        <th style='width:32%'>Materia</th>
                        <th style='width:10%;text-align:center'>Créd.</th>
                        <th style='width:12%;text-align:center'>Nota</th>
                        <th style='width:14%;text-align:center'>Estado</th>
                        <th style='width:14%'>Periodo</th>
                    </tr></thead>
                    <tbody>{$filas}</tbody>
                </table>
                <div class='footer'>
                    <p><strong>Promedio General:</strong> " . number_format($promedio ?? 0, 1) . "</p>
                    <p><strong>Fecha de generación:</strong> {$fecha}</p>
                </div>
            </div>
        </body></html>";

        $pdf = Pdf::loadHtml($html)->setPaper('a4', 'landscape')
            ->setOption('margin-top', 15)->setOption('margin-bottom', 15)
            ->setOption('margin-left', 15)->setOption('margin-right', 15);

        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="reporte-academico.pdf"');
    }

    public function exportarExcel(Request $request)
    {
        $usuario = $request->user();
        $estudiante = Estudiante::with('carrera')->where('idUsuario', $usuario->id)->where('estadoA', 1)->first();

        $historial = HistorialAcademico::with(['materia', 'periodoAcademico'])
            ->where('idEstudiante', $usuario->id)->where('estadoA', 1)
            ->orderByDesc('idPeriodoAcademico')->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Historial');

        $sheet->setCellValue('A1', 'Reporte Académico');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', "Estudiante: " . trim("{$usuario->nombre1} {$usuario->apellidoP}"));
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A3', "Matrícula: {$estudiante?->matricula} | Carrera: {$estudiante?->carrera?->nombre}");
        $sheet->mergeCells('A3:F3');

        $headers = ['Código', 'Materia', 'Créditos', 'Nota Final', 'Estado', 'Periodo'];
        $sheet->fromArray($headers, null, 'A5');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0d9488']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'e2e8f0']]],
        ];
        for ($col = 'A'; $col <= 'F'; $col++) {
            $sheet->getStyle($col . '5')->applyFromArray($headerStyle);
        }

        $row = 6;
        foreach ($historial as $h) {
            $sheet->setCellValue('A' . $row, $h->materia?->codigo);
            $sheet->setCellValue('B' . $row, $h->materia?->nombre);
            $sheet->setCellValue('C' . $row, $h->materia?->creditos);
            $sheet->setCellValue('D' . $row, $h->notaFinal !== null ? number_format($h->notaFinal, 1) : '—');
            $sheet->setCellValue('E' . $row, $h->estado);
            $sheet->setCellValue('F' . $row, $h->periodoAcademico?->codigo);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(14);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="reporte-academico.xlsx"');
    }
}
