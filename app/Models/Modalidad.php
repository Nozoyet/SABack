<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modalidad extends Model
{
    public $timestamps = false;

    protected $table = 'modalidad';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'maxMateriasPermitidas',
        'usuarioA',
    ];

    public function carreras()
    {
        return $this->hasMany(Carrera::class, 'idModalidad');
    }
}
