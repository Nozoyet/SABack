<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ReporteService;

class ReporteController extends Controller
{
    protected $reporteService;

    public function __construct(ReporteService $reporteService)
    {
        $this->reporteService = $reporteService;
    }

    public function carreras()
    {
        try {
            $carreras = $this->reporteService->obtenerTodasLasCarreras();
            return response()->json($carreras);
        } catch (\Exception $e) {
            \Log::error('Error en carreras: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function materiasXCarrera(Request $request)
    {
        try {
            $carreraId = $request->query('carrera_id');
            $semestre = $request->query('semestre');

            if (!$carreraId) {
                return response()->json(['error' => 'carrera_id es requerido'], 400);
            }

            $materias = $this->reporteService->obtenerMateriasPorCarrera($carreraId, $semestre);
            return response()->json($materias);
        } catch (\Exception $e) {
            \Log::error('Error en materias: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function gestiones(Request $request)
    {
        try {
            $carreraId = $request->query('carrera_id');
            if (!$carreraId) {
                return response()->json(['error' => 'carrera_id es requerido'], 400);
            }
            $gestiones = $this->reporteService->obtenerGestionesPorCarrera($carreraId);
            return response()->json($gestiones);
        } catch (\Exception $e) {
            \Log::error('Error en gestiones: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exportPdf($carreraId)
    {
        try {
            $pdf = $this->reporteService->generarPdf($carreraId);
            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="reporte-materias.pdf"');
        } catch (\Exception $e) {
            \Log::error('Error en PDF: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exportExcel($carreraId)
    {
        try {
            return $this->reporteService->generarExcel($carreraId);
        } catch (\Exception $e) {
            \Log::error('Error en Excel: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function periodos(Request $request)
    {
        try {
            $carreraId = $request->query('carrera_id');
            if (!$carreraId) {
                return response()->json(['error' => 'carrera_id es requerido'], 400);
            }
            $periodos = $this->reporteService->obtenerPeriodosPorCarrera($carreraId);
            return response()->json($periodos);
        } catch (\Exception $e) {
            \Log::error('Error en periodos: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function cursosPorPeriodo(Request $request)
    {
        try {
            $periodoId = $request->query('periodo_id');
            if (!$periodoId) {
                return response()->json(['error' => 'periodo_id es requerido'], 400);
            }
            $cursos = $this->reporteService->obtenerCursosPorPeriodo($periodoId);
            return response()->json($cursos);
        } catch (\Exception $e) {
            \Log::error('Error en cursos: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reporteEstudiantes(Request $request)
    {
        try {
            $data = $this->reporteService->obtenerReporteEstudiantes($request);
            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('Error en reporte estudiantes: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reporteDocentes(Request $request)
    {
        try {
            $data = $this->reporteService->obtenerReporteDocentes($request);
            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('Error en reporte docentes: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exportPdfEstudiantes(Request $request)
    {
        try {
            $pdf = $this->reporteService->generarPdfEstudiantes($request);
            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="reporte-estudiantes.pdf"');
        } catch (\Exception $e) {
            \Log::error('Error en PDF estudiantes: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exportExcelEstudiantes(Request $request)
    {
        try {
            return $this->reporteService->generarExcelEstudiantes($request);
        } catch (\Exception $e) {
            \Log::error('Error en Excel estudiantes: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exportPdfDocentes(Request $request)
    {
        try {
            $pdf = $this->reporteService->generarPdfDocentes($request);
            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="reporte-docentes.pdf"');
        } catch (\Exception $e) {
            \Log::error('Error en PDF docentes: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exportExcelDocentes(Request $request)
    {
        try {
            return $this->reporteService->generarExcelDocentes($request);
        } catch (\Exception $e) {
            \Log::error('Error en Excel docentes: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
