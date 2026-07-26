<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class MateriasPorCarreraExport implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    protected $nombreCarrera;
    protected $materias;

    public function __construct($nombreCarrera, $materias)
    {
        $this->nombreCarrera = $nombreCarrera;
        $this->materias = $materias;
    }

    public function collection()
    {
        $items = [];
        foreach ($this->materias as $materia) {
            $items[] = [
                'codigo' => $materia['codigo'],
                'nombre' => $materia['nombre'],
                'creditos' => $materia['creditos'],
                'semestre' => $materia['semestre'],
                'docente' => $materia['docente_asignado'],
            ];
        }
        return collect($items);
    }

    public function headings(): array
    {
        return ['Código', 'Nombre', 'Créditos', 'Semestre', 'Docente Asignado'];
    }

    public function title(): string
    {
        return 'Materias';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '7c3aed']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ],
        ];
    }
}
