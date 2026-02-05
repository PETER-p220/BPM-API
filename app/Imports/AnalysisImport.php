<?php

namespace App\Imports;

use App\Models\Analysis;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AnalysisImport implements ToModel, WithHeadingRow
{
    private $projectId;
    private $processedRows = [];

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

        // Skip empty rows
        if (empty(array_filter($row))) {
            Log::info('Skipping empty row', ['row' => $row]);
            return null;
        }

        // Create a unique key for this row to detect duplicates
        $rowKey = ($row['serial_number'] ?? '') . '|' . ($row['item_description'] ?? '') . '|' . ($row['quantity'] ?? '');
        
        // Skip if we've already processed this exact row
        if (isset($this->processedRows[$rowKey])) {
            Log::info('Skipping duplicate row', ['row_key' => $rowKey]);
            return null;
        }
        
        $this->processedRows[$rowKey] = true;

        // Map columns by header name (more reliable than index)
        $data = [
            'project_id' => $this->projectId,
            'user_id' => Auth::id(),
            'serial_number' => $row['serial_number'] ?? $row['s_n'] ?? $row['sn'] ?? null,
            'item_description' => $row['item_description'] ?? $row['item_descriptions'] ?? $row['item'] ?? null,
            'quoted_quantity' => is_numeric($row['quoted_quantity'] ?? null) ? (int)$row['quoted_quantity'] : null,
            'quoted_unit' => $row['quoted_unit'] ?? $row['q_unit'] ?? null,
            'quoted_rate' => is_numeric($row['quoted_rate'] ?? null) ? (float)$row['quoted_rate'] : null,
            'quoted_amount' => is_numeric($row['quoted_amount'] ?? null) ? (float)$row['quoted_amount'] : null,
            'quantity' => is_numeric($row['quantity'] ?? $row['qty'] ?? null) ? (int)($row['quantity'] ?? $row['qty']) : null,
            'rate' => is_numeric($row['rate'] ?? $row['rate_tzs'] ?? null) ? (float)($row['rate'] ?? $row['rate_tzs']) : null,
            'amount' => is_numeric($row['amount'] ?? $row['amount_tzs'] ?? null) ? (float)($row['amount'] ?? $row['amount_tzs']) : null,
            'source' => $row['source'] ?? null,
            'urgent_status' => $row['urgent_status'] ?? $row['urgent'] ?? null,
            'total_amount_vat_excl' => is_numeric($row['total_amount_vat_excl'] ?? null) ? (float)$row['total_amount_vat_excl'] : null,
            'total_amount_vat_incl' => is_numeric($row['total_amount_vat_incl'] ?? null) ? (float)$row['total_amount_vat_incl'] : null,
            'total_amount_needed' => is_numeric($row['total_amount_needed'] ?? null) ? (float)$row['total_amount_needed'] : null,
            'site_contingency' => is_numeric($row['site_contingency'] ?? $row['site_contigency'] ?? null) ? (float)$row['site_contingency'] ?? $row['site_contigency'] : null,
            'total_investment' => is_numeric($row['total_investment'] ?? null) ? (float)$row['total_investment'] : null,
            'projected_profit' => is_numeric($row['projected_profit'] ?? null) ? (float)$row['projected_profit'] : null,
            'projected_profit_percentage' => is_numeric($row['projected_profit_percentage'] ?? null) ? (float)$row['projected_profit_percentage'] : null,
        ];

        // Skip if no meaningful data - more lenient check
        if (empty($data['serial_number']) && empty($data['item_description']) && empty($data['quantity'])) {
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