<?php

namespace App\Services;

use App\Models\Carrera;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class ReporteService {
    public function obtenerTodasLasCarreras() {
        try {
            $carreras = DB::table('carrera')
                ->join('modalidad', 'carrera.idModalidad', '=', 'modalidad.id')
                ->where('carrera.estado', 1)
                ->select('carrera.id', 'carrera.codigo', 'carrera.nombre', 'modalidad.nombre as modalidad')
                ->orderBy('carrera.nombre')
                ->get();

            return $carreras->toArray();
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener carreras: ' . $e->getMessage());
        }
    }

    public function obtenerMateriasPorCarrera($carreraId, $semestre = null) {
        try {
            $carrera = Carrera::find($carreraId);

            if (!$carrera) {
                throw new \Exception('Carrera no encontrada');
            }

            $query = DB::table('materia')
                ->join('pensum', 'materia.idPensum', '=', 'pensum.id')
                ->where('pensum.idCarrera', $carreraId)
                ->where('materia.estado', 1)
                ->select(
                    'materia.id',
                    'materia.codigo',
                    'materia.nombre',
                    'materia.creditos',
                    'materia.semestre'
                )
                ->orderBy('materia.semestre')
                ->orderBy('materia.codigo');

            if ($semestre) {
                $query->where('materia.semestre', $semestre);
            }

            $materias = $query->get()->map(function ($materia) {
                return [
                    'id' => $materia->id,
                    'codigo' => $materia->codigo,
                    'nombre' => $materia->nombre,
                    'creditos' => (int) $materia->creditos,
                    'semestre' => (int) $materia->semestre,
                ];
            })->toArray();

            return $materias;
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener materias: ' . $e->getMessage());
        }
    }

    public function generarPdf($carreraId) {
        try {
            $carrera = Carrera::find($carreraId);
            if (!$carrera) {
                throw new \Exception('Carrera no encontrada');
            }

            $materias = $this->obtenerMateriasPorCarrera($carreraId);
            $html = $this->generarHtmlReporte($carrera->nombre, $materias);

            $pdf = Pdf::loadHtml($html)
                ->setPaper('a4', 'landscape')
                ->setOption('margin-top', 15)
                ->setOption('margin-bottom', 15)
                ->setOption('margin-left', 15)
                ->setOption('margin-right', 15);

            return $pdf->output();
        } catch (\Exception $e) {
            \Log::error('Error al generar PDF: ' . $e->getMessage());
            throw new \Exception('Error al generar PDF: ' . $e->getMessage());
        }
    }

    public function generarExcel($carreraId) {
        try {
            $carrera = Carrera::find($carreraId);
            if (!$carrera) {
                throw new \Exception('Carrera no encontrada');
            }

            $materias = $this->obtenerMateriasPorCarrera($carreraId);

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Materias');

            $headers = ['Código', 'Nombre', 'Créditos', 'Semestre'];
            $sheet->fromArray($headers, NULL, 'A1');

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '7c3aed']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'e2e8f0']]],
            ];
            for ($col = 'A'; $col <= 'D'; $col++) {
                $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
            }

            $row = 2;
            foreach ($materias as $materia) {
                $sheet->setCellValue('A' . $row, $materia['codigo']);
                $sheet->setCellValue('B' . $row, $materia['nombre']);
                $sheet->setCellValue('C' . $row, $materia['creditos']);
                $sheet->setCellValue('D' . $row, $materia['semestre']);
                $row++;
            }

            $sheet->getColumnDimension('A')->setWidth(12);
            $sheet->getColumnDimension('B')->setWidth(45);
            $sheet->getColumnDimension('C')->setWidth(12);
            $sheet->getColumnDimension('D')->setWidth(12);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            // ✅ Capturar el output en memoria, no imprimir directo
            ob_start();
            $writer->save('php://output');
            $content = ob_get_clean();

            $filename = 'reporte-' . str_replace(' ', '-', $carrera->nombre) . '.xlsx';

            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'max-age=0');
        } catch (\Exception $e) {
            \Log::error('Error al generar Excel: ' . $e->getMessage());
            throw new \Exception('Error al generar Excel: ' . $e->getMessage());
        }
    }

    public function obtenerGestionesPorCarrera($carreraId) {
        try {
            return DB::table('periodoacademico')
                ->where('idCarrera', $carreraId)
                ->where('estadoA', 1)
                ->select('id', 'codigo', 'fechaInicio', 'fechaFin')
                ->orderBy('fechaInicio', 'desc')
                ->get()
                ->map(function ($g) {
                    $anio = date('Y', strtotime($g->fechaInicio));
                    return [
                        'id' => $g->id,
                        'codigo' => $g->codigo,
                        'anio' => $anio,
                        'label' => "Gestión {$anio} - Periodo {$g->codigo}",
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener gestiones: ' . $e->getMessage());
        }
    }

    public function obtenerPeriodosPorCarrera($carreraId) {
        try {
            return DB::table('periodoacademico')
                ->where('idCarrera', $carreraId)
                ->where('estadoA', 1)
                ->select('id', 'codigo', 'fechaInicio', 'fechaFin')
                ->orderBy('fechaInicio', 'desc')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener periodos: ' . $e->getMessage());
        }
    }

    public function obtenerCursosPorPeriodo($periodoId) {
        try {
            return DB::table('curso')
                ->join('materia', 'curso.idMateria', '=', 'materia.id')
                ->where('curso.idPeriodoAcademico', $periodoId)
                ->where('curso.estadoA', 1)
                ->select('curso.id', DB::raw("CONCAT(materia.codigo, ' - ', materia.nombre) as nombre"))
                ->orderBy('materia.nombre')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener cursos: ' . $e->getMessage());
        }
    }

    public function obtenerReporteEstudiantes($request) {
        try {
            $carreraId = $request->query('carrera_id');
            $periodoId = $request->query('periodo_id');
            $cursoId = $request->query('curso_id');
            $fechaInicio = $request->query('fecha_inicio');
            $fechaFin = $request->query('fecha_fin');
            $nombre = $request->query('nombre');
            $nombreUsuario = $request->query('nombreUsuario');

            $query = DB::table('inscripcion')
                ->join('estudiante', 'inscripcion.idEstudiante', '=', 'estudiante.idUsuario')
                ->join('usuario', 'estudiante.idUsuario', '=', 'usuario.id')
                ->join('carrera', 'estudiante.idCarrera', '=', 'carrera.id')
                ->join('curso', 'inscripcion.idCurso', '=', 'curso.id')
                ->join('materia', 'curso.idMateria', '=', 'materia.id')
                ->join('periodoacademico', 'curso.idPeriodoAcademico', '=', 'periodoacademico.id')
                ->leftJoin('historialacademico', 'inscripcion.id', '=', 'historialacademico.idInscripcion')
                ->where('inscripcion.estadoA', 1)
                ->where('usuario.estado', 1);

            if ($carreraId) {
                $query->where('estudiante.idCarrera', $carreraId);
            }

            if ($periodoId) {
                $query->where('curso.idPeriodoAcademico', $periodoId);
            }

            if ($cursoId) {
                $query->where('inscripcion.idCurso', $cursoId);
            }

            if ($fechaInicio) {
                $query->whereDate('inscripcion.fechaInscripcion', '>=', $fechaInicio);
            }

            if ($fechaFin) {
                $query->whereDate('inscripcion.fechaInscripcion', '<=', $fechaFin);
            }

            if ($nombre) {
                $query->where(function ($q) use ($nombre) {
                    $q->where('usuario.nombre1', 'LIKE', "%{$nombre}%")
                      ->orWhere('usuario.nombre2', 'LIKE', "%{$nombre}%")
                      ->orWhere('usuario.apellidoP', 'LIKE', "%{$nombre}%")
                      ->orWhere('usuario.apellidoM', 'LIKE', "%{$nombre}%");
                });
            }

            if ($nombreUsuario) {
                $query->where('usuario.nombreUsuario', 'LIKE', "%{$nombreUsuario}%");
            }

            return $query->select(
                'inscripcion.id',
                'estudiante.matricula',
                DB::raw("TRIM(CONCAT(usuario.nombre1, ' ', COALESCE(usuario.nombre2, ''), ' ', usuario.apellidoP, ' ', COALESCE(usuario.apellidoM, ''))) as estudiante"),
                'carrera.nombre as carrera',
                DB::raw("CONCAT(materia.codigo, ' - ', materia.nombre) as curso"),
                'historialacademico.notaFinal',
                'inscripcion.estado'
            )
            ->orderBy('usuario.apellidoP')
            ->orderBy('usuario.nombre1')
            ->get()
            ->toArray();
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener reporte de estudiantes: ' . $e->getMessage());
        }
    }

    public function obtenerReporteDocentes($request) {
        try {
            $carreraId = $request->query('carrera_id');
            $periodoId = $request->query('periodo_id');
            $cursoId = $request->query('curso_id');
            $fechaInicio = $request->query('fecha_inicio');
            $fechaFin = $request->query('fecha_fin');
            $nombre = $request->query('nombre');
            $nombreUsuario = $request->query('nombreUsuario');

            $query = DB::table('curso')
                ->join('usuario', 'curso.idDocente', '=', 'usuario.id')
                ->leftJoin('docente', 'usuario.id', '=', 'docente.idUsuario')
                ->join('materia', 'curso.idMateria', '=', 'materia.id')
                ->join('pensum', 'materia.idPensum', '=', 'pensum.id')
                ->join('carrera', 'pensum.idCarrera', '=', 'carrera.id')
                ->join('periodoacademico', 'curso.idPeriodoAcademico', '=', 'periodoacademico.id')
                ->where('usuario.rol', 'Docente')
                ->where('curso.estadoA', 1)
                ->where('usuario.estado', 1);

            if ($carreraId) {
                $query->where('carrera.id', $carreraId);
            }

            if ($periodoId) {
                $query->where('curso.idPeriodoAcademico', $periodoId);
            }

            if ($cursoId) {
                $query->where('curso.id', $cursoId);
            }

            if ($fechaInicio) {
                $query->whereDate('curso.fechaHoraA', '>=', $fechaInicio);
            }

            if ($fechaFin) {
                $query->whereDate('curso.fechaHoraA', '<=', $fechaFin);
            }

            if ($nombre) {
                $query->where(function ($q) use ($nombre) {
                    $q->where('usuario.nombre1', 'LIKE', "%{$nombre}%")
                      ->orWhere('usuario.nombre2', 'LIKE', "%{$nombre}%")
                      ->orWhere('usuario.apellidoP', 'LIKE', "%{$nombre}%")
                      ->orWhere('usuario.apellidoM', 'LIKE', "%{$nombre}%");
                });
            }

            if ($nombreUsuario) {
                $query->where('usuario.nombreUsuario', 'LIKE', "%{$nombreUsuario}%");
            }

            $results = $query->select(
                'usuario.id as idDocente',
                'curso.id as idCurso',
                DB::raw("TRIM(CONCAT(usuario.nombre1, ' ', COALESCE(usuario.nombre2, ''), ' ', usuario.apellidoP, ' ', COALESCE(usuario.apellidoM, ''))) as docente"),
                'docente.especialidad',
                DB::raw("CONCAT(materia.codigo, ' - ', materia.nombre) as materia"),
                DB::raw("CONCAT(materia.codigo, ' - ', materia.nombre) as curso"),
                'periodoacademico.codigo as periodo',
                DB::raw("(SELECT COUNT(*) FROM inscripcion WHERE inscripcion.idCurso = curso.id AND inscripcion.estadoA = 1) as totalEstudiantes")
            )
            ->orderBy('usuario.apellidoP')
            ->orderBy('usuario.nombre1')
            ->get()
            ->toArray();

            return $results;
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener reporte de docentes: ' . $e->getMessage());
        }
    }

    public function generarPdfEstudiantes($request) {
        try {
            $data = $this->obtenerReporteEstudiantes($request);
            $html = $this->generarHtmlReporteEstudiantes($data);
            $pdf = Pdf::loadHtml($html)
                ->setPaper('a4', 'landscape')
                ->setOption('margin-top', 15)
                ->setOption('margin-bottom', 15)
                ->setOption('margin-left', 15)
                ->setOption('margin-right', 15);
            return $pdf->output();
        } catch (\Exception $e) {
            \Log::error('Error al generar PDF estudiantes: ' . $e->getMessage());
            throw new \Exception('Error al generar PDF: ' . $e->getMessage());
        }
    }

    public function generarExcelEstudiantes($request) {
        try {
            $data = $this->obtenerReporteEstudiantes($request);

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Estudiantes');

            $headers = ['Matrícula', 'Estudiante', 'Carrera', 'Curso', 'Nota Final', 'Estado'];
            $sheet->fromArray($headers, NULL, 'A1');

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '7c3aed']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'e2e8f0']]],
            ];
            for ($col = 'A'; $col <= 'F'; $col++) {
                $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
            }

            $row = 2;
            foreach ($data as $item) {
                $item = (array) $item;
                $sheet->setCellValue('A' . $row, $item['matricula']);
                $sheet->setCellValue('B' . $row, $item['estudiante']);
                $sheet->setCellValue('C' . $row, $item['carrera']);
                $sheet->setCellValue('D' . $row, $item['curso']);
                $sheet->setCellValue('E' . $row, $item['notaFinal'] ?? '');
                $sheet->setCellValue('F' . $row, $item['estado']);
                $row++;
            }

            $sheet->getColumnDimension('A')->setWidth(18);
            $sheet->getColumnDimension('B')->setWidth(35);
            $sheet->getColumnDimension('C')->setWidth(25);
            $sheet->getColumnDimension('D')->setWidth(35);
            $sheet->getColumnDimension('E')->setWidth(12);
            $sheet->getColumnDimension('F')->setWidth(14);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $content = ob_get_clean();

            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="reporte-estudiantes.xlsx"')
                ->header('Cache-Control', 'max-age=0');
        } catch (\Exception $e) {
            \Log::error('Error al generar Excel estudiantes: ' . $e->getMessage());
            throw new \Exception('Error al generar Excel: ' . $e->getMessage());
        }
    }

    public function generarPdfDocentes($request) {
        try {
            $data = $this->obtenerReporteDocentes($request);
            $html = $this->generarHtmlReporteDocentes($data);
            $pdf = Pdf::loadHtml($html)
                ->setPaper('a4', 'landscape')
                ->setOption('margin-top', 15)
                ->setOption('margin-bottom', 15)
                ->setOption('margin-left', 15)
                ->setOption('margin-right', 15);
            return $pdf->output();
        } catch (\Exception $e) {
            \Log::error('Error al generar PDF docentes: ' . $e->getMessage());
            throw new \Exception('Error al generar PDF: ' . $e->getMessage());
        }
    }

    public function generarExcelDocentes($request) {
        try {
            $data = $this->obtenerReporteDocentes($request);

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Docentes');

            $headers = ['Docente', 'Especialidad', 'Curso', 'Materia', 'Periodo', 'Total Estudiantes'];
            $sheet->fromArray($headers, NULL, 'A1');

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '7c3aed']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'e2e8f0']]],
            ];
            for ($col = 'A'; $col <= 'F'; $col++) {
                $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
            }

            $row = 2;
            foreach ($data as $item) {
                $item = (array) $item;
                $sheet->setCellValue('A' . $row, $item['docente']);
                $sheet->setCellValue('B' . $row, $item['especialidad'] ?? '');
                $sheet->setCellValue('C' . $row, $item['curso']);
                $sheet->setCellValue('D' . $row, $item['materia']);
                $sheet->setCellValue('E' . $row, $item['periodo']);
                $sheet->setCellValue('F' . $row, $item['totalEstudiantes']);
                $row++;
            }

            $sheet->getColumnDimension('A')->setWidth(35);
            $sheet->getColumnDimension('B')->setWidth(30);
            $sheet->getColumnDimension('C')->setWidth(35);
            $sheet->getColumnDimension('D')->setWidth(35);
            $sheet->getColumnDimension('E')->setWidth(18);
            $sheet->getColumnDimension('F')->setWidth(18);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $content = ob_get_clean();

            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="reporte-docentes.xlsx"')
                ->header('Cache-Control', 'max-age=0');
        } catch (\Exception $e) {
            \Log::error('Error al generar Excel docentes: ' . $e->getMessage());
            throw new \Exception('Error al generar Excel: ' . $e->getMessage());
        }
    }

    private function generarHtmlReporteEstudiantes($data) {
        $fechaActual = now()->format('d/m/Y H:i');
        $total = count($data);

        $filas = '';
        foreach ($data as $idx => $row) {
            $row = (array) $row;
            $bg = $idx % 2 === 0 ? '#f8fafc' : '#ffffff';
            $notaFinal = $row['notaFinal'] ?? '—';
            $filas .= "
                <tr style='background-color: {$bg}; border-bottom: 1px solid #e2e8f0;'>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$row['matricula']}</td>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$row['estudiante']}</td>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$row['carrera']}</td>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$row['curso']}</td>
                    <td style='padding: 11px 15px; font-size: 13px; text-align: center;'>{$notaFinal}</td>
                    <td style='padding: 11px 15px; font-size: 13px; text-align: center;'>{$row['estado']}</td>
                </tr>
            ";
        }

        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    * { margin: 0; padding: 0; }
                    body { font-family: Arial, sans-serif; color: #1e293b; line-height: 1.4; }
                    .container { padding: 25px; }
                    .header { border-top: 4px solid #7c3aed; margin-bottom: 25px; }
                    h1 { font-size: 26px; font-weight: bold; margin-bottom: 5px; color: #0f172a; }
                    .subtitle { font-size: 13px; color: #64748b; margin-bottom: 15px; }
                    .info-section { display: flex; gap: 15px; margin-bottom: 25px; }
                    .info-item { flex: 1; background: #f8fafc; padding: 12px 15px; border-radius: 8px; border-left: 3px solid #7c3aed; }
                    .info-label { font-size: 11px; color: #94a3b8; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
                    .info-value { font-size: 14px; color: #1e293b; font-weight: 500; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    thead { background: #7c3aed; color: white; }
                    th { padding: 12px 15px; text-align: left; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
                    td { padding: 11px 15px; font-size: 13px; }
                    .footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Reporte de Estudiantes</h1>
                        <p class='subtitle'>Listado de estudiantes inscritos por curso y periodo</p>
                    </div>
                    <div class='info-section'>
                        <div class='info-item'>
                            <div class='info-label'>Total de Registros</div>
                            <div class='info-value'>{$total}</div>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th style='width: 14%;'>Matrícula</th>
                                <th style='width: 28%;'>Estudiante</th>
                                <th style='width: 18%;'>Carrera</th>
                                <th style='width: 22%;'>Curso</th>
                                <th style='width: 10%; text-align: center;'>Nota</th>
                                <th style='width: 8%; text-align: center;'>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$filas}
                        </tbody>
                    </table>
                    <div class='footer'>
                        <p><strong>Fecha de generación:</strong> {$fechaActual}</p>
                        <p>Este reporte fue generado automáticamente por el Sistema Académico</p>
                    </div>
                </div>
            </body>
            </html>
        ";
    }

    private function generarHtmlReporteDocentes($data) {
        $fechaActual = now()->format('d/m/Y H:i');
        $total = count($data);

        $filas = '';
        foreach ($data as $idx => $row) {
            $row = (array) $row;
            $bg = $idx % 2 === 0 ? '#f8fafc' : '#ffffff';
            $especialidad = $row['especialidad'] ?? '—';
            $filas .= "
                <tr style='background-color: {$bg}; border-bottom: 1px solid #e2e8f0;'>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$row['docente']}</td>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$especialidad}</td>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$row['curso']}</td>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$row['materia']}</td>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$row['periodo']}</td>
                    <td style='padding: 11px 15px; font-size: 13px; text-align: center;'>{$row['totalEstudiantes']}</td>
                </tr>
            ";
        }

        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    * { margin: 0; padding: 0; }
                    body { font-family: Arial, sans-serif; color: #1e293b; line-height: 1.4; }
                    .container { padding: 25px; }
                    .header { border-top: 4px solid #7c3aed; margin-bottom: 25px; }
                    h1 { font-size: 26px; font-weight: bold; margin-bottom: 5px; color: #0f172a; }
                    .subtitle { font-size: 13px; color: #64748b; margin-bottom: 15px; }
                    .info-section { display: flex; gap: 15px; margin-bottom: 25px; }
                    .info-item { flex: 1; background: #f8fafc; padding: 12px 15px; border-radius: 8px; border-left: 3px solid #7c3aed; }
                    .info-label { font-size: 11px; color: #94a3b8; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
                    .info-value { font-size: 14px; color: #1e293b; font-weight: 500; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    thead { background: #7c3aed; color: white; }
                    th { padding: 12px 15px; text-align: left; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
                    td { padding: 11px 15px; font-size: 13px; }
                    .footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Reporte de Docentes</h1>
                        <p class='subtitle'>Listado de docentes y cursos asignados por periodo</p>
                    </div>
                    <div class='info-section'>
                        <div class='info-item'>
                            <div class='info-label'>Total de Registros</div>
                            <div class='info-value'>{$total}</div>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th style='width: 22%;'>Docente</th>
                                <th style='width: 20%;'>Especialidad</th>
                                <th style='width: 20%;'>Curso</th>
                                <th style='width: 20%;'>Materia</th>
                                <th style='width: 10%;'>Periodo</th>
                                <th style='width: 8%; text-align: center;'>Estud.</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$filas}
                        </tbody>
                    </table>
                    <div class='footer'>
                        <p><strong>Fecha de generación:</strong> {$fechaActual}</p>
                        <p>Este reporte fue generado automáticamente por el Sistema Académico</p>
                    </div>
                </div>
            </body>
            </html>
        ";
    }

    private function obtenerDocenteAsignado($materiaId) {
        try {
            $docente = DB::table('curso')
                ->join('usuario', 'curso.idDocente', '=', 'usuario.id')
                ->where('curso.idMateria', $materiaId)
                ->select('usuario.nombre1', 'usuario.apellidoP')
                ->orderBy('curso.id', 'desc')
                ->first();

            if ($docente) {
                return trim("{$docente->nombre1} {$docente->apellidoP}");
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }


    private function generarHtmlReporte($nombreCarrera, $materias) {
        $fechaActual = now()->format('d/m/Y H:i');
        $totalCreditos = array_sum(array_column($materias, 'creditos'));
        $totalMaterias = count($materias);

        $filas = '';
        foreach ($materias as $idx => $materia) {
            $bg = $idx % 2 === 0 ? '#f8fafc' : '#ffffff';
            $filas .= "
                <tr style='background-color: {$bg}; border-bottom: 1px solid #e2e8f0;'>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$materia['codigo']}</td>
                    <td style='padding: 11px 15px; font-size: 13px;'>{$materia['nombre']}</td>
                    <td style='padding: 11px 15px; font-size: 13px; text-align: center;'>{$materia['creditos']}</td>
                    <td style='padding: 11px 15px; font-size: 13px; text-align: center;'>{$materia['semestre']}</td>
                </tr>
            ";
        }

        $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    * { margin: 0; padding: 0; }
                    body { font-family: Arial, sans-serif; color: #1e293b; line-height: 1.4; }
                    .container { padding: 25px; }
                    .header { border-top: 4px solid #7c3aed; margin-bottom: 25px; }
                    h1 { font-size: 26px; font-weight: bold; margin-bottom: 5px; color: #0f172a; }
                    .subtitle { font-size: 13px; color: #64748b; margin-bottom: 15px; }
                    .info-section { display: flex; gap: 15px; margin-bottom: 25px; }
                    .info-item { flex: 1; background: #f8fafc; padding: 12px 15px; border-radius: 8px; border-left: 3px solid #7c3aed; }
                    .info-label { font-size: 11px; color: #94a3b8; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
                    .info-value { font-size: 14px; color: #1e293b; font-weight: 500; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    thead { background: #7c3aed; color: white; }
                    th { padding: 12px 15px; text-align: left; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
                    td { padding: 11px 15px; font-size: 13px; }
                    .footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Reporte de Materias</h1>
                        <p class='subtitle'>Listado completo de materias de la carrera</p>
                    </div>

                    <div class='info-section'>
                        <div class='info-item'>
                            <div class='info-label'>Carrera</div>
                            <div class='info-value'>{$nombreCarrera}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Total de Materias</div>
                            <div class='info-value'>{$totalMaterias}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Total de Créditos</div>
                            <div class='info-value'>{$totalCreditos}</div>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th style='width: 12%;'>Código</th>
                                <th style='width: 55%;'>Nombre</th>
                                <th style='width: 16%; text-align: center;'>Créditos</th>
                                <th style='width: 17%; text-align: center;'>Semestre</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$filas}
                        </tbody>
                    </table>

                    <div class='footer'>
                        <p><strong>Fecha de generación:</strong> {$fechaActual}</p>
                        <p>Este reporte fue generado automáticamente por el Sistema Académico</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        return $html;
    }
}
