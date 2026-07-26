<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Usuario;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'nombreUsuario' => 'required|string',
            'contrasena'    => 'required|string',
        ]);

        // Buscar usuario 
        $usuario = Usuario::where('nombreUsuario', $request->nombreUsuario)
                          ->where('estado', true)
                          ->first();

        if (! $usuario || ! Hash::check($request->contrasena, $usuario->contrasena)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas',
            ], 401);
        }

        // Revocar tokens anteriores
        $usuario->tokens()->delete();

        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
                'user' => [
                'id'            => $usuario->id,
                'nombre'        => trim("{$usuario->nombre1} {$usuario->apellidoP}"),
                'nombre1'       => $usuario->nombre1,
                'nombre2'       => $usuario->nombre2,
                'apellidoP'     => $usuario->apellidoP,
                'apellidoM'     => $usuario->apellidoM,
                'email'         => $usuario->email,
                'rol'           => $usuario->rol,
                'username'      => $usuario->nombreUsuario,
                'nombreUsuario' => $usuario->nombreUsuario,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        // Revoca solo el token actual
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}