<?php

namespace App\Repositories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UsuarioRepository
{
    public function getAll(): Collection
    {
        return Usuario::where('estadoA', true)
            ->select('id', 'nombre1', 'nombre2', 'apellidoP', 'apellidoM', 'email', 'nombreUsuario', 'rol', 'estado', 'fechaHoraA')
            ->orderBy('apellidoP')
            ->get();
    }

    public function create(array $data): Usuario
    {
        // Extraer campos que NO van en tabla usuario
        $extra = collect($data)->only([
            'matricula', 'telefono', 'fechaNac', 'idCarrera', 'especialidad'
        ])->toArray();

        $usuarioData = collect($data)->except([
            'matricula', 'telefono', 'fechaNac', 'idCarrera', 'especialidad'
        ])->toArray();

        $usuario = Usuario::create($usuarioData);

        // Insertar en tabla según rol
        $usuarioA = $data['usuarioA'] ?? 'admin';

        if ($usuario->rol === 'Estudiante') {
            DB::table('estudiante')->insert([
                'idUsuario'        => $usuario->id,
                'matricula'        => $extra['matricula'] ?? '',
                'telefono'         => $extra['telefono'] ?? null,
                'fechaNac'         => !empty($extra['fechaNac']) ? $extra['fechaNac'] : null,
                'idCarrera'        => $extra['idCarrera'],
                'usuarioA'         => $usuarioA,
                'fechaHoraA'       => now(),
                'estadoA'          => 1,
                'fechaInscripcion' => now(),
            ]);
        } elseif ($usuario->rol === 'Docente') {
            DB::table('docente')->insert([
                'idUsuario'   => $usuario->id,
                'especialidad'=> $extra['especialidad'] ?? null,
                'telefono'    => $extra['telefono'] ?? null,
                'usuarioA'    => $usuarioA,
                'fechaHoraA'  => now(),
                'estadoA'     => 1,
            ]);
        }

        return $usuario;
    }

    public function findById(int $id): ?Usuario
    {
        return Usuario::find($id);
    }

    public function softDelete(int $id): bool
    {
        return Usuario::where('id', $id)->update(['estadoA' => false, 'estado' => false]);
    }

    public function updateRol(int $id, string $rol): ?Usuario
    {
        $usuario = Usuario::find($id);
        if ($usuario) {
            $usuario->rol = $rol;
            $usuario->save();
        }
        return $usuario;
    }

    public function countAdmins(): int
    {
        return Usuario::where('rol', 'Administrador')
            ->where('estadoA', true)
            ->count();
    }
}