<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Materia;

class MateriaController extends Controller
{
    public function index()
    {
        $materias = Materia::with(['prerrequisito', 'pensum.carrera'])->where('estadoA', 1)->get();

        return response()->json([
            'success' => true,
            'data'    => $materias,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo'        => 'required|string|max:20',
            'nombre'        => 'required|string|max:100',
            'creditos'      => 'required|integer|min:1',
            'descripcion'   => 'nullable|string',
            'semestre'      => 'nullable|integer|min:1|max:12',
            'idPrerequisito'=> 'nullable|exists:materia,id',
            'idPensum'      => 'required|exists:pensum,id',
            'estado'        => 'boolean',
        ]);

        $existe = Materia::where('codigo', $validated['codigo'])
                         ->where('idPensum', $validated['idPensum'])
                         ->where('estadoA', 1)
                         ->exists();
        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una materia con ese código en este pensum',
            ], 422);
        }

        if ($validated['idPrerequisito'] ?? null) {
            $prereq = Materia::where('id', $validated['idPrerequisito'])
                             ->where('idPensum', $validated['idPensum'])
                             ->where('estadoA', 1)
                             ->exists();
            if (! $prereq) {
                return response()->json([
                    'success' => false,
                    'message' => 'El prerrequisito debe pertenecer al mismo pensum',
                ], 422);
            }
        }

        $validated['usuarioA'] = $request->user()->id;

        $materia = Materia::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Materia creada correctamente',
            'data'    => $materia->load('prerrequisito'),
        ], 201);
    }

    public function show($id)
    {
        $materia = Materia::with('prerrequisito')->where('estadoA', 1)->find($id);

        if (! $materia) {
            return response()->json([
                'success' => false,
                'message' => 'Materia no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $materia,
        ]);
    }

    public function update(Request $request, $id)
    {
        $materia = Materia::where('estadoA', 1)->find($id);

        if (! $materia) {
            return response()->json([
                'success' => false,
                'message' => 'Materia no encontrada',
            ], 404);
        }

        $validated = $request->validate([
            'codigo'        => 'sometimes|string|max:20',
            'nombre'        => 'sometimes|string|max:100',
            'creditos'      => 'sometimes|integer|min:1',
            'descripcion'   => 'nullable|string',
            'semestre'      => 'nullable|integer|min:1|max:12',
            'idPrerequisito'=> 'nullable|exists:materia,id',
            'idPensum'      => 'sometimes|exists:pensum,id',
            'estado'        => 'boolean',
        ]);

        $codigo = $validated['codigo'] ?? $materia->codigo;
        $idPensum = $validated['idPensum'] ?? $materia->idPensum;

        $existe = Materia::where('codigo', $codigo)
                         ->where('idPensum', $idPensum)
                         ->where('estadoA', 1)
                         ->where('id', '!=', $id)
                         ->exists();
        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe otra materia con ese código en este pensum',
            ], 422);
        }

        if (isset($validated['idPrerequisito'])) {
            $prereq = Materia::where('id', $validated['idPrerequisito'])
                             ->where('idPensum', $idPensum)
                             ->where('estadoA', 1)
                             ->exists();
            if (! $prereq) {
                return response()->json([
                    'success' => false,
                    'message' => 'El prerrequisito debe pertenecer al mismo pensum',
                ], 422);
            }
        }

        $materia->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Materia actualizada correctamente',
            'data'    => $materia->load('prerrequisito'),
        ]);
    }

    public function destroy($id)
    {
        $materia = Materia::where('estadoA', 1)->find($id);

        if (! $materia) {
            return response()->json([
                'success' => false,
                'message' => 'Materia no encontrada',
            ], 404);
        }

        $materia->update(['estadoA' => 0]);

        return response()->json([
            'success' => true,
            'message' => 'Materia eliminada correctamente',
        ]);
    }
}
