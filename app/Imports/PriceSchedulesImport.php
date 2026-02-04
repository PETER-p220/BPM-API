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
        Log::info('Raw row data', ['row' => $row]);

        if (empty(array_filter($row)) || $row[0] === 'S/N' || $row[2] === 'Q_QTY') {
            Log::info('Skipping header or empty row', ['row' => $row]);
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
            'amount' => is_numeric($row[8]) ? (float)$row[8] : null,
            'source' => $row[9] ?? null,
            'urgent_status' => $row[10] ?? null,
            'total_amount_vat_excl' => is_numeric($row[11]) ? (float)$row[11] : null,
            'total_amount_vat_incl' => is_numeric($row[12]) ? (float)$row[12] : null,
            'total_amount_needed' => is_numeric($row[13]) ? (float)$row[13] : null,
            'site_contingency' => is_numeric($row[14]) ? (float)$row[14] : null,
            'total_investment' => is_numeric($row[15]) ? (float)$row[15] : null,
            'projected_profit' => is_numeric($row[16]) ? (float)$row[16] : null,
            'projected_profit_percentage' => is_numeric($row[17]) ? (float)$row[17] : null,
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