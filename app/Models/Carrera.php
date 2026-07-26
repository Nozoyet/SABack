<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    protected $table = 'carrera';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'estado', 'idModalidad', 'usuarioA', 'estadoA'];

    public function pensums()
    {
        return $this->hasMany(Pensum::class, 'idCarrera', 'id');
    }

    public function materias()
    {
        return $this->hasManyThrough(
            Materia::class,
            Pensum::class,
            'idCarrera',
            'idPensum',
            'id',
            'id'
        );
    }
}
