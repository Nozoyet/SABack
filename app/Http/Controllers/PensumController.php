<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pensum;
use App\Models\Materia;

class PensumController extends Controller
{
    public function index()
    {
        $pensums = Pensum::with('carrera')->where('estadoA', 1)->get();

        return response()->json([
            'success' => true,
            'data'    => $pensums,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
    'idCarrera'       => 'required|exists:carrera,id',
    'anioCreacion'    => 'required|digits:4|integer|min:' . date('Y') . '|max:2099',
    'estado'          => 'boolean',
]);

        $exists = Pensum::where('idCarrera', $validated['idCarrera'])
                        ->where('anioCreacion', $validated['anioCreacion'])
                        ->where('estadoA', 1)
                        ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un pensum para esta carrera en el año ' . $validated['anioCreacion'] . '. Cada año solo puede tener un pensum por carrera.',
            ], 422);
        }

        $validated['usuarioA'] = $request->user()->id;

        $pensum = Pensum::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pensum creado correctamente',
            'data'    => $pensum->load('carrera'),
        ], 201);
    }

    public function show($id)
    {
        $pensum = Pensum::with(['carrera', 'materias'])->where('estadoA', 1)->find($id);

        if (! $pensum) {
            return response()->json([
                'success' => false,
                'message' => 'Pensum no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $pensum,
        ]);
    }

    public function copiarMaterias(Request $request, $id, $sourceId)
    {
        $pensum = Pensum::where('estadoA', 1)->find($id);
        if (! $pensum) {
            return response()->json(['success' => false, 'message' => 'Pensum no encontrado'], 404);
        }

        $source = Pensum::where('estadoA', 1)->find($sourceId);
        if (! $source) {
            return response()->json(['success' => false, 'message' => 'Pensum origen no encontrado'], 404);
        }

        $materiasOrigen = Materia::where('idPensum', $sourceId)->where('estadoA', 1)->get();
        if ($materiasOrigen->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'El pensum origen no tiene materias'], 404);
        }

        $mapaIds = [];
        $nuevasMaterias = [];

        foreach ($materiasOrigen as $m) {
            $nueva = Materia::create([
                'codigo'        => $m->codigo,
                'nombre'        => $m->nombre,
                'creditos'      => $m->creditos,
                'descripcion'   => $m->descripcion,
                'semestre'      => $m->semestre,
                'idPrerequisito'=> null,
                'idPensum'      => $id,
                'estado'        => $m->estado,
                'usuarioA'      => $request->user()->id,
                'esElectiva'     => $m->esElectiva,
            ]);
            $mapaIds[$m->id] = $nueva->id;
            $nuevasMaterias[] = $nueva;
        }

        foreach ($nuevasMaterias as $nueva) {
            $original = $materiasOrigen->firstWhere('codigo', $nueva->codigo);
            if ($original && $original->idPrerequisito && isset($mapaIds[$original->idPrerequisito])) {
                $nueva->update(['idPrerequisito' => $mapaIds[$original->idPrerequisito]]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Materias copiadas correctamente',
            'data'    => Materia::where('idPensum', $id)->where('estadoA', 1)->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $pensum = Pensum::where('estadoA', 1)->find($id);

        if (! $pensum) {
            return response()->json([
                'success' => false,
                'message' => 'Pensum no encontrado',
            ], 404);
        }

        $validated = $request->validate([
            'estado' => 'required|boolean',
        ]);

        $pensum->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Estado del pensum actualizado correctamente',
            'data'    => $pensum->load('carrera'),
        ]);
    }

    public function destroy($id)
    {
        $pensum = Pensum::where('estadoA', 1)->find($id);

        if (! $pensum) {
            return response()->json([
                'success' => false,
                'message' => 'Pensum no encontrado',]);
        }

        $pensum->update(['estadoA' => 0]);

        return response()->json([
            'success' => true,
            'message' => 'Pensum eliminado correctamente',
        ]);
    }
}
