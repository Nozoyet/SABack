<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pensum extends Model
{
    protected $table = 'pensum';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idCarrera',
        'anioCreacion',
        'estado',
        'usuarioA',
        'estadoA',
        'fechaHoraA',
    ];

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'idCarrera', 'id');
    }

    public function materias()
    {
        return $this->hasMany(Materia::class, 'idPensum', 'id');
    }
}
