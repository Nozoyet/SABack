<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PeriodoAcademico;
use App\Models\Carrera;

class PeriodoAcademicoController extends Controller
{
    public function index()
    {
        $periodos = PeriodoAcademico::with('carrera')
            ->where('estadoA', 1)->get();
        return response()->json(['success' => true, 'data' => $periodos]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo'      => 'required|string|max:30',
            'fechaInicio' => 'required|date',
            'fechaFin'    => 'required|date|after:fechaInicio',
            'idCarrera'   => 'required|exists:carrera,id',
            'estado'      => 'boolean',
        ], [
        // Mensajes personalizados
        'required' => 'El campo :attribute es obligatorio.',
        'date'     => 'El campo :attribute debe ser una fecha válida.',
        'after'    => 'La :attribute debe ser una fecha posterior a la :date.',
        'exists'   => 'La carrera seleccionada no es válida.',
    ], [
        // Atributos personalizados (para que no salgan nombres raros de la BD)
        'codigo'      => 'código',
        'fechaInicio' => 'fecha de inicio',
        'fechaFin'    => 'fecha de fin',
        'idCarrera'   => 'carrera',
    ]);

        $existe = PeriodoAcademico::where('codigo', $validated['codigo'])
            ->where('idCarrera', $validated['idCarrera'])
            ->where('estadoA', 1)->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un período con ese código para esta carrera',
            ], 422);
        }

        $validated['usuarioA'] = $request->user()->id;
        $periodo = PeriodoAcademico::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Período académico creado correctamente',
            'data'    => $periodo->load('carrera'),
        ], 201);
    }

    public function show($id)
    {
        $periodo = PeriodoAcademico::with(['carrera', 'cursos.materia', 'cursos.horarios'])
            ->where('estadoA', 1)->find($id);

        if (!$periodo) return response()->json(['success' => false, 'message' => 'No encontrado'], 404);

        return response()->json(['success' => true, 'data' => $periodo]);
    }
}