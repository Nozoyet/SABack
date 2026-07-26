<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\HistorialAcademico;
use App\Models\Estudiante;
use App\Models\PeriodoAcademico;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class DocenteController extends Controller
{
    public function cursos(Request $request)
    {
        $docente = $request->user();

        $cursos = Curso::with(['materia', 'periodoAcademico.carrera', 'horarios'])
            ->where('idDocente', $docente->id)
            ->where('estadoA', 1)
            ->orderByDesc('id')
            ->get()
            ->map(function ($c) {
                return [
                    'id'            => $c->id,
                    'codigoGrupo'   => $c->codigoGrupo,
                    'cupoMaximo'    => $c->cupoMaximo,
                    'cupoActual'    => $c->cupoActual,
                    'materia'       => $c->materia ? [
                        'id'       => $c->materia->id,
                        'codigo'   => $c->materia->codigo,
                        'nombre'   => $c->materia->nombre,
                        'creditos' => $c->materia->creditos,
                        'semestre' => $c->materia->semestre ?? null,
                    ] : null,
                    'periodo' => $c->periodoAcademico ? [
                        'id'     => $c->periodoAcademico->id,
                        'codigo' => $c->periodoAcademico->codigo,
                        'carrera'=> $c->periodoAcademico->carrera?->nombre,
                    ] : null,
                    'horarios' => $c->horarios->map(fn($h) => [
                        'diaSemana'  => $h->diaSemana,
                        'horaInicio' => substr($h->horaInicio, 0, 5),
                        'horaFin'    => substr($h->horaFin, 0, 5),
                        'aula'       => $h->aula,
                    ]),
                ];
            });

        return response()->json(['success' => true, 'data' => $cursos]);
    }

    public function estudiantesPorCurso($cursoId)
{
    $curso = Curso::with('materia')->where('id', $cursoId)->where('estadoA', 1)->first();
    if (!$curso) {
        return response()->json(['success' => false, 'message' => 'Curso no encontrado'], 404);
    }

    $inscripciones = Inscripcion::with([
        'curso.materia',
        'curso.docente',
    ])
        ->where('idCurso', $cursoId)
        ->where('estadoA', 1)
        // CORRECCIÓN: Ahora acepta tanto alumnos cursando como alumnos con notas ya guardadas
        ->whereIn('estado', ['Activa', 'Completada']) 
        ->get()
        ->map(function ($ins) {
            $est = Estudiante::with('carrera')->where('idUsuario', $ins->idEstudiante)->first();
            $usuario = \App\Models\Usuario::find($ins->idEstudiante);

            $historial = HistorialAcademico::where('idEstudiante', $ins->idEstudiante)
                ->where('idInscripcion', $ins->id)
                ->where('estadoA', 1)
                ->first();

            return [
                'idInscripcion' => $ins->id,
                'idEstudiante'  => $ins->idEstudiante,
                'matricula'     => $est?->matricula,
                'nombre'        => $usuario ? trim("{$usuario->nombre1} {$usuario->apellidoP}") : '—',
                'email'         => $usuario?->email,
                'carrera'       => $est?->carrera?->nombre,
                'notaFinal'     => $historial?->notaFinal,
                'estadoNota'    => $historial?->estado,
                'fechaInscripcion' => $ins->fechaInscripcion,
            ];
        });

    return response()->json([
        'success' => true,
        'curso'   => [
            'id'          => $curso->id,
            'codigoGrupo' => $curso->codigoGrupo,
            'materia'     => $curso->materia?->nombre,
            'cupoActual'  => $curso->cupoActual,
            'cupoMaximo'  => $curso->cupoMaximo,
        ],
        'data' => $inscripciones,
    ]);
}

    public function guardarCalificaciones(Request $request, $cursoId)
    {
        $validated = $request->validate([
            'calificaciones'            => 'required|array|min:1',
            'calificaciones.*.idInscripcion' => 'required|exists:inscripcion,id',
            'calificaciones.*.notaFinal'     => 'required|numeric|min:0|max:100',
        ]);

        $curso = Curso::with(['materia', 'docente'])->where('id', $cursoId)->where('estadoA', 1)->first();
        if (!$curso) {
            return response()->json(['success' => false, 'message' => 'Curso no encontrado'], 404);
        }

        DB::beginTransaction();
        try {
            foreach ($validated['calificaciones'] as $cal) {
                $inscripcion = Inscripcion::where('id', $cal['idInscripcion'])
                    ->where('idCurso', $cursoId)
                    ->where('estadoA', 1)
                    ->first();

                if (!$inscripcion) continue;

                $nota = (float) $cal['notaFinal'];
                $estadoNota = $nota >= 51 ? 'Aprobado' : 'Reprobado';

                $inscripcion->update([
                    'notaFinal' => $nota,
                    'estado'    => 'Completada',
                ]);

                HistorialAcademico::updateOrCreate(
                    [
                        'idEstudiante'    => $inscripcion->idEstudiante,
                        'idMateria'       => $curso->idMateria,
                        'idInscripcion'   => $inscripcion->id,
                    ],
                    [
                        'idPeriodoAcademico' => $curso->idPeriodoAcademico,
                        'notaFinal'          => $nota,
                        'estado'             => $estadoNota,
                        'usuarioA'           => $request->user()->id,
                        'estadoA'            => 1,
                    ]
                );

                $estudiante = Usuario::find($inscripcion->idEstudiante);
                $docenteNombre = $curso->docente
                    ? trim("{$curso->docente->nombre1} {$curso->docente->apellidoP}")
                    : '—';

                Notificacion::create([
                    'idUsuario' => $inscripcion->idEstudiante,
                    'titulo'    => 'Calificación Asignada',
                    'tipo'      => 'calificacion_asignada',
                    'mensaje'   => "Su nota final ha sido asignada. Nota: {$nota}, Materia: {$curso->materia->nombre}, Docente: {$docenteNombre}",
                    'fechaEnvio'=> now(),
                    'estado'    => 'Pendiente',
                    'usuarioA'  => $request->user()->id,
                    'estadoA'   => 1,
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Calificaciones guardadas correctamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function reporteCurso($cursoId)
    {
        $curso = Curso::with(['materia', 'periodoAcademico.carrera', 'docente'])
            ->where('id', $cursoId)->where('estadoA', 1)->first();
        if (!$curso) {
            return response()->json(['success' => false, 'message' => 'Curso no encontrado'], 404);
        }

        $estudiantes = Inscripcion::where('idCurso', $cursoId)
            ->where('estadoA', 1)
            ->get()
            ->map(function ($ins) {
                $est = Estudiante::with('carrera')->where('idUsuario', $ins->idEstudiante)->first();
                $usuario = \App\Models\Usuario::find($ins->idEstudiante);
                $historial = HistorialAcademico::where('idEstudiante', $ins->idEstudiante)
                    ->where('idInscripcion', $ins->id)->where('estadoA', 1)->first();
                return [
                    'matricula'  => $est?->matricula,
                    'estudiante' => $usuario ? trim("{$usuario->nombre1} {$usuario->apellidoP}") : '—',
                    'notaFinal'  => $historial?->notaFinal,
                    'estado'     => $historial?->estado ?? ($ins->estado === 'Activa' ? 'Cursando' : $ins->estado),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'curso' => [
                    'codigoGrupo'   => $curso->codigoGrupo,
                    'materia'       => $curso->materia?->nombre,
                    'docente'       => $curso->docente ? trim("{$curso->docente->nombre1} {$curso->docente->apellidoP}") : '—',
                    'periodo'       => $curso->periodoAcademico?->codigo,
                    'carrera'       => $curso->periodoAcademico?->carrera?->nombre,
                    'cupoActual'    => $curso->cupoActual,
                    'cupoMaximo'    => $curso->cupoMaximo,
                ],
                'estudiantes' => $estudiantes,
                'resumen' => [
                    'inscritos'    => $estudiantes->count(),
                    'aprobados'    => $estudiantes->where('estado', 'Aprobado')->count(),
                    'reprobados'   => $estudiantes->where('estado', 'Reprobado')->count(),
                    'cursando'     => $estudiantes->where('estado', 'Cursando')->count(),
                    'promedio'     => $estudiantes->whereNotNull('notaFinal')->avg('notaFinal'),
                ],
            ],
        ]);
    }

    public function exportarPdfCurso($cursoId)
    {
        $data = $this->obtenerDataReporteCurso($cursoId);

        $html = view('reportes.docente-curso', $data)->render();

        $pdf = Pdf::loadHtml($html)
            ->setPaper('a4', 'landscape')
            ->setOption('margin-top', 15)
            ->setOption('margin-bottom', 15)
            ->setOption('margin-left', 15)
            ->setOption('margin-right', 15);

        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="reporte-curso-' . $cursoId . '.pdf"');
    }

    public function exportarExcelCurso($cursoId)
    {
        $data = $this->obtenerDataReporteCurso($cursoId);
        $curso = $data['curso'];
        $estudiantes = $data['estudiantes'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Calificaciones');

        $sheet->setCellValue('A1', 'Reporte de Calificaciones');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', "Curso: {$curso['materia']} - Grupo {$curso['codigoGrupo']}");
        $sheet->mergeCells('A2:E2');
        $sheet->setCellValue('A3', "Docente: {$curso['docente']} | Periodo: {$curso['periodo']} | Carrera: {$curso['carrera']}");
        $sheet->mergeCells('A3:E3');

        $headers = ['Matrícula', 'Estudiante', 'Nota Final', 'Estado'];
        $sheet->fromArray($headers, null, 'A5');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0369a1']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'e2e8f0']]],
        ];
        for ($col = 'A'; $col <= 'D'; $col++) {
            $sheet->getStyle($col . '5')->applyFromArray($headerStyle);
        }

        $row = 6;
        foreach ($estudiantes as $e) {
            $sheet->setCellValue('A' . $row, $e['matricula']);
            $sheet->setCellValue('B' . $row, $e['estudiante']);
            $sheet->setCellValue('C' . $row, $e['notaFinal'] ?? '—');
            $sheet->setCellValue('D' . $row, $e['estado']);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(14);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="reporte-curso-' . $cursoId . '.xlsx"');
    }

    private function obtenerDataReporteCurso($cursoId)
    {
        $curso = Curso::with(['materia', 'periodoAcademico.carrera', 'docente'])
            ->where('id', $cursoId)->where('estadoA', 1)->firstOrFail();

        $estudiantes = Inscripcion::where('idCurso', $cursoId)
            ->where('estadoA', 1)
            ->get()
            ->map(function ($ins) {
                $est = Estudiante::with('carrera')->where('idUsuario', $ins->idEstudiante)->first();
                $usuario = \App\Models\Usuario::find($ins->idEstudiante);
                $historial = HistorialAcademico::where('idEstudiante', $ins->idEstudiante)
                    ->where('idInscripcion', $ins->id)->where('estadoA', 1)->first();
                return [
                    'matricula'  => $est?->matricula,
                    'estudiante' => $usuario ? trim("{$usuario->nombre1} {$usuario->apellidoP}") : '—',
                    'notaFinal'  => $historial?->notaFinal,
                    'estado'     => $historial?->estado ?? ($ins->estado === 'Activa' ? 'Cursando' : $ins->estado),
                ];
            });

        return [
            'curso' => [
                'codigoGrupo'   => $curso->codigoGrupo,
                'materia'       => $curso->materia?->nombre,
                'docente'       => $curso->docente ? trim("{$curso->docente->nombre1} {$curso->docente->apellidoP}") : '—',
                'periodo'       => $curso->periodoAcademico?->codigo,
                'carrera'       => $curso->periodoAcademico?->carrera?->nombre,
            ],
            'estudiantes' => $estudiantes,
            'fecha' => now()->format('d/m/Y H:i'),
        ];
    }
}
