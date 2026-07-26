<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    protected $table = 'horario';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idCurso', 'diaSemana', 'horaInicio', 'horaFin',
        'aula', 'edificio', 'turno', 'usuarioA', 'estadoA'
    ];

    public function curso() { return $this->belongsTo(Curso::class, 'idCurso', 'id'); }
}