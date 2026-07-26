<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function __construct(protected UsuarioService $usuarioService) {}

    // Listar todos los usuarios
    public function index(): JsonResponse
    {
        $usuarios = $this->usuarioService->listarUsuarios();
        return response()->json($usuarios);
    }

    // Crear nuevo usuario
    public function store(UsuarioRequest $request): JsonResponse
    {
        $usuario = $this->usuarioService->crearUsuario($request->validated());
        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'usuario' => $usuario
        ], 201);
    }

    // Eliminar usuario (soft delete - cambia estado a FALSE)
    public function destroy(int $id): JsonResponse
    {
        $this->usuarioService->eliminarUsuario($id);
        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    // Asignar rol a un usuario
    public function asignarRol(int $id, UsuarioRequest $request): JsonResponse
    {
        $usuario = $this->usuarioService->asignarRol($id, $request->validated('rol'));
        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'usuario' => $usuario
        ]);
    }
    public function actualizarPerfil(Request $request): JsonResponse
    {
        $usuario = \App\Models\Usuario::find(Auth::id());
        if ($request->has('nombreUsuario')) {
            $usuario->nombreUsuario = $request->nombreUsuario;
        }
        if ($request->filled('contrasena')) {
            $usuario->contrasena = Hash::make($request->contrasena);
        }
        $usuario->save();
        return response()->json(['message' => 'Perfil actualizado.']);
    }
}