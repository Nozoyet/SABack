<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    protected $table = 'curso';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'codigoGrupo', 'idMateria', 'idPeriodoAcademico', 'idDocente',
        'cupoMaximo', 'cupoActual', 'estado', 'usuarioA', 'estadoA'
    ];

    public function materia()      { return $this->belongsTo(Materia::class, 'idMateria', 'id'); }
    public function docente()      { return $this->belongsTo(Usuario::class, 'idDocente', 'id'); }
    public function periodoAcademico() 
    { 
        return $this->belongsTo(PeriodoAcademico::class, 'idPeriodoAcademico', 'id'); 
        }
    public function horarios()
    {
        return $this->hasMany(Horario::class, 'idCurso', 'id');
    }
    // En app/Models/Curso.php
public function inscripciones()
{
    return $this->hasMany(Inscripcion::class, 'idCurso', 'id');
}
}