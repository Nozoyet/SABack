<?php

namespace App\Services;

use App\Repositories\UsuarioRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Exception;

class UsuarioService
{
    public function __construct(protected UsuarioRepository $usuarioRepository) {}

    // Listar todos los usuarios activos
    public function listarUsuarios()
    {
        return $this->usuarioRepository->getAll();
    }

    // Crear usuario con contraseña hasheada
    public function crearUsuario(array $data)
    {
        $data['contrasena'] = Hash::make($data['contrasena']);
        $data['usuarioA']   = Auth::user()->nombreUsuario ?? 'admin';
        $data['estado']     = true;
        $data['estadoA']    = true;

        return $this->usuarioRepository->create($data);
    }

    // Eliminar usuario (no se puede eliminar el último administrador)
    public function eliminarUsuario(int $id): void
    {
        $usuario = $this->usuarioRepository->findById($id);

        if (!$usuario) {
            throw new Exception('Usuario no encontrado.');
        }

        // Evitar eliminar el último administrador
        if ($usuario->rol === 'Administrador') {
            $totalAdmins = $this->usuarioRepository->countAdmins();
            if ($totalAdmins <= 1) {
                throw new Exception('No se puede eliminar el único administrador del sistema.');
            }
        }

        // Evitar que el admin se elimine a sí mismo
        if (Auth::id() === $id) {
            throw new Exception('No puedes eliminarte a ti mismo.');
        }

        $this->usuarioRepository->softDelete($id);
    }

    // Asignar rol a usuario
    public function asignarRol(int $id, string $rol)
    {
        $usuario = $this->usuarioRepository->findById($id);

        if (!$usuario) {
            throw new Exception('Usuario no encontrado.');
        }

        // Evitar que el último admin pierda su rol
        if ($usuario->rol === 'Administrador' && $rol !== 'Administrador') {
            $totalAdmins = $this->usuarioRepository->countAdmins();
            if ($totalAdmins <= 1) {
                throw new Exception('No se puede cambiar el rol del único administrador.');
            }
        }

        return $this->usuarioRepository->updateRol($id, $rol);
    }
}