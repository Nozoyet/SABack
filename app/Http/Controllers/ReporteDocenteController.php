<?php

namespace App\Http\Controllers;

use App\Services\ReporteDocenteService;
use App\Models\PeriodoAcademico;
use App\Models\Materia;
use App\Models\Curso;
use Illuminate\Http\Request;

class ReporteDocenteController extends Controller
{
    protected $reporteService;

    private $tipoMap = [
        'periodo-academico' => 'periodo_academico',
        'estudiantes' => 'estudiantes',
        'materias' => 'materias',
        'cursos' => 'cursos',
        'calificaciones' => 'calificaciones',
    ];

    public function __construct(ReporteDocenteService $reporteService)
    {
        $this->reporteService = $reporteService;
    }

    public function filtrosReportes(Request $request)
    {
        $docente = $request->user();

        $periodos = PeriodoAcademico::whereHas('cursos', function ($q) use ($docente) {
            $q->where('idDocente', $docente->id)->where('estadoA', 1);
        })
        ->where('estadoA', 1)
        ->select('id', 'codigo')
        ->orderByDesc('codigo')
        ->get();

        $materias = Materia::whereHas('cursos', function ($q) use ($docente) {
            $q->where('idDocente', $docente->id)->where('estadoA', 1);
        })
        ->where('estadoA', 1)
        ->select('id', 'codigo', 'nombre', 'semestre')
        ->orderBy('nombre')
        ->get();

        $cursos = Curso::with(['materia', 'periodoAcademico'])
            ->where('idDocente', $docente->id)
            ->where('estadoA', 1)
            ->select('id', 'codigoGrupo', 'idMateria', 'idPeriodoAcademico')
            ->orderBy('id')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'codigoGrupo' => $c->codigoGrupo,
                    'idMateria' => $c->idMateria,
                    'idPeriodoAcademico' => $c->idPeriodoAcademico,
                    'materia' => $c->materia?->nombre,
                    'periodo' => $c->periodoAcademico?->codigo,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'periodos' => $periodos,
                'materias' => $materias,
                'cursos' => $cursos,
            ]
        ]);
    }

    public function preview(Request $request, $tipo)
    {
        $internalTipo = $this->tipoMap[$tipo] ?? null;
        if (!$internalTipo) {
            return response()->json(['error' => 'Tipo de reporte inválido'], 400);
        }

        $request->merge(['tipo' => $internalTipo]);

        $request->validate([
            'tipo' => 'required|in:periodo_academico,estudiantes,materias,cursos,calificaciones',
            'periodo_id' => 'nullable|integer',
            'materia_id' => 'nullable|integer',
            'curso_id' => 'nullable|integer',
            'estudiante_id' => 'nullable|integer',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'estado' => 'nullable|string',
            'nombre' => 'nullable|string',
            'carrera_id' => 'nullable|integer',
            'semestre' => 'nullable|integer',
            'turno' => 'nullable|string',
            'nota_min' => 'nullable|numeric',
            'nota_max' => 'nullable|numeric',
            'condicion' => 'nullable|string',
        ]);

        $data = $this->reporteService->generarPreview(
            $request->user(),
            $request->all()
        );

        return response()->json([
            'message' => 'Previsualización generada correctamente',
            'data' => $data
        ]);
    }

    public function exportarPdf(Request $request, $tipo)
    {
        $internalTipo = $this->tipoMap[$tipo] ?? null;
        if (!$internalTipo) {
            return response()->json(['error' => 'Tipo de reporte inválido'], 400);
        }

        $request->merge(['tipo' => $internalTipo]);

        return $this->reporteService->exportarPdf(
            $request->user(),
            $request->all()
        );
    }

    public function exportarExcel(Request $request, $tipo)
    {
        $internalTipo = $this->tipoMap[$tipo] ?? null;
        if (!$internalTipo) {
            return response()->json(['error' => 'Tipo de reporte inválido'], 400);
        }

        $request->merge(['tipo' => $internalTipo]);

        return $this->reporteService->exportarExcel(
            $request->user(),
            $request->all()
        );
    }
}
