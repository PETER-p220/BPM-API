<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceMail;

try {
    $testData = (object) [
        'invoice_number' => 'TEST-001',
        'client_name' => 'Test Client',
        'client_email' => 'test@example.com',
        'client_phone' => '+255123456789',
        'item_description' => 'Test Item Description',
        'number_of_cars' => 2,
        'period_months' => 3,
        'uom' => 'per_month',
        'unit_price' => 100000,
        'gross_value' => 600000,
        'tax_rate' => 18,
        'tax_amount' => 108000,
        'total_amount' => 708000,
        'invoice_date' => '2026-02-14',
        'due_date' => '2026-03-14',
        'notes' => 'Test notes',
        'status' => 'draft'
    ];

    Mail::to('test@example.com')
        ->send(new InvoiceMail($testData, 'Test Invoice from TERA BPM'));
    
    echo "Test email sent successfully!\n";
    
} catch (\Exception $e) {
    echo "Error sending email: " . $e->getMessage() . "\n";
}
