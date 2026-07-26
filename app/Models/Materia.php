<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    protected $table = 'materia';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['codigo', 
    'nombre', 
    'creditos', 
    'descripcion', 
    'semestre', 
    'idPrerequisito', 
    'idPensum', 
    'estado', 
    'usuarioA', 
    'estadoA',
    'esElectiva'
    ];

    public function pensum()
    {
        return $this->belongsTo(Pensum::class, 'idPensum', 'id');
    }

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'idMateria', 'id');
    }

    public function prerrequisito()
    {
        return $this->belongsTo(Materia::class, 'idPrerequisito', 'id');
    }
}
