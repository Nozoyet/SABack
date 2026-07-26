<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HistorialAcademico extends Model
{
    protected $table = 'historialacademico';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idEstudiante', 'idMateria', 'idPeriodoAcademico',
        'idInscripcion', 'notaFinal', 'estado', 'usuarioA', 'estadoA'
    ];

    public function materia()          { return $this->belongsTo(Materia::class, 'idMateria', 'id'); }
    public function periodoAcademico() { return $this->belongsTo(PeriodoAcademico::class, 'idPeriodoAcademico', 'id'); }
    public function estudiante()       { return $this->belongsTo(Estudiante::class, 'idEstudiante', 'idUsuario'); }
    public function inscripcion()      { return $this->belongsTo(Inscripcion::class, 'idInscripcion', 'id'); }
}