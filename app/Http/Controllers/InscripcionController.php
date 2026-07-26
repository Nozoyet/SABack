<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inscripcion;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\PeriodoAcademico;
use App\Models\HistorialAcademico;
use App\Models\Notificacion;
use Illuminate\Support\Facades\DB;

class InscripcionController extends Controller
{
    /**
     * Cursos disponibles para el estudiante autenticado en el período vigente.
     * Filtra por prerrequisitos aprobados y excluye cursos ya inscritos.
     */
    public function disponibles(Request $request)
    {
        $usuario = $request->user();

        $estudiante = Estudiante::where('idUsuario', $usuario->id)
            ->where('estadoA', 1)->first();

        if (!$estudiante) {
            return response()->json(['success' => false, 'message' => 'Perfil de estudiante no encontrado'], 404);
        }

        // Período más reciente de la carrera del estudiante
        $periodo = PeriodoAcademico::where('idCarrera', $estudiante->idCarrera)
            ->where('estadoA', 1)
            ->orderByDesc('fechaInicio')
            ->first();

        if (!$periodo) {
            return response()->json(['success' => false, 'message' => 'No hay período académico activo para tu carrera'], 404);
        }

        // Materias aprobadas por el estudiante
        $materiasAprobadas = HistorialAcademico::where('idEstudiante', $usuario->id)
            ->where('estado', 'Aprobado')
            ->where('estadoA', 1)
            ->pluck('idMateria')
            ->toArray();
           

        // IDs de materias ya inscritas en este período
        $cursosInscritos = Inscripcion::where('idEstudiante', $usuario->id)
            ->whereIn('estado', ['Activa'])
            ->where('estadoA', 1)
            ->pluck('idCurso')
            ->toArray();

        $materiasYaInscritas = Curso::whereIn('id', $cursosInscritos)
            ->where('idPeriodoAcademico', $periodo->id)
            ->pluck('idMateria')
            ->toArray();

        // Cursos del período con sus relaciones
        $cursos = Curso::with(['materia.prerrequisito', 'docente', 'horarios'])
            ->where('idPeriodoAcademico', $periodo->id)
            ->where('estado', 1)
            ->where('estadoA', 1)
            ->whereNotIn('idMateria', $materiasAprobadas)
            ->get();

        // Clasificar cada curso
        $resultado = $cursos->map(function ($curso) use ($materiasAprobadas, $materiasYaInscritas, $cursosInscritos) {
            $materia = $curso->materia;
            $prereq = $materia->idPrerequisito;

            $cumplePrerequisito = is_null($prereq) || in_array($prereq, $materiasAprobadas);
            $yaInscrito = in_array($curso->id, $cursosInscritos);
            $materiaYaInscrita = in_array($materia->id, $materiasYaInscritas) && !$yaInscrito;
            $sinCupo = $curso->cupoActual >= $curso->cupoMaximo;

            return [
                'id'                  => $curso->id,
                'codigoGrupo'         => $curso->codigoGrupo,
                'cupoMaximo'          => $curso->cupoMaximo,
                'cupoActual'          => $curso->cupoActual,
                'cuposDisponibles'    => $curso->cupoMaximo - $curso->cupoActual,
                'materia'             => [
                    'id'          => $materia->id,
                    'codigo'      => $materia->codigo,
                    'nombre'      => $materia->nombre,
                    'creditos'    => $materia->creditos,
                    'semestre'    => $materia->semestre,
                    'prerrequisito' => $materia->prerrequisito?->nombre,
                ],
                'docente'  => $curso->docente ? trim("{$curso->docente->nombre1} {$curso->docente->apellidoP}") : null,
                'horarios' => $curso->horarios->map(fn($h) => [
                    'diaSemana'  => $h->diaSemana,
                    'horaInicio' => substr($h->horaInicio, 0, 5),
                    'horaFin'    => substr($h->horaFin, 0, 5),
                    'aula'       => $h->aula,
                    'edificio'   => $h->edificio,
                    'turno'      => $h->turno,
                ]),
                'disponible'          => $cumplePrerequisito && !$yaInscrito && !$materiaYaInscrita && !$sinCupo,
                'yaInscrito'          => $yaInscrito,
                'materiaYaInscrita'   => $materiaYaInscrita,
                'sinPrerequisito'     => !$cumplePrerequisito,
                'sinCupo'             => $sinCupo,
            ];
        });

        return response()->json([
            'success' => true,
            'periodo' => [
                'id'          => $periodo->id,
                'codigo'      => $periodo->codigo,
                'fechaInicio' => $periodo->fechaInicio,
                'fechaFin'    => $periodo->fechaFin,
            ],
            'data' => $resultado,
        ]);
    }

