<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuario';
    public $timestamps = false;
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre1', 'nombre2', 'apellidoP', 'apellidoM',
        'email', 'nombreUsuario', 'contrasena', 'rol', 'estado',
        'usuarioA',
    ];

    protected $hidden = ['contrasena'];

    // Remapear 'password' → 'contrasena' para Auth::attempt()
    public function getAuthPassword()
    {
        return $this->contrasena;
    }
}