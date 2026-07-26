<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Inscripcion extends Model
{
    protected $table = 'inscripcion';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idEstudiante', 'idCurso', 'fechaInscripcion', 'estado', 'usuarioA', 'estadoA'
    ];

    public function curso() {
        return $this->belongsTo(Curso::class, 'idCurso', 'id')
                    ->with(['materia', 'docente', 'horarios']);
    }
    public function estudiante()        { return $this->belongsTo(Estudiante::class, 'idEstudiante', 'idUsuario'); }
    public function historialAcademico(){ return $this->hasOne(HistorialAcademico::class, 'idInscripcion', 'id'); }
}