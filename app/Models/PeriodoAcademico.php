<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PeriodoAcademico extends Model
{
    protected $table = 'periodoacademico';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'codigo', 'fechaInicio', 'fechaFin',
        'idCarrera', 'estado', 'usuarioA', 'estadoA'
    ];

    public function carrera() { return $this->belongsTo(Carrera::class, 'idCarrera', 'id'); }
    public function cursos()  { return $this->hasMany(Curso::class, 'idPeriodoAcademico', 'id'); }
}