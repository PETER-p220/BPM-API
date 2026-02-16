<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Mail\InvoiceMail;

class InvoiceController extends Controller
{
    public function indexAccountant(Request $request)
    {
        $invoices = Invoice::with(['creator', 'department', 'project'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                // Use new fields directly, fallback to legacy fields for old records
                $invoice->invoice_number = $invoice->invoice_number ?: $invoice->ref_number;
                $invoice->client_name = $invoice->client_name ?: '';
                $invoice->client_email = $invoice->client_email ?: '';
                $invoice->client_phone = $invoice->client_phone ?: '';
                $invoice->item_description = $invoice->item_description ?: $invoice->item;
                $invoice->number_of_cars = $invoice->number_of_cars ?: 0;
                $invoice->period_months = $invoice->period_months ?: 0;
                $invoice->uom = $invoice->uom ?: '';
                $invoice->unit_price = $invoice->unit_price ?: 0;
                $invoice->gross_value = $invoice->gross_value ?: 0;
                $invoice->tax_rate = $invoice->tax_rate ?: 18;
                $invoice->tax_amount = $invoice->tax_amount ?: 0;
                $invoice->total_amount = $invoice->total_amount ?: $invoice->amount;
                $invoice->invoice_date = $invoice->invoice_date ?: $invoice->start_date;
                $invoice->due_date = $invoice->due_date ?: $invoice->end_date;
                $invoice->notes = $invoice->notes ?: '';
                $invoice->status = $invoice->status ?: 'draft';
                
                return $invoice;
            });

        return response()->json([
            'success' => true,
            'data' => $invoices
        ]);
    }

    public function storeAccountant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_number' => 'required|string|max:50',
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            'item_description' => 'required|string',
            'number_of_cars' => 'required|numeric|min:0',
            'period_months' => 'required|numeric|min:0',
            'uom' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Calculate totals
        $grossValue = $request->number_of_cars * $request->period_months * $request->unit_price;
        $taxAmount = $grossValue * ($request->tax_rate / 100);
        $totalAmount = $grossValue + $taxAmount;

        // Use new database fields directly
        $data = [
            'invoice_number' => $request->invoice_number,
            'client_name' => $request->client_name,
            'client_email' => $request->client_email,
            'client_phone' => $request->client_phone,
            'item_description' => $request->item_description,
            'number_of_cars' => $request->number_of_cars,
            'period_months' => $request->period_months,
            'uom' => $request->uom,
            'unit_price' => $request->unit_price,
            'gross_value' => $grossValue,
            'tax_rate' => $request->tax_rate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'invoice_date' => $request->invoice_date,
            'due_date' => $request->due_date,
            'notes' => $request->notes,
            'status' => 'draft',
            'created_by' => auth()->id(),
            // Legacy fields for compatibility
            'payment' => '',
            'item' => $request->item_description,
            'ref_number' => $request->invoice_number,
            'amount' => $totalAmount,
            'department_id' => 5,
            'iscreated_by' => auth()->id(),
            'description' => $request->client_name . ' - ' . $request->client_email . ' - ' . $request->client_phone,
            'project_id' => 0,
            'project_name' => '',
            'tender_id' => null,
            'budget' => null,
            'contract' => null,
            'start_date' => $request->invoice_date,
            'end_date' => $request->due_date
        ];

