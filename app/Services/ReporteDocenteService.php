<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Materia;
use App\Models\Inscripcion;
use App\Models\Calificacion;
use App\Models\PeriodoAcademico;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ReporteDocenteService
{
    public function generarPreview($docente, array $filtros)
    {
        return match ($filtros['tipo']) {
            'periodo_academico' => $this->reportePeriodoAcademico($docente, $filtros),
            'estudiantes' => $this->reporteEstudiantes($docente, $filtros),
            'materias' => $this->reporteMaterias($docente, $filtros),
            'cursos' => $this->reporteCursos($docente, $filtros),
            'calificaciones' => $this->reporteCalificaciones($docente, $filtros),
        };
    }

    private function reportePeriodoAcademico($docente, array $filtros)
    {
        return PeriodoAcademico::query()
            ->when($filtros['periodo_id'] ?? null, function ($query, $periodoId) {
                $query->where('id', $periodoId);
            })
            ->get();
    }

    private function reporteEstudiantes($docente, array $filtros)
    {
        return Inscripcion::query()
            ->with(['estudiante', 'curso.materia', 'curso.periodoAcademico', 'historialAcademico'])
            ->whereHas('curso', function ($query) use ($docente, $filtros) {
                $query->where('idDocente', $docente->id);

                if (!empty($filtros['curso_id'])) {
                    $query->where('id', $filtros['curso_id']);
                }

                if (!empty($filtros['materia_id'])) {
                    $query->where('idMateria', $filtros['materia_id']);
                }

                if (!empty($filtros['periodo_id'])) {
                    $query->where('idPeriodoAcademico', $filtros['periodo_id']);
                }
            })
            ->when($filtros['estudiante_id'] ?? null, function ($query, $estudianteId) {
                $query->where('estudiante_id', $estudianteId);
            })
            ->when($filtros['estado'] ?? null, function ($query, $estado) {
                $query->where('estado', $estado);
            })
            ->when($filtros['condicion'] ?? null, function ($query, $condicion) {
                $query->whereHas('historialAcademico', function ($q) use ($condicion) {
                    $q->where('estado', $condicion);
                });
            })
            ->when($filtros['nombre'] ?? null, function ($query, $nombre) {
                $query->whereHas('estudiante', function ($q) use ($nombre) {
                    $q->whereHas('usuario', function ($u) use ($nombre) {
                        $u->whereRaw("CONCAT(nombre1, ' ', apellidoP) LIKE ?", ["%{$nombre}%"]);
                    });
                });
            })
            ->where('estadoA', 1)
            ->get();
    }

    private function reporteMaterias($docente, array $filtros)
    {
        return Materia::query()
            ->whereHas('cursos', function ($query) use ($docente, $filtros) {
                $query->where('idDocente', $docente->id);

                if (!empty($filtros['periodo_id'])) {
                    $query->where('idPeriodoAcademico', $filtros['periodo_id']);
                }
            })
            ->when($filtros['materia_id'] ?? null, function ($query, $materiaId) {
                $query->where('id', $materiaId);
            })
            ->when($filtros['carrera_id'] ?? null, function ($query, $carreraId) {
                $query->whereHas('pensum', function ($q) use ($carreraId) {
                    $q->where('idCarrera', $carreraId);
                });
            })
            ->when($filtros['semestre'] ?? null, function ($query, $semestre) {
                $query->where('semestre', $semestre);
            })
            ->where('estadoA', 1)
            ->get();
    }

    private function reporteCursos($docente, array $filtros)
    {
        return Curso::query()
            ->with(['materia', 'periodoAcademico', 'horario'])
            ->where('idDocente', $docente->id)
            ->when($filtros['curso_id'] ?? null, function ($query, $cursoId) {
                $query->where('id', $cursoId);
            })
            ->when($filtros['materia_id'] ?? null, function ($query, $materiaId) {
                $query->where('idMateria', $materiaId);
            })
            ->when($filtros['periodo_id'] ?? null, function ($query, $periodoId) {
                $query->where('idPeriodoAcademico', $periodoId);
            })
            ->when($filtros['turno'] ?? null, function ($query, $turno) {
                $query->whereHas('horarios', function ($q) use ($turno) {
                    $q->where('turno', $turno);
                });
            })
            ->where('estadoA', 1)
            ->get();
    }

    private function reporteCalificaciones($docente, array $filtros)
    {
        return Calificacion::query()
            ->with(['estudiante', 'inscripcion.curso.materia', 'inscripcion.curso.periodoAcademico', 'materia'])
            ->whereHas('inscripcion.curso', function ($query) use ($docente, $filtros) {
                $query->where('idDocente', $docente->id);

                if (!empty($filtros['curso_id'])) {
                    $query->where('id', $filtros['curso_id']);
                }

                if (!empty($filtros['materia_id'])) {
                    $query->where('idMateria', $filtros['materia_id']);
                }

                if (!empty($filtros['periodo_id'])) {
                    $query->where('idPeriodoAcademico', $filtros['periodo_id']);
                }
            })
            ->when($filtros['estudiante_id'] ?? null, function ($query, $estudianteId) {
                $query->where('idEstudiante', $estudianteId);
            })
            ->when($filtros['nombre'] ?? null, function ($query, $nombre) {
                $query->whereHas('estudiante', function ($q) use ($nombre) {
                    $q->whereHas('usuario', function ($u) use ($nombre) {
                        $u->whereRaw("CONCAT(nombre1, ' ', apellidoP) LIKE ?", ["%{$nombre}%"]);
                    });
                });
            })
            ->when(isset($filtros['nota_min']), function ($query) use ($filtros) {
                $query->where('notaFinal', '>=', $filtros['nota_min']);
            })
            ->when(isset($filtros['nota_max']), function ($query) use ($filtros) {
                $query->where('notaFinal', '<=', $filtros['nota_max']);
            })
            ->when($filtros['estado'] ?? null, function ($query, $estado) {
                $query->where('estado', $estado);
            })
            ->where('estadoA', 1)
            ->get()
            ->map(function ($c) {
                $curso = $c->inscripcion?->curso;
                return [
                    'id' => $c->id,
                    'estudiante' => $c->estudiante?->usuario ? trim("{$c->estudiante->usuario->nombre1} {$c->estudiante->usuario->apellidoP}") : '—',
                    'curso' => $curso ? [
                        'materia' => ['nombre' => $curso->materia?->nombre],
                        'periodoAcademico' => ['codigo' => $curso->periodoAcademico?->codigo],
                    ] : null,
                    'materia' => $c->materia?->nombre,
                    'notaFinal' => $c->notaFinal,
                    'estado' => $c->estado,
                ];
            });
    }

    public function exportarPdf($docente, array $filtros)
    {
        $tipo = $filtros['tipo'];
        $data = $this->generarPreview($docente, $filtros);

        $html = $this->generarHtml($tipo, $data);

        $pdf = Pdf::loadHtml($html)
            ->setPaper('a4', 'landscape')
            ->setOption('margin-top', 15)
            ->setOption('margin-bottom', 15)
            ->setOption('margin-left', 15)
            ->setOption('margin-right', 15);

        return $pdf->download("reporte_{$tipo}.pdf");
    }

    public function exportarExcel($docente, array $filtros)
    {
        $tipo = $filtros['tipo'];
        $data = $this->generarPreview($docente, $filtros);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte');

        $this->llenarExcel($tipo, $sheet, $data);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', "attachment; filename=\"reporte_{$tipo}.xlsx\"");
    }

    private function llenarExcel($tipo, $sheet, $data)
    {
        $titulos = [
            'periodo_academico' => 'Reporte de Periodo Académico',
            'estudiantes' => 'Reporte de Estudiantes',
            'materias' => 'Reporte de Materias',
            'cursos' => 'Reporte de Cursos',
            'calificaciones' => 'Reporte de Calificaciones',
        ];
        $titulo = $titulos[$tipo] ?? 'Reporte Docente';

        $sheet->setCellValue('A1', $titulo);
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $items = $data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array) $data;

        $headers = match ($tipo) {
            'estudiantes' => ['ID', 'Estudiante', 'Curso', 'Periodo', 'Estado'],
            'materias' => ['Código', 'Nombre', 'Créditos', 'Semestre'],
            'cursos' => ['ID', 'Materia', 'Periodo', 'Horario', 'Estado'],
            'periodo_academico' => ['ID', 'Código', 'Nombre', 'Fecha Inicio', 'Fecha Fin'],
            default => ['Estudiante', 'Curso', 'Nota Final', 'Estado'],
        };

        // Headers at row 2, styled
        $headerRow = 2;
        $sheet->fromArray($headers, null, 'A' . $headerRow);
        $lastCol = chr(64 + count($headers));
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0369a1']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'e2e8f0']]],
        ];
        for ($col = 'A'; $col <= $lastCol; $col++) {
            $sheet->getStyle($col . $headerRow)->applyFromArray($headerStyle);
        }

        // Data starting at row 3
        $fila = 3;
        foreach ($items as $item) {
            $i = (array) $item;
            switch ($tipo) {
                case 'estudiantes':
                    $est = (array) ($i['estudiante'] ?? []);
                    $curso = (array) ($i['curso'] ?? []);
                    $mat = (array) ($curso['materia'] ?? []);
                    $per = (array) ($curso['periodo_academico'] ?? []);
                    $sheet->setCellValue('A' . $fila, $i['id'] ?? '—');
                    $sheet->setCellValue('B' . $fila, $est['nombre'] ?? '—');
                    $sheet->setCellValue('C' . $fila, $mat['nombre'] ?? '—');
                    $sheet->setCellValue('D' . $fila, $per['codigo'] ?? '—');
                    $sheet->setCellValue('E' . $fila, $i['estado'] ?? '—');
                    break;
                case 'materias':
                    $sheet->setCellValue('A' . $fila, $i['codigo'] ?? '—');
                    $sheet->setCellValue('B' . $fila, $i['nombre'] ?? '—');
                    $sheet->setCellValue('C' . $fila, $i['creditos'] ?? '—');
                    $sheet->setCellValue('D' . $fila, ($i['semestre'] ?? '') . '°');
                    break;
                case 'cursos':
                    $mat = (array) ($i['materia'] ?? []);
                    $per = (array) ($i['periodo_academico'] ?? []);
                    $sheet->setCellValue('A' . $fila, $i['id'] ?? '—');
                    $sheet->setCellValue('B' . $fila, $mat['nombre'] ?? '—');
                    $sheet->setCellValue('C' . $fila, $per['codigo'] ?? '—');
                    $sheet->setCellValue('D' . $fila, $i['codigo_grupo'] ?? '—');
                    $sheet->setCellValue('E' . $fila, ($i['estado_a'] ?? 1) ? 'Activo' : 'Inactivo');
                    break;
                case 'periodo_academico':
                    $sheet->setCellValue('A' . $fila, $i['id'] ?? '—');
                    $sheet->setCellValue('B' . $fila, $i['codigo'] ?? '—');
                    $sheet->setCellValue('C' . $fila, $i['nombre'] ?? '—');
                    $sheet->setCellValue('D' . $fila, $i['fecha_inicio'] ?? '—');
                    $sheet->setCellValue('E' . $fila, $i['fecha_fin'] ?? '—');
                    break;
                default:
                    $est = (array) ($i['estudiante'] ?? []);
                    $curso = (array) ($i['curso'] ?? []);
                    $mat = (array) ($curso['materia'] ?? []);
                    $sheet->setCellValue('A' . $fila, $est['nombre'] ?? '—');
                    $sheet->setCellValue('B' . $fila, $mat['nombre'] ?? '—');
                    $sheet->setCellValue('C' . $fila, $i['nota_final'] ?? ($i['notaFinal'] ?? '—'));
                    $sheet->setCellValue('D' . $fila, $i['estado'] ?? '—');
                    break;
            }
            $fila++;
        }

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(16);
        if (isset($headers[4])) $sheet->getColumnDimension('E')->setWidth(16);
    }

    private function generarHtml($tipo, $data)
    {
        $fecha = now()->format('d/m/Y H:i');
        $items = $data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array) $data;
        $total = count($items);
        $color = '#0369a1';

        $titulos = [
            'periodo_academico' => 'Reporte de Periodo Académico',
            'estudiantes' => 'Reporte de Estudiantes',
            'materias' => 'Reporte de Materias',
            'cursos' => 'Reporte de Cursos',
            'calificaciones' => 'Reporte de Calificaciones',
        ];
        $titulo = $titulos[$tipo] ?? 'Reporte Docente';

        $filas = '';

        foreach ($items as $idx => $item) {
            $i = (array) $item;
            $bg = $idx % 2 === 0 ? '#f8fafc' : '#ffffff';

            switch ($tipo) {
                case 'estudiantes':
                    $est = (array) ($i['estudiante'] ?? []);
                    $curso = (array) ($i['curso'] ?? []);
                    $mat = (array) ($curso['materia'] ?? []);
                    $per = (array) ($curso['periodo_academico'] ?? []);
                    $filas .= "<tr style='background:{$bg};border-bottom:1px solid #e2e8f0;'>
                        <td style='padding:9px 14px;font-size:13px;'>{$i['id']}</td>
                        <td style='padding:9px 14px;font-size:13px;'>" . ($est['nombre'] ?? '—') . "</td>
                        <td style='padding:9px 14px;font-size:13px;'>" . ($mat['nombre'] ?? '—') . "</td>
                        <td style='padding:9px 14px;font-size:13px;'>" . ($per['codigo'] ?? '—') . "</td>
                        <td style='padding:9px 14px;font-size:13px;text-align:center;'>{$i['estado']}</td>
                    </tr>";
                    break;
                case 'materias':
                    $filas .= "<tr style='background:{$bg};border-bottom:1px solid #e2e8f0;'>
                        <td style='padding:9px 14px;font-size:13px;'>{$i['codigo']}</td>
                        <td style='padding:9px 14px;font-size:13px;'>{$i['nombre']}</td>
                        <td style='padding:9px 14px;font-size:13px;text-align:center;'>{$i['creditos']}</td>
                        <td style='padding:9px 14px;font-size:13px;text-align:center;'>{$i['semestre']}°</td>
                    </tr>";
                    break;
                case 'cursos':
                    $mat = (array) ($i['materia'] ?? []);
                    $per = (array) ($i['periodo_academico'] ?? []);
                    $estadoCurso = ($i['estado_a'] ?? 1) ? 'Activo' : 'Inactivo';
                    $filas .= "<tr style='background:{$bg};border-bottom:1px solid #e2e8f0;'>
                        <td style='padding:9px 14px;font-size:13px;'>{$i['id']}</td>
                        <td style='padding:9px 14px;font-size:13px;'>" . ($mat['nombre'] ?? '—') . "</td>
                        <td style='padding:9px 14px;font-size:13px;'>" . ($per['codigo'] ?? '—') . "</td>
                        <td style='padding:9px 14px;font-size:13px;'>{$i['codigo_grupo']}</td>
                        <td style='padding:9px 14px;font-size:13px;text-align:center;'>{$estadoCurso}</td>
                    </tr>";
                    break;
                case 'periodo_academico':
                    $filas .= "<tr style='background:{$bg};border-bottom:1px solid #e2e8f0;'>
                        <td style='padding:9px 14px;font-size:13px;'>{$i['id']}</td>
                        <td style='padding:9px 14px;font-size:13px;'>{$i['codigo']}</td>
                        <td style='padding:9px 14px;font-size:13px;'>{$i['nombre']}</td>
                        <td style='padding:9px 14px;font-size:13px;'>{$i['fecha_inicio']}</td>
                        <td style='padding:9px 14px;font-size:13px;'>{$i['fecha_fin']}</td>
                    </tr>";
                    break;
                case 'calificaciones':
                default:
                    $est = (array) ($i['estudiante'] ?? []);
                    $curso = (array) ($i['curso'] ?? []);
                    $mat = (array) ($curso['materia'] ?? []);
                    $nota = $i['nota_final'] ?? ($i['notaFinal'] ?? '—');
                    $filas .= "<tr style='background:{$bg};border-bottom:1px solid #e2e8f0;'>
                        <td style='padding:9px 14px;font-size:13px;'>" . ($est['nombre'] ?? '—') . "</td>
                        <td style='padding:9px 14px;font-size:13px;'>" . ($mat['nombre'] ?? '—') . "</td>
                        <td style='padding:9px 14px;font-size:13px;text-align:center;font-weight:700;'>{$nota}</td>
                        <td style='padding:9px 14px;font-size:13px;text-align:center;'>{$i['estado']}</td>
                    </tr>";
                    break;
            }
        }

        $infoLabel = match ($tipo) {
            'periodo_academico' => 'Total Periodos',
            'estudiantes' => 'Total Estudiantes',
            'materias' => 'Total Materias',
            'cursos' => 'Total Cursos',
            'calificaciones' => 'Total Calificaciones',
            default => 'Total Registros',
        };

        $headers = match ($tipo) {
            'estudiantes' => ['ID', 'Estudiante', 'Curso', 'Periodo', 'Estado'],
            'materias' => ['Código', 'Nombre', 'Créditos', 'Semestre'],
            'cursos' => ['ID', 'Materia', 'Periodo', 'Horario', 'Estado'],
            'periodo_academico' => ['ID', 'Código', 'Nombre', 'Fecha Inicio', 'Fecha Fin'],
            default => ['Estudiante', 'Curso', 'Nota Final', 'Estado'],
        };

        $thHtml = '';
        foreach ($headers as $h) {
            $thHtml .= "<th style='padding:10px 14px;font-size:11px;font-weight:700;color:#ffffff;background:{$color};text-transform:uppercase;letter-spacing:0.4px;white-space:nowrap;'>{$h}</th>";
        }

        if (empty($filas)) {
            $colspan = count($headers);
            $filas = "<tr><td colspan='{$colspan}' style='padding:20px;text-align:center;color:#94a3b8;'>No hay datos disponibles</td></tr>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$titulo}</title>
            <style>
                *{margin:0;padding:0;box-sizing:border-box;}
                body{font-family:Arial,sans-serif;color:#1e293b;line-height:1.4;padding:25px 30px;}
                .header{border-top:4px solid {$color};margin-bottom:20px;padding-top:15px;}
                h1{font-size:22px;font-weight:700;color:#0f172a;margin-bottom:4px;}
                .subtitle{font-size:12px;color:#64748b;margin-bottom:18px;}
                .info-grid{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
                .info-item{flex:1;min-width:140px;background:#f8fafc;padding:10px 14px;border-radius:6px;border-left:3px solid {$color};}
                .info-label{font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;}
                .info-value{font-size:13px;color:#1e293b;font-weight:500;}
                table{width:100%;border-collapse:collapse;margin-top:12px;}
                td{padding:9px 14px;font-size:13px;border-bottom:1px solid #e2e8f0;}
                .footer{margin-top:22px;padding-top:12px;border-top:1px solid #e2e8f0;font-size:10px;color:#64748b;}
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>{$titulo}</h1>
                <p class='subtitle'>Documento generado el {$fecha}</p>
            </div>
            <div class='info-grid'>
                <div class='info-item'>
                    <div class='info-label'>{$infoLabel}</div>
                    <div class='info-value'>{$total}</div>
                </div>
            </div>
            <table>
                <thead><tr>{$thHtml}</tr></thead>
                <tbody>{$filas}</tbody>
            </table>
            <div class='footer'>
                <p><strong>Fecha de generación:</strong> {$fecha}</p>
                <p>Este reporte fue generado automáticamente por el Sistema Académico</p>
            </div>
        </body>
        </html>
        ";
    }
}