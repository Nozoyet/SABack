<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\ImpersonationLog;

class ImpersonationController extends Controller
{
    public function start(Request $request, $id)
    {
        $admin = $request->user();

        if ($admin->rol !== 'Administrador') {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para esta acción'], 403);
        }

        if ((int) $admin->id === (int) $id) {
            return response()->json(['success' => false, 'message' => 'No puedes suplantarte a ti mismo'], 422);
        }

        $objetivo = Usuario::where('estadoA', 1)->find($id);

        if (! $objetivo) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        if ($objetivo->rol === 'Administrador') {
            return response()->json(['success' => false, 'message' => 'No se puede suplantar a otro administrador'], 422);
        }

        ImpersonationLog::create([
            'idAdmin'           => $admin->id,
            'idUsuarioObjetivo' => $objetivo->id,
            'fechaInicio'       => now(),
            'ip'                => $request->ip(),
        ]);

        // Importante: NO se revocan tokens existentes (a diferencia del login normal),
        // para que el token del admin siga vivo mientras dura la suplantación.
        $token = $objetivo->createToken('impersonation')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'            => $objetivo->id,
                'nombre'        => trim("{$objetivo->nombre1} {$objetivo->apellidoP}"),
                'nombre1'       => $objetivo->nombre1,
                'nombre2'       => $objetivo->nombre2,
                'apellidoP'     => $objetivo->apellidoP,
                'apellidoM'     => $objetivo->apellidoM,
                'email'         => $objetivo->email,
                'rol'           => $objetivo->rol,
                'username'      => $objetivo->nombreUsuario,
                'nombreUsuario' => $objetivo->nombreUsuario,
            ],
        ]);
    }

    public function stop(Request $request)
    {
        $usuario = $request->user(); // autenticado con el token del usuario suplantado

        $log = ImpersonationLog::where('idUsuarioObjetivo', $usuario->id)
            ->whereNull('fechaFin')
            ->latest('fechaInicio')
            ->first();

        if ($log) {
            $log->update(['fechaFin' => now()]);
        }

        // Revocar solo el token de impersonación actual
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Sesión de suplantación finalizada']);
    }
}