        $invoice = Invoice::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Invoice created successfully',
            'data' => $invoice
        ]);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['creator', 'department', 'project'])
            ->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invoice retrieved successfully',
            'data' => $invoice
        ]);
    }

    public function updateAccountant(Request $request, $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'invoice_number' => 'required|string|max:50',
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            'item_description' => 'required|string',
            'number_of_cars' => 'required|numeric|min:0',
            'period_months' => 'required|numeric|min:0',
            'uom' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['updated_by'] = auth()->id();

        // Calculate totals
        $grossValue = $data['number_of_cars'] * $data['period_months'] * $data['unit_price'];
        $taxAmount = $grossValue * ($data['tax_rate'] / 100);
        $totalAmount = $grossValue + $taxAmount;

        // Use new fields directly
        $updateData = [
            'invoice_number' => $data['invoice_number'],
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'],
            'client_phone' => $data['client_phone'],
            'item_description' => $data['item_description'],
            'number_of_cars' => $data['number_of_cars'],
            'period_months' => $data['period_months'],
            'uom' => $data['uom'],
            'unit_price' => $data['unit_price'],
            'gross_value' => $grossValue,
            'tax_rate' => $data['tax_rate'],
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'notes' => $data['notes'],
            'status' => $data['status'],
            // Legacy fields for compatibility
            'payment' => $invoice->payment,
            'item' => $data['item_description'],
            'ref_number' => $data['invoice_number'],
            'amount' => $totalAmount,
            'department_id' => $invoice->department_id,
            'iscreated_by' => $invoice->iscreated_by,
            'description' => $data['client_name'] . ' - ' . $data['client_email'] . ' - ' . $data['client_phone'],
            'project_id' => $invoice->project_id,
            'project_name' => $data['client_name'],
            'tender_id' => $invoice->tender_id,
            'budget' => $invoice->budget,
            'contract' => $invoice->contract,
            'start_date' => $invoice->invoice_date,
            'end_date' => $invoice->due_date
        ];

        $invoice->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Invoice updated successfully',
            'data' => $invoice
        ]);
    }

    public function destroyAccountant($id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted successfully',
            'data' => $invoice
        ]);
    }

    public function statisticsAccountant()
    {
        $totalInvoices = Invoice::count();
        $paidInvoices = 0; // No status column, default to 0
        $sentInvoices = 0; // No status column, default to 0
        $draftInvoices = $totalInvoices; // All invoices are draft by default
        $totalAmount = Invoice::sum('total_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_invoices' => $totalInvoices,
                'paid_invoices' => 0,
                'sent_invoices' => 0,
                'draft_invoices' => $totalInvoices,
                'total_amount' => $totalAmount
            ]
        ]);
    }

    public function sendAccountantInvoice(Request $request, $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        // Get invoice data with new fields
        $invoiceData = [
            'invoice_number' => $invoice->invoice_number ?: $invoice->ref_number,
            'client_name' => $invoice->client_name ?: '',
            'client_email' => $invoice->client_email ?: '',
            'client_phone' => $invoice->client_phone ?: '',
            'item_description' => $invoice->item_description ?: $invoice->item,
            'number_of_cars' => $invoice->number_of_cars ?: 0,
            'period_months' => $invoice->period_months ?: 0,
            'uom' => $invoice->uom ?: '',
            'unit_price' => $invoice->unit_price ?: 0,
            'gross_value' => $invoice->gross_value ?: 0,
            'tax_rate' => $invoice->tax_rate ?: 18,
            'tax_amount' => $invoice->tax_amount ?: 0,
            'total_amount' => $invoice->total_amount ?: $invoice->amount,
            'invoice_date' => $invoice->invoice_date ?: $invoice->start_date,
            'due_date' => $invoice->due_date ?: $invoice->end_date,
            'notes' => $invoice->notes ?: '',
            'status' => $invoice->status ?: 'draft'
        ];

        // Log invoice data for debugging
        Log::info("Invoice data for #{$invoiceData['invoice_number']}: " . json_encode($invoiceData));

        $validator = Validator::make($request->all(), [
            'send_method' => 'required|in:email,whatsapp,both',
            'message' => 'nullable|string|max:1000'
        ]);

        // Additional validation for phone if WhatsApp is involved
        if ($request->send_method === 'whatsapp' || $request->send_method === 'both') {
            if (empty($invoiceData['client_phone'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number is required for WhatsApp sending',
                    'errors' => ['client_phone' => ['The client phone field is required.']]
                ], 422);
            }
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $sendMethod = $request->send_method;
        $customMessage = $request->message ?? '';
        $results = [];

        Log::info("Attempting to send invoice #{$invoiceData['invoice_number']} via method: {$sendMethod}");

        // Send Email
        if ($sendMethod === 'email' || $sendMethod === 'both') {
            if (!empty($invoiceData['client_email'])) {
                try {
                    $subject = "Invoice #{$invoiceData['invoice_number']} from TERA BPM";
                    Log::info("Sending email to {$invoiceData['client_email']} for invoice #{$invoiceData['invoice_number']}");
                    
                    Mail::to($invoiceData['client_email'])
                        ->send(new InvoiceMail((object) $invoiceData, $subject));
                    
                    $results['email'] = 'sent';
                    Log::info("Invoice {$invoiceData['invoice_number']} sent successfully via email to {$invoiceData['client_email']}");
                } catch (\Exception $e) {
                    $results['email'] = 'failed: ' . $e->getMessage();
                    Log::error("Failed to send invoice {$invoiceData['invoice_number']} via email: " . $e->getMessage());
                }
            } else {
                $results['email'] = 'skipped: no email address provided';
                Log::warning("Invoice {$invoiceData['invoice_number']} email skipped - no email address provided");
            }
        }

        // Send WhatsApp
        if ($sendMethod === 'whatsapp' || $sendMethod === 'both') {
            if (!empty($invoiceData['client_phone'])) {
                try {
                    $whatsappMessage = $this->generateWhatsAppMessage($invoiceData, $customMessage);
                    $response = $this->sendWhatsAppMessage($invoiceData['client_phone'], $whatsappMessage);
                    
                    if ($response['success']) {
                        $results['whatsapp'] = 'sent';
                        Log::info("Invoice {$invoiceData['invoice_number']} sent successfully via WhatsApp to {$invoiceData['client_phone']}");
                        
                        // If URL was returned, include it in results
                        if (isset($response['url'])) {
                            $results['whatsapp_url'] = $response['url'];
                        }
                    } else {
                        $results['whatsapp'] = 'failed: ' . $response['message'];
                        Log::error("Failed to send invoice {$invoiceData['invoice_number']} via WhatsApp: " . $response['message']);
                    }
                } catch (\Exception $e) {
                    $results['whatsapp'] = 'failed: ' . $e->getMessage();
                    Log::error("Failed to send invoice {$invoiceData['invoice_number']} via WhatsApp: " . $e->getMessage());
                }
            } else {
                $results['whatsapp'] = 'skipped: no phone number provided';
                Log::warning("Invoice {$invoiceData['invoice_number']} WhatsApp skipped - no phone number provided");
            }
        }

        // Update invoice status if at least one method succeeded
        $hasSuccess = collect($results)->contains(function($result) {
            return str_starts_with($result, 'sent');
        });

        if ($hasSuccess) {
            $invoice->update(['status' => 'sent']);
            Log::info("Invoice #{$invoiceData['invoice_number']} status updated to 'sent'");
        } else {
            Log::error("Invoice #{$invoiceData['invoice_number']} failed to send via all methods");
        }

        return response()->json([
            'success' => $hasSuccess,
            'message' => $hasSuccess ? 'Invoice sent successfully' : 'Failed to send invoice',
            'results' => $results,
            'debug_info' => [
                'invoice_data' => $invoiceData,
                'send_method' => $sendMethod,
                'has_email' => !empty($invoiceData['client_email']),
                'has_phone' => !empty($invoiceData['client_phone']),
                'custom_message' => $customMessage
            ],
            'data' => $invoice
        ]);
    }

    private function generateWhatsAppMessage($invoice, $customMessage = '')
    {
        $message = "🧾 *INVOICE #{$invoice['invoice_number']}* 🧾\n\n";
        $message .= "📋 *Details:*\n";
        $message .= "• Client: {$invoice['client_name']}\n";
        $message .= "• Description: {$invoice['item_description']}\n";
        $message .= "• Amount: TZS " . number_format($invoice['total_amount'], 2) . "\n";
        $message .= "• Due Date: {$invoice['due_date']}\n\n";
        
        if (!empty($customMessage)) {
            $message .= "💬 *Message:*\n{$customMessage}\n\n";
        }
        
        $message .= "📎 *Note:* Full invoice details have been sent to your email.\n";
        $message .= "🔗 *Portal:* You can view your invoice online.\n\n";
        $message .= "Thank you for your business! 🙏";
        
        return $message;
    }

    private function sendWhatsAppMessage($phone, $message)
    {
        // Remove any non-numeric characters from phone
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present (assuming Tanzania)
        if (!str_starts_with($phone, '255') && (strlen($phone) === 9 || strlen($phone) === 10)) {
            $phone = '255' . ltrim($phone, '0');
        }
        
        try {
            // Option 1: Using WhatsApp Business API (requires setup)
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.whatsapp.token'),
                'Content-Type' => 'application/json',
            ])->post('https://graph.facebook.com/v18.0/' . config('services.whatsapp.phone_id') . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Message sent via WhatsApp API'];
            }

            // Option 2: Fallback to WhatsApp Web URL (user needs to send manually)
            $whatsappUrl = "https://wa.me/{$phone}?text=" . urlencode($message);
            return [
                'success' => true, 
                'message' => 'WhatsApp URL generated - click to send',
                'url' => $whatsappUrl,
                'phone' => $phone
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function markAccountantInvoiceAsPaid(Request $request, $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'payment_reference' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $invoice->update([
            'status' => 'paid',
            'payment' => $request->payment_method,
            'payment_date' => $request->payment_date,
            'payment_reference' => $request->payment_reference
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice marked as paid successfully',
            'data' => $invoice
        ]);
    }

    public function exportInvoicesToExcel()
    {
        $invoices = Invoice::with(['creator', 'department', 'project'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                // Use new fields directly, fallback to legacy fields for old records
                $invoice->invoice_number = $invoice->invoice_number ?: $invoice->ref_number;
                $invoice->client_name = $invoice->client_name ?: '';
                $invoice->client_email = $invoice->client_email ?: '';
                $invoice->client_phone = $invoice->client_phone ?: '';
                $invoice->item_description = $invoice->item_description ?: $invoice->item;
                $invoice->number_of_cars = $invoice->number_of_cars ?: 0;
                $invoice->period_months = $invoice->period_months ?: 0;
                $invoice->uom = $invoice->uom ?: '';
                $invoice->unit_price = $invoice->unit_price ?: 0;
                $invoice->gross_value = $invoice->gross_value ?: 0;
                $invoice->tax_rate = $invoice->tax_rate ?: 18;
                $invoice->tax_amount = $invoice->tax_amount ?: 0;
                $invoice->total_amount = $invoice->total_amount ?: $invoice->amount;
                $invoice->invoice_date = $invoice->invoice_date ?: $invoice->start_date;
                $invoice->due_date = $invoice->due_date ?: $invoice->end_date;
                $invoice->notes = $invoice->notes ?: '';
                $invoice->status = $invoice->status ?: 'draft';
                
                return $invoice;
            });

        $filename = 'invoices_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($invoices) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($file, [
                'Invoice Number',
                'Client Name', 
                'Client Email',
                'Client Phone',
                'Item Description',
                'Number of Cars',
                'Period Months',
                'UOM',
                'Unit Price',
                'Tax Rate',
                'Gross Value',
                'Tax Amount',
                'Total Amount',
                'Invoice Date',
                'Due Date',
                'Notes',
                'Status',
                'Created At'
            ]);
            
            // Add data
            foreach ($invoices as $invoice) {
                fputcsv($file, [
                    $invoice->invoice_number,
                    $invoice->client_name,
                    $invoice->client_email,
                    $invoice->client_phone,
                    $invoice->item_description,
                    $invoice->number_of_cars,
                    $invoice->period_months,
                    $invoice->uom,
                    $invoice->unit_price,
                    $invoice->tax_rate,
                    $invoice->gross_value,
                    $invoice->tax_amount,
                    $invoice->total_amount,
                    $invoice->invoice_date,
                    $invoice->due_date,
                    $invoice->notes,
                    $invoice->status,
                    $invoice->created_at
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportInvoicesToPDF()
    {
        $invoices = Invoice::with(['creator', 'department', 'project'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                // Use new fields directly, fallback to legacy fields for old records
                $invoice->invoice_number = $invoice->invoice_number ?: $invoice->ref_number;
                $invoice->client_name = $invoice->client_name ?: '';
                $invoice->client_email = $invoice->client_email ?: '';
                $invoice->client_phone = $invoice->client_phone ?: '';
                $invoice->item_description = $invoice->item_description ?: $invoice->item;
                $invoice->number_of_cars = $invoice->number_of_cars ?: 0;
                $invoice->period_months = $invoice->period_months ?: 0;
                $invoice->uom = $invoice->uom ?: '';
                $invoice->unit_price = $invoice->unit_price ?: 0;
                $invoice->gross_value = $invoice->gross_value ?: 0;
                $invoice->tax_rate = $invoice->tax_rate ?: 18;
                $invoice->tax_amount = $invoice->tax_amount ?: 0;
                $invoice->total_amount = $invoice->total_amount ?: $invoice->amount;
                $invoice->invoice_date = $invoice->invoice_date ?: $invoice->start_date;
                $invoice->due_date = $invoice->due_date ?: $invoice->end_date;
                $invoice->notes = $invoice->notes ?: '';
                $invoice->status = $invoice->status ?: 'draft';
                
                return $invoice;
            });

        $filename = 'invoices_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Build HTML content
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: BLUE; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .meta { color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #f5f5f5; padding: 10px; text-align: left; border: 1px solid #ddd; font-weight: bold; }
        td { padding: 8px; border: 1px solid #ddd; }
        .number { text-align: right; }
        .status { text-align: center; font-weight: bold; }
        .draft { color: #666; }
        .sent { color: #0066cc; }
        .paid { color: #008800; }
        .cancelled { color: #cc0000; }
    </style>
</head>
<body>
    <h1>TERA TECHNOLOGIES AND ENGINEERING LIMITED</h1>
    <p>Supply, Installation and maintenance of Telecoms networks and Equipments, PABX and Unified Communication, Networking and Connectivity Solutions, CCTV, Access Control Systems, Fire Alarm Systems, Electricaland power systems, and all types of office Automation equipments.</p>
    </br>
    </br>
    <h2 style="color:red">REGISTERED CONTRACTOR IN ELECTRICAL <i style="color:cyan">(CLASS FOUR)__</i> TELECOMS, ICT AND SECURITY SYSTEMS <i style="color:cyan"> (CLASS TWO)</i>
    <p> Office: Mbezi Beach Africana, Plot No. 2283, Block H, Tarangire Street, Bagamoyo Road/African Drive, P.O. Box 31257, Dar es Salaam, Tanzania.</br>
    Tel/Fax: +255 22 2701611, Cell: +255 713 899 309 E-mail: info@teratech.co.tz, Website: www.teratech.co.tz   </p>

    <h3>TAX INVOICE FOR VEHICLE TRACKER INSTALLATION </H3>
    
    
    <table>
        <thead>
            <tr>
                <th>Invoice Number</th>
                <th>Client Name</th>
                <th>Item Description</th>
                <th class="number">Cars</th>
                <th class="number">Period</th>
                <th class="number">Unit Price</th>
                <th class="number">Total Amount</th>
                <th class="number">Invoice Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($invoices as $invoice) {
            $statusClass = $invoice->status ?? 'draft';
            $html .= '
            <tr>
                <td>' . htmlspecialchars($invoice->invoice_number) . '</td>
                <td>' . htmlspecialchars($invoice->client_name) . '</td>
                <td>' . htmlspecialchars(substr($invoice->item_description, 0, 50)) . '</td>
                <td class="number">' . $invoice->number_of_cars . '</td>
                <td class="number">' . $invoice->period_months . '</td>
                <td class="number">TZS ' . number_format($invoice->unit_price, 2) . '</td>
                <td class="number">TZS ' . number_format($invoice->total_amount, 2) . '</td>
                <td class="number">' . $invoice->invoice_date . '</td>
                <td class="status ' . $statusClass . '">' . ucfirst($statusClass) . '</td>
            </tr>';
        }

        $html .= '
        </tbody>
    </table>
        <div class="meta">Generated on ' . date('Y-m-d H:i:s') . '</div>
</body>
</html>';

        // Use DomPDF if available, otherwise fallback to simple approach
        if (class_exists('\Dompdf\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } else {
            // Fallback: return HTML as file (user can save as PDF)
            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => 'attachment; filename="' . str_replace('.pdf', '.html', $filename) . '"'
            ]);
        }
    }
}
