<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpersonationLog extends Model
{
    protected $table = 'impersonation_logs';
    public $timestamps = false;

    protected $fillable = ['idAdmin', 'idUsuarioObjetivo', 'fechaInicio', 'fechaFin', 'ip'];

    public function admin()
    {
        return $this->belongsTo(Usuario::class, 'idAdmin');
    }

    public function usuarioObjetivo()
    {
        return $this->belongsTo(Usuario::class, 'idUsuarioObjetivo');
    }
}