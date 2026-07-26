<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $notificaciones = Notificacion::where('idUsuario', $request->user()->id)
            ->where('estadoA', 1)
            ->orderByDesc('fechaEnvio')
            ->get()
            ->map(function ($n) {
                return [
                    'id'        => $n->id,
                    'titulo'    => $n->titulo,
                    'tipo'      => $n->tipo,
                    'mensaje'   => $n->mensaje,
                    'fechaEnvio'=> $n->fechaEnvio?->format('Y-m-d H:i:s'),
                    'estado'    => $n->estado,
                    'leida'     => $n->estado === 'Leida',
                ];
            });

        return response()->json(['success' => true, 'data' => $notificaciones]);
    }

    public function noLeidas(Request $request)
    {
        $count = Notificacion::where('idUsuario', $request->user()->id)
            ->where('estadoA', 1)
            ->where('estado', '!=', 'Leida')
            ->count();

        return response()->json(['success' => true, 'data' => ['count' => $count]]);
    }

    public function marcarLeida(Request $request, $id)
    {
        $notificacion = Notificacion::where('id', $id)
            ->where('idUsuario', $request->user()->id)
            ->where('estadoA', 1)
            ->first();

        if (!$notificacion) {
            return response()->json(['success' => false, 'message' => 'Notificación no encontrada'], 404);
        }

        $notificacion->update(['estado' => 'Leida']);

        return response()->json(['success' => true, 'message' => 'Notificación marcada como leída']);
    }

    public function marcarTodasLeidas(Request $request)
    {
        Notificacion::where('idUsuario', $request->user()->id)
            ->where('estadoA', 1)
            ->where('estado', '!=', 'Leida')
            ->update(['estado' => 'Leida']);

        return response()->json(['success' => true, 'message' => 'Todas las notificaciones marcadas como leídas']);
    }
}
