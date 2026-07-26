<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificacion';
    public $timestamps = false;
    protected $primaryKey = 'id';

    protected $fillable = [
        'idUsuario',
        'titulo',
        'tipo',
        'mensaje',
        'fechaEnvio',
        'estado',
        'usuarioA',
        'estadoA',
    ];

    protected $casts = [
        'fechaEnvio' => 'datetime',
        'estadoA' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'idUsuario', 'id');
    }
}
