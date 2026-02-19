<?php

namespace App\Imports;

use App\Models\PriceSchedule;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PriceSchedulesImport implements ToModel
{
    private $tenderId;

    public function __construct($tenderId)
    {
        $this->tenderId = $tenderId;
        Log::info('PriceSchedulesImport initialized', [
            'tender_id' => $tenderId
        ]);
    }

    public function model(array $row)
    {
        Log::info('Raw row data', ['row' => $row, 'count' => count($row)]);

        if (empty(array_filter($row)) || $row[0] === 'S/N' || $row[2] === 'Q_QTY') {
            Log::info('Skipping header or empty row', ['row' => $row]);
            return null;
        }

        // Check if row has minimum required columns (at least 8 columns for index 7 to exist)
        if (count($row) < 8) {
            Log::warning('Row has insufficient columns, skipping', ['row' => $row, 'column_count' => count($row)]);
            return null;
        }

        $data = [
            'tender_id' => $this->tenderId,
            'user_id' => Auth::id(),
            'serial_number' => $row[0] ?? null,
            'item_description' => $row[1] ?? null,
            'quoted_quantity' => is_numeric($row[2]) ? (int)$row[2] : null,
            'quoted_unit' => $row[3] ?? null,
            'quoted_rate' => is_numeric($row[4]) ? (float)$row[4] : null,
            'quoted_amount' => is_numeric($row[5]) ? (float)$row[5] : null,
            'quantity' => is_numeric($row[6]) ? (int)$row[6] : null,
            'rate' => is_numeric($row[7]) ? (float)$row[7] : null,
            'amount' => isset($row[8]) && is_numeric($row[8]) ? (float)$row[8] : null,
            'source' => isset($row[9]) ? $row[9] : null,
            'urgent_status' => isset($row[10]) ? $row[10] : null,
            'total_amount_vat_excl' => isset($row[11]) && is_numeric($row[11]) ? (float)$row[11] : null,
            'total_amount_vat_incl' => isset($row[12]) && is_numeric($row[12]) ? (float)$row[12] : null,
            'total_amount_needed' => isset($row[13]) && is_numeric($row[13]) ? (float)$row[13] : null,
            'site_contingency' => isset($row[14]) && is_numeric($row[14]) ? (float)$row[14] : null,
            'total_investment' => isset($row[15]) && is_numeric($row[15]) ? (float)$row[15] : null,
            'projected_profit' => isset($row[16]) && is_numeric($row[16]) ? (float)$row[16] : null,
            'projected_profit_percentage' => isset($row[17]) && is_numeric($row[17]) ? (float)$row[17] : null,
        ];

        if (empty($data['serial_number']) && empty($data['item_description'])) {
            Log::info('Skipping row with no meaningful data', ['data' => $data]);
            return null;
        }

        Log::info('Mapped data before saving', ['data' => $data]);

        try {
            $priceSchedule = new PriceSchedule($data);
            $priceSchedule->save();
            Log::info('Price schedule record saved', ['price_schedule_id' => $priceSchedule->price_schedule_id, 'data' => $data]);
            return $priceSchedule;
        } catch (\Exception $e) {
            Log::error('Failed to save PriceSchedule record', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}