<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Carrera;

class CarreraController extends Controller
{
    public function index()
    {
        $carreras = Carrera::where('estadoA', 1)->get();

        return response()->json([
            'success' => true,
            'data'    => $carreras,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo'      => 'required|string|max:20|unique:carrera,codigo',
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'estado'      => 'boolean',
            'idModalidad' => 'nullable|exists:modalidad,id',
        ]);

        $validated['usuarioA'] = $request->user()->id;

        $carrera = Carrera::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Carrera creada correctamente',
            'data'    => $carrera,
        ], 201);
    }

    public function show($id)
    {
        $carrera = Carrera::where('estadoA', 1)->find($id);

        if (! $carrera) {
            return response()->json([
                'success' => false,
                'message' => 'Carrera no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $carrera,
        ]);
    }

    public function update(Request $request, $id)
    {
        $carrera = Carrera::where('estadoA', 1)->find($id);

        if (! $carrera) {
            return response()->json([
                'success' => false,
                'message' => 'Carrera no encontrada',
            ], 404);
        }

        $validated = $request->validate([
            'codigo'      => 'sometimes|string|max:20|unique:carrera,codigo,' . $id . ',id',
            'nombre'      => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'estado'      => 'boolean',
            'idModalidad' => 'sometimes|nullable|exists:modalidad,id',
        ]);

        $carrera->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Carrera actualizada correctamente',
            'data'    => $carrera,
        ]);
    }

    public function destroy($id)
    {
        $carrera = Carrera::where('estadoA', 1)->find($id);

        if (! $carrera) {
            return response()->json([
                'success' => false,
                'message' => 'Carrera no encontrada',
            ], 404);
        }

        $carrera->update(['estadoA' => 0]);

        return response()->json([
            'success' => true,
            'message' => 'Carrera eliminada correctamente',
        ]);
    }

    public function pensumActivo($id)
{
    $carrera = Carrera::where('estadoA', 1)->find($id);
    if (!$carrera) return response()->json(['success' => false, 'message' => 'Carrera no encontrada'], 404);

    $pensum = \App\Models\Pensum::with([
        'materias' => function ($q) {
            $q->where('estadoA', 1)->with('prerrequisito')->orderBy('semestre');
        }
    ])->where('idCarrera', $id)->where('estadoA', 1)->latest('anioCreacion')->first();

    if (!$pensum) return response()->json(['success' => false, 'message' => 'No hay pensum activo para esta carrera'], 404);

    return response()->json(['success' => true, 'data' => $pensum]);
}
}