    /**
     * Inscribir al estudiante en un curso.
     */
    public function store(Request $request)
    {
        $request->validate(['idCurso' => 'required|exists:curso,id']);

        $usuario    = $request->user();
        $estudiante = Estudiante::where('idUsuario', $usuario->id)->where('estadoA', 1)->first();

        if (!$estudiante) {
            return response()->json(['success' => false, 'message' => 'Perfil de estudiante no encontrado'], 404);
        }

        $curso = Curso::with('horarios')->where('id', $request->idCurso)
            ->where('estado', 1)->where('estadoA', 1)->first();

        if (!$curso) {
            return response()->json(['success' => false, 'message' => 'Curso no disponible'], 404);
        }

        // ── Validar cupo ──────────────────────────────────────────────────
        if ($curso->cupoActual >= $curso->cupoMaximo) {
            return response()->json(['success' => false, 'message' => 'El curso no tiene cupos disponibles'], 422);
        }

        // ── Validar inscripción duplicada ─────────────────────────────────
        $yaInscrito = Inscripcion::where('idEstudiante', $usuario->id)
            ->where('idCurso', $curso->id)
            ->where('estadoA', 1)->exists();

        if ($yaInscrito) {
            return response()->json(['success' => false, 'message' => 'Ya estás inscrito en este curso'], 422);
        }

        // ── Validar prerrequisito ─────────────────────────────────────────
        $prereqId = $curso->materia->idPrerequisito ?? null;
        if ($prereqId) {
            $aprobado = HistorialAcademico::where('idEstudiante', $usuario->id)
                ->where('idMateria', $prereqId)
                ->where('estado', 'Aprobado')
                ->where('estadoA', 1)->exists();
            if (!$aprobado) {
                return response()->json(['success' => false, 'message' => 'No cumples el prerrequisito para esta materia'], 422);
            }
        }

        // ── Validar materia ya inscrita en este período ───────────────────
        $periodo = PeriodoAcademico::where('idCarrera', $estudiante->idCarrera)
            ->where('estadoA', 1)->orderByDesc('fechaInicio')->first();

        $cursosDelPeriodo = Curso::where('idPeriodoAcademico', $periodo->id)->pluck('id');
        $materiaYaInscrita = Inscripcion::where('idEstudiante', $usuario->id)
            ->whereIn('idCurso', $cursosDelPeriodo)
            ->where('estadoA', 1)
            ->whereHas('curso', fn($q) => $q->where('idMateria', $curso->idMateria))
            ->exists();

        if ($materiaYaInscrita) {
            return response()->json(['success' => false, 'message' => 'Ya estás inscrito en otro grupo de esta materia'], 422);
        }

        // ── Validar choque de horario del estudiante ──────────────────────
        $inscripcionesActivas = Inscripcion::where('idEstudiante', $usuario->id)
            ->where('estado', 'Activa')->where('estadoA', 1)
            ->whereHas('curso', fn($q) => $q->where('idPeriodoAcademico', $periodo->id))
            ->with('curso.horarios')->get();

        foreach ($curso->horarios as $nuevo) {
            foreach ($inscripcionesActivas as $ins) {
                foreach ($ins->curso->horarios as $ex) {
                    if ($nuevo->diaSemana === $ex->diaSemana &&
                        $nuevo->horaInicio < $ex->horaFin &&
                        $nuevo->horaFin    > $ex->horaInicio) {
                        return response()->json([
                            'success' => false,
                            'message' => "Tienes un choque de horario el {$ex->diaSemana} de {$ex->horaInicio} a {$ex->horaFin} con el curso {$ins->curso->codigoGrupo}",
                        ], 422);
                    }
                }
            }
        }

        // ── Todo ok: inscribir ────────────────────────────────────────────
        DB::beginTransaction();
        try {
            $inscripcion = Inscripcion::create([
                'idEstudiante'    => $usuario->id,
                'idCurso'         => $curso->id,
                'fechaInscripcion'=> now(),
                'estado'          => 'Activa',
                'usuarioA'        => $usuario->id,
                'estadoA'         => true,
            ]);

            $curso->increment('cupoActual');

            DB::commit();

            $inscripcion->load(['curso.materia', 'curso.docente', 'curso.horarios']);

            $horarioStr = $inscripcion->curso->horarios->map(fn($h) => "{$h->diaSemana} {$h->horaInicio}-{$h->horaFin}")->implode(', ');
            $turno = $inscripcion->curso->horarios->first()?->turno ?? '—';
            $docenteNombre = $inscripcion->curso->docente
                ? trim("{$inscripcion->curso->docente->nombre1} {$inscripcion->curso->docente->apellidoP}")
                : '—';
            $mes = now()->locale('es')->monthName;

            Notificacion::create([
                'idUsuario' => $usuario->id,
                'titulo'    => 'Inscripción Exitosa',
                'tipo'      => 'inscripcion_exitosa',
                'mensaje'   => "Se ha inscrito correctamente a {$inscripcion->curso->materia->nombre}, en el curso {$inscripcion->curso->codigoGrupo}. Docente: {$docenteNombre}, Horario: {$horarioStr}, Turno: {$turno}, Mes: {$mes}",
                'fechaEnvio'=> now(),
                'estado'    => 'Pendiente',
                'usuarioA'  => $usuario->id,
                'estadoA'   => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => '¡Inscripción realizada exitosamente!',
                'data'    => $inscripcion,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error al inscribir: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Inscripciones activas del estudiante.
     */
    public function misInscripciones(Request $request)
    {
        $usuario = $request->user();

        $inscripciones = Inscripcion::with(['curso.materia', 'curso.docente', 'curso.horarios', 'curso.periodoAcademico'])
            ->where('idEstudiante', $usuario->id)
            ->where('estadoA', 1)
            ->orderByDesc('fechaInscripcion')
            ->get();

        return response()->json(['success' => true, 'data' => $inscripciones]);
    }
    public function historial(Request $request)
{
    $usuario = $request->user();

    $historial = HistorialAcademico::with([
        'materia.prerrequisito',
        'periodoAcademico',
        'inscripcion.curso.docente',
        'inscripcion.curso.horarios',
    ])
        ->where('idEstudiante', $usuario->id)
        ->where('estadoA', 1)
        ->orderByDesc('idPeriodoAcademico')
        ->get();

    return response()->json(['success' => true, 'data' => $historial]);
}
}