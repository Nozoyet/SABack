<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuario';

    protected $fillable = [
        'nombre1',
        'nombre2',
        'apellidoP',
        'apellidoM',
        'email',
        'nombreUsuario',
        'contrasena',
        'rol',
        'estado',
        'usuarioA',
        'estadoA',
    ];

    protected $hidden = [
        'contrasena',
    ];

    // Laravel usa 'password' por defecto, lo sobreescribimos
    public function getAuthPassword(): string
    {
        return $this->contrasena;
    }
}