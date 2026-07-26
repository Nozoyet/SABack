<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $table = 'estudiante';
    protected $primaryKey = 'idUsuario';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'idUsuario', 'matricula', 'telefono', 'fechaNac',
        'fechaInscripcion', 'idCarrera', 'usuarioA', 'estadoA'
    ];

    public function carrera()      { return $this->belongsTo(Carrera::class, 'idCarrera', 'id'); }
    public function inscripciones(){ return $this->hasMany(Inscripcion::class, 'idEstudiante', 'idUsuario'); }
    public function historial()    { return $this->hasMany(HistorialAcademico::class, 'idEstudiante', 'idUsuario'); }
    public function usuario()      { return $this->belongsTo(Usuario::class, 'idUsuario', 'id'); }
}
