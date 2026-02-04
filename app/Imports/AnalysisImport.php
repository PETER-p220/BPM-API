<?php

namespace App\Imports;

use App\Models\Analysis;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AnalysisImport implements ToModel
{
    private $projectId;

    public function __construct($projectId)
    {
        $this->projectId = $projectId;
        Log::info('AnalysisImport initialized', [
            'project_id' => $projectId
        ]);
    }

    public function model(array $row)
    {
        Log::info('Raw row data', ['row' => $row]);

        // Skip header rows and empty rows
        if (empty(array_filter($row)) || $row[0] === 'S/N' || $row[2] === 'Q_QTY') {
            Log::info('Skipping header or empty row', ['row' => $row]);
            return null;
        }

        // Map columns by index (based on document structure)
        $data = [
            'project_id' => $this->projectId,
            'user_id' => Auth::id(),
            'serial_number' => $row[0] ?? null, // Column A: S/N
            'item_description' => $row[1] ?? null, // Column B: ITEM DESCRIPTIONS
            'quoted_quantity' => is_numeric($row[2]) ? (int)$row[2] : null, // Column C: Q_QTY
            'quoted_unit' => $row[3] ?? null, // Column D: Q_UNIT
            'quoted_rate' => is_numeric($row[4]) ? (float)$row[4] : null, // Column E: Q_Rate(TZS)
            'quoted_amount' => is_numeric($row[5]) ? (float)$row[5] : null, // Column F: Q_Amount(TZS)
            'quantity' => is_numeric($row[6]) ? (int)$row[6] : null, // Column G: QTY
            'rate' => is_numeric($row[7]) ? (float)$row[7] : null, // Column H: Rate(TZS)
            'amount' => is_numeric($row[8]) ? (float)$row[8] : null, // Column I: Amount(TZS)
            'source' => $row[9] ?? null, // Column J: SOURCE
            'urgent_status' => $row[10] ?? null, // Column K: URGENT STATUS
            'total_amount_vat_excl' => is_numeric($row[11]) ? (float)$row[11] : null, // Column L: TOTAL AMOUNT(VAT EXCL)
            'total_amount_vat_incl' => is_numeric($row[12]) ? (float)$row[12] : null, // Column M: TOTAL AMOUNT(VAT INCL)
            'total_amount_needed' => is_numeric($row[13]) ? (float)$row[13] : null, // Column N: TOTAL AMOUNT NEEDED IN THIS REQUEST
            'site_contingency' => is_numeric($row[14]) ? (float)$row[14] : null, // Column O: SITE CONTIGENCY
            'total_investment' => is_numeric($row[15]) ? (float)$row[15] : null, // Column P: TOTAL INVESTMENT
            'projected_profit' => is_numeric($row[16]) ? (float)$row[16] : null, // Column Q: PROJECTED PROFIT
            'projected_profit_percentage' => is_numeric($row[17]) ? (float)$row[17] : null, // Column R: PROJECTED PROFIT IN %
        ];

        // Skip if no meaningful data
        if (empty($data['serial_number']) && empty($data['item_description'])) {
            Log::info('Skipping row with no meaningful data', ['data' => $data]);
            return null;
        }

        Log::info('Mapped data before saving', ['data' => $data]);

        try {
            $analysis = new Analysis($data);
            $analysis->save();
            Log::info('Analysis record saved', ['analysis_id' => $analysis->analysis_id, 'data' => $data]);
            return $analysis;
        } catch (\Exception $e) {
            Log::error('Failed to save Analysis record', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}