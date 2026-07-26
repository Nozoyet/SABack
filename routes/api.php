<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\PensumController;
use App\Http\Controllers\CarreraController;
use App\Http\Controllers\MateriaController;
use App\Http\Controllers\PeriodoAcademicoController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\EstudianteReporteController;
use App\Http\Controllers\ReporteDocenteController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\ModalidadController;

use App\Http\Controllers\ImpersonationController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});



Route::middleware(['auth:sanctum'])->group(function () {

    // Gestión de usuarios
    Route::prefix('usuarios')->group(function () {
        Route::get('/',           [UsuarioController::class, 'index']);
        Route::post('/',          [UsuarioController::class, 'store']);
        Route::delete('/{id}',    [UsuarioController::class, 'destroy']);
        Route::put('/{id}/rol',   [UsuarioController::class, 'asignarRol']);
    });
    
    //Impersonation routes
Route::post('/usuarios/{id}/impersonar', [ImpersonationController::class, 'start']);
Route::post('/impersonar/detener', [ImpersonationController::class, 'stop']);

    // PERFIL
    Route::put('/perfil', [UsuarioController::class, 'actualizarPerfil']);

    Route::apiResource('pensum', PensumController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('pensum/{id}/copiar-materias/{sourceId}', [PensumController::class, 'copiarMaterias']);
     Route::get('/carrera/{id}/pensum-activo', [CarreraController::class, 'pensumActivo']);
   
    Route::apiResource('carrera', CarreraController::class);
    Route::apiResource('materia', MateriaController::class);
    Route::get('/modalidad', [ModalidadController::class, 'index']);
Route::apiResource('carrera', CarreraController::class);
Route::apiResource('materia', MateriaController::class);

// Períodos académicos
    Route::get('/periodos',        [PeriodoAcademicoController::class, 'index']);
    Route::post('/periodos',       [PeriodoAcademicoController::class, 'store']);
    Route::get('/periodos/{id}',   [PeriodoAcademicoController::class, 'show']);

    // Cursos
    Route::get('/cursos',          [CursoController::class, 'index']);
    Route::post('/cursos',         [CursoController::class, 'store']);
    Route::get('/cursos/historial-periodos', [CursoController::class, 'historialPeriodos']);
    Route::delete('/cursos/{id}',  [CursoController::class, 'destroy']);
    Route::put('/cursos/{id}', [CursoController::class, 'update']);
  

    // Notificaciones
    Route::get('/notificaciones',                [NotificacionController::class, 'index']);
    Route::get('/notificaciones/no-leidas',      [NotificacionController::class, 'noLeidas']);
    Route::patch('/notificaciones/{id}/leer',    [NotificacionController::class, 'marcarLeida']);
    Route::patch('/notificaciones/leer-todas',   [NotificacionController::class, 'marcarTodasLeidas']);

    // Carreras - pensum activo con materias
    
    // Agregar este route para listar docentes disponibles
Route::get('/docentes', function () {
    $docentes = \App\Models\Usuario::where('rol', 'Docente')
        ->where('estadoA', 1)
        ->select('id', 'nombre1', 'nombre2', 'apellidoP', 'apellidoM')
        ->get()
        ->map(fn($u) => [
            'id'     => $u->id,
            'nombre' => trim("{$u->nombre1} {$u->apellidoP}"),
        ]);
    return response()->json(['success' => true, 'data' => $docentes]);
})->middleware('auth:sanctum');

Route::get('/inscripciones/disponibles', [InscripcionController::class, 'disponibles']);
Route::post('/inscripciones',            [InscripcionController::class, 'store']);
Route::get('/inscripciones/mis',         [InscripcionController::class, 'misInscripciones']);
Route::get('/inscripciones/historial', [InscripcionController::class, 'historial']);

});

// Inscripciones (estudiante)

Route::middleware(['auth:sanctum', 'admin'])->prefix('reportes')->group(function () {
    Route::get('carreras', [ReporteController::class, 'carreras']);
    Route::get('materias', [ReporteController::class, 'materiasXCarrera']);
    Route::get('gestiones', [ReporteController::class, 'gestiones']);
    Route::get('periodos', [ReporteController::class, 'periodos']);
    Route::get('cursos-por-periodo', [ReporteController::class, 'cursosPorPeriodo']);
    Route::get('estudiantes', [ReporteController::class, 'reporteEstudiantes']);
    Route::get('docentes', [ReporteController::class, 'reporteDocentes']);
    Route::get('exportar-pdf/{carreraId}', [ReporteController::class, 'exportPdf']);
    Route::get('exportar-excel/{carreraId}', [ReporteController::class, 'exportExcel']);
    Route::get('exportar-pdf-estudiantes', [ReporteController::class, 'exportPdfEstudiantes']);
    Route::get('exportar-excel-estudiantes', [ReporteController::class, 'exportExcelEstudiantes']);
    Route::get('exportar-pdf-docentes', [ReporteController::class, 'exportPdfDocentes']);
    Route::get('exportar-excel-docentes', [ReporteController::class, 'exportExcelDocentes']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/docente/reportes/filtros', [ReporteDocenteController::class, 'filtrosReportes']);
    Route::get('/docente/reportes/{tipo}/preview', [ReporteDocenteController::class, 'preview'])->where('tipo', 'periodo-academico|estudiantes|materias|cursos|calificaciones');

    Route::get('/docente/reportes/{tipo}/pdf', [ReporteDocenteController::class, 'exportarPdf'])->where('tipo', 'periodo-academico|estudiantes|materias|cursos|calificaciones');

    Route::get('/docente/reportes/{tipo}/excel', [ReporteDocenteController::class, 'exportarExcel'])->where('tipo', 'periodo-academico|estudiantes|materias|cursos|calificaciones');

    // ── Docente: cursos, estudiantes, calificaciones ──
    Route::get('docente/cursos',                     [DocenteController::class, 'cursos']);
    Route::get('docente/cursos/{id}/estudiantes',    [DocenteController::class, 'estudiantesPorCurso']);
    Route::put('docente/cursos/{id}/calificaciones', [DocenteController::class, 'guardarCalificaciones']);
    Route::get('docente/reportes/curso/{cursoId}',              [DocenteController::class, 'reporteCurso']);
    Route::get('docente/reportes/exportar-pdf/{cursoId}',       [DocenteController::class, 'exportarPdfCurso']);
    Route::get('docente/reportes/exportar-excel/{cursoId}',     [DocenteController::class, 'exportarExcelCurso']);

    // ── Estudiante: reportes ──
    Route::get('estudiante/reportes',              [EstudianteReporteController::class, 'reporte']);
    Route::get('estudiante/reportes/exportar-pdf',   [EstudianteReporteController::class, 'exportarPdf']);
    Route::get('estudiante/reportes/exportar-excel', [EstudianteReporteController::class, 'exportarExcel']);
});