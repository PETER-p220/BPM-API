<?php

namespace App\Exports;

use App\Models\FinancialRecord;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialRecordsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        return $this->records;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Date',
            'Description',
            'Reference',
            'Type',
            'Category',
            'Amount',
            'Status',
            'Created By',
            'Approved By',
            'Approved At',
            'Created At'
        ];
    }

    public function map($record): array
    {
        return [
            $record->id,
            $record->date,
            $record->description,
            $record->reference ?? 'N/A',
            ucfirst($record->type),
            ucfirst($record->category),
            number_format($record->amount, 2),
            ucfirst($record->status),
            $record->creator ? $record->creator->name : 'N/A',
            $record->approver ? $record->approver->name : 'N/A',
            $record->approved_at ? $record->approved_at->format('Y-m-d H:i:s') : 'N/A',
            $record->created_at->format('Y-m-d H:i:s')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => 'E8E8E8'
                    ]
                ]
            ]
        ];
    }

    public function title(): string
    {
        return 'Financial Records';
    }
}
