<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Mail\InvoiceMail;

class InvoiceController extends Controller
{
    // ═══════════════════════════════════════════════════════════════
    //  SHARED HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Normalise an Invoice model, filling new fields from legacy
     * fallbacks so the rest of the code always sees consistent data.
     */
    private function normalise(Invoice $invoice): Invoice
    {
        $invoice->invoice_number   = $invoice->invoice_number   ?: $invoice->ref_number;
        $invoice->title            = $invoice->title            ?: '';
        $invoice->client_name      = $invoice->client_name      ?: '';
        $invoice->client_email     = $invoice->client_email     ?: '';
        $invoice->client_phone     = $invoice->client_phone     ?: '';
        $invoice->item_description = $invoice->item_description ?: $invoice->item;
        $invoice->number_of_cars   = $invoice->number_of_cars   ?: 0;
        $invoice->period_months    = $invoice->period_months    ?: 0;
        $invoice->uom              = $invoice->uom              ?: '';
        $invoice->unit_price       = $invoice->unit_price       ?: 0;
        $invoice->gross_value      = $invoice->gross_value      ?: 0;
        $invoice->tax_rate         = $invoice->tax_rate         ?: 18;
        $invoice->tax_amount       = $invoice->tax_amount       ?: 0;
        $invoice->total_amount     = $invoice->total_amount     ?: $invoice->amount;
        $invoice->invoice_date     = $invoice->invoice_date     ?: $invoice->start_date;
        $invoice->due_date         = $invoice->due_date         ?: $invoice->end_date;
        $invoice->notes            = $invoice->notes            ?: '';
        $invoice->status           = $invoice->status           ?: 'draft';

        // Company / Issuer fields (with sensible defaults)
        $invoice->company_name    = $invoice->company_name    ?: 'TERA Technologies & Engineering Ltd.';
        $invoice->company_email   = $invoice->company_email   ?: 'info@teratech.co.tz';
        $invoice->company_phone   = $invoice->company_phone   ?: '+255 22 2701611';
        $invoice->company_website = $invoice->company_website ?: 'www.teratech.co.tz';
        $invoice->company_tin     = $invoice->company_tin     ?: '';
        $invoice->company_vrn     = $invoice->company_vrn     ?: '';
        $invoice->company_address = $invoice->company_address ?: 'Plot No. 2283, Mbezi Beach Africana, Dar es Salaam, Tanzania';
        $invoice->company_logo    = $invoice->company_logo    ?: null;

        return $invoice;
    }

    /** Validation rules shared by store and update. */
    private function invoiceRules(): array
    {
        return [
            'invoice_number'   => 'required|string|max:50',
            'title'            => 'nullable|string|max:255',
            'client_name'      => 'required|string|max:255',
            'client_email'     => 'nullable|email|max:255',
            'client_phone'     => 'nullable|string|max:20',
            'tin'              => 'nullable|string|max:50',
            'address'          => 'nullable|string|max:500',
            'vrn'              => 'nullable|string|max:50',
            'item_description' => 'required|string',
            'number_of_cars'   => 'required|numeric|min:0',
            'period_months'    => 'required|numeric|min:0',
            'uom'              => 'required|string|max:50',
            'unit_price'       => 'required|numeric|min:0',
            'tax_rate'         => 'required|numeric|min:0|max:100',
            'invoice_date'     => 'required|date',
            'due_date'         => 'required|date|after_or_equal:invoice_date',
            'notes'            => 'nullable|string',
            // Company / Issuer
            'company_name'     => 'nullable|string|max:255',
            'company_email'    => 'nullable|email|max:255',
            'company_phone'    => 'nullable|string|max:30',
            'company_tin'      => 'nullable|string|max:50',
            'company_vrn'      => 'nullable|string|max:50',
            'company_address'  => 'nullable|string|max:500',
            'company_logo'     => 'nullable|file|image|max:2048',
        ];
    }

    /** Calculate gross, tax and total from request fields. */
    private function calculateTotals(array $data): array
    {
        $gross = $data['number_of_cars'] * $data['period_months'] * $data['unit_price'];
        $tax   = $gross * ($data['tax_rate'] / 100);

        return [
            'gross_value'  => $gross,
            'tax_amount'   => $tax,
            'total_amount' => $gross + $tax,
        ];
    }

    /** Build the full save payload (new fields + legacy compatibility). */
    private function buildPayload(array $data, ?Invoice $existing = null): array
    {
        $totals = $this->calculateTotals($data);

        return array_merge([
            // Invoice core
            'invoice_number'   => $data['invoice_number'],
            'title'            => $data['title']            ?? null,
            'client_name'      => $data['client_name'],
            'client_email'     => $data['client_email']     ?? null,
            'client_phone'     => $data['client_phone']     ?? null,
            'tin'              => $data['tin']              ?? null,
            'address'          => $data['address']          ?? null,
            'vrn'              => $data['vrn']              ?? null,
            'item_description' => $data['item_description'],
            'number_of_cars'   => $data['number_of_cars'],
            'period_months'    => $data['period_months'],
            'uom'              => $data['uom'],
            'unit_price'       => $data['unit_price'],
            'tax_rate'         => $data['tax_rate'],
            'invoice_date'     => $data['invoice_date'],
            'due_date'         => $data['due_date'],
            'notes'            => $data['notes']            ?? null,
            'status'           => $data['status']           ?? ($existing ? $existing->status : 'draft'),
            // Company / Issuer
            'company_name'     => $data['company_name']    ?? ($existing->company_name    ?? null),
            'company_email'    => $data['company_email']   ?? ($existing->company_email   ?? null),
            'company_phone'    => $data['company_phone']   ?? ($existing->company_phone   ?? null),
            'company_website'  => $data['company_website'] ?? ($existing->company_website ?? null),
            'company_tin'      => $data['company_tin']     ?? ($existing->company_tin     ?? null),
            'company_vrn'      => $data['company_vrn']     ?? ($existing->company_vrn     ?? null),
            'company_address'  => $data['company_address'] ?? ($existing->company_address ?? null),
        ], $totals, [
            // Legacy compatibility
            'item'         => $data['item_description'],
            'ref_number'   => $data['invoice_number'],
            'amount'       => $totals['total_amount'],
            'department_id'=> $existing->department_id ?? 5,
            'iscreated_by' => $existing->iscreated_by  ?? auth()->id(),
            'description'  => trim(implode(' - ', array_filter([
                $data['client_name'],
                $data['client_email'] ?? '',
                $data['client_phone'] ?? '',
            ]))),
            'project_id'   => $existing->project_id ?? 0,
            'project_name' => $data['client_name'],
            'tender_id'    => $existing->tender_id   ?? null,
            'budget'       => $existing->budget      ?? null,
            'contract'     => $existing->contract    ?? null,
            'start_date'   => $data['invoice_date'],
            'end_date'     => $data['due_date'],
            'payment'      => $existing->payment     ?? '',
        ]);
    }

    /** Handle optional logo upload and return stored path. */
    private function handleLogoUpload(Request $request, ?Invoice $existing = null): ?string
    {
        if (!$request->hasFile('company_logo')) {
            return $existing?->company_logo ?? null;
        }

        // Delete previous logo if replacing
        if ($existing?->company_logo && Storage::disk('public')->exists($existing->company_logo)) {
            Storage::disk('public')->delete($existing->company_logo);
        }

        return $request->file('company_logo')->store('invoice_logos', 'public');
    }

    /** Convert a stored logo path to an inline base64 data-URI for PDF embedding. */
    private function logoToBase64(?string $path): ?string
    {
        if (!$path) return null;

        $fullPath = Storage::disk('public')->path($path);
        if (!file_exists($fullPath)) return null;

        $mime = mime_content_type($fullPath);
        $data = base64_encode(file_get_contents($fullPath));

        return "data:{$mime};base64,{$data}";
    }

    // ═══════════════════════════════════════════════════════════════
    //  CRUD
    // ═══════════════════════════════════════════════════════════════

    public function indexAccountant(Request $request)
    {
        $invoices = Invoice::with(['creator', 'department', 'project'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($invoice) => $this->normalise($invoice));

        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function storeAccountant(Request $request)
    {
        $validator = Validator::make($request->all(), $this->invoiceRules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $payload               = $this->buildPayload($request->all());
        $payload['created_by'] = auth()->id();
        $payload['company_logo'] = $this->handleLogoUpload($request);

        $invoice = Invoice::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Invoice created successfully',
            'data'    => $invoice,
        ]);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['creator', 'department', 'project'])->find($id);

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invoice retrieved successfully',
            'data'    => $invoice,
        ]);
    }

    public function updateAccountant(Request $request, $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        $validator = Validator::make($request->all(), $this->invoiceRules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $payload               = $this->buildPayload($request->all(), $invoice);
        $payload['updated_by'] = auth()->id();
        $payload['company_logo'] = $this->handleLogoUpload($request, $invoice);

        $invoice->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Invoice updated successfully',
            'data'    => $invoice,
        ]);
    }

    public function destroyAccountant($id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        // Clean up logo file on delete
        if ($invoice->company_logo && Storage::disk('public')->exists($invoice->company_logo)) {
            Storage::disk('public')->delete($invoice->company_logo);
        }

        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted successfully',
            'data'    => $invoice,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  STATISTICS
    // ═══════════════════════════════════════════════════════════════

    public function statisticsAccountant()
    {
        $counts = Invoice::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $amountByStatus = Invoice::selectRaw('status, SUM(total_amount) as amount')
            ->groupBy('status')
            ->pluck('amount', 'status')
            ->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_invoices'  => Invoice::count(),
                'paid_invoices'   => $counts['paid']   ?? 0,
                'sent_invoices'   => $counts['sent']   ?? 0,
                'draft_invoices'  => $counts['draft']  ?? 0,
                'unpaid_invoices' => $counts['unpaid'] ?? 0,
                'total_amount'    => Invoice::sum('total_amount'),
                'paid_amount'     => $amountByStatus['paid']   ?? 0,
                'unpaid_amount'   => $amountByStatus['unpaid'] ?? 0,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  SEND (EMAIL / WHATSAPP)
    // ═══════════════════════════════════════════════════════════════

    public function sendAccountantInvoice(Request $request, $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        $invoice = $this->normalise($invoice);

        $validator = Validator::make($request->all(), [
            'send_method' => 'required|in:email,whatsapp,both',
            'message'     => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $sendMethod    = $request->send_method;
        $customMessage = $request->message ?? '';

        if (in_array($sendMethod, ['whatsapp', 'both']) && empty($invoice->client_phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number is required for WhatsApp sending',
                'errors'  => ['client_phone' => ['The client phone field is required.']],
            ], 422);
        }

        $results = [];

        // ── Email ──────────────────────────────────────────────────
        if (in_array($sendMethod, ['email', 'both'])) {
            if (!empty($invoice->client_email)) {
                try {
                    $subject = "Invoice #{$invoice->invoice_number} from {$invoice->company_name}";
                    Mail::to($invoice->client_email)->send(new InvoiceMail($invoice, $subject));
                    $results['email'] = 'sent';
                    Log::info("Invoice #{$invoice->invoice_number} emailed to {$invoice->client_email}");
                } catch (\Exception $e) {
                    $results['email'] = 'failed: ' . $e->getMessage();
                    Log::error("Invoice #{$invoice->invoice_number} email failed: " . $e->getMessage());
                }
            } else {
                $results['email'] = 'skipped: no email address provided';
            }
        }

        // ── WhatsApp ───────────────────────────────────────────────
        if (in_array($sendMethod, ['whatsapp', 'both'])) {
            if (!empty($invoice->client_phone)) {
                try {
                    $message  = $this->generateWhatsAppMessage($invoice, $customMessage);
                    $response = $this->sendWhatsAppMessage($invoice->client_phone, $message);

                    $results['whatsapp'] = $response['success'] ? 'sent' : 'failed: ' . $response['message'];

                    if (isset($response['url'])) {
                        $results['whatsapp_url'] = $response['url'];
                    }

                    $response['success']
                        ? Log::info("Invoice #{$invoice->invoice_number} WhatsApp sent to {$invoice->client_phone}")
                        : Log::error("Invoice #{$invoice->invoice_number} WhatsApp failed: " . $response['message']);
                } catch (\Exception $e) {
                    $results['whatsapp'] = 'failed: ' . $e->getMessage();
                    Log::error("Invoice #{$invoice->invoice_number} WhatsApp exception: " . $e->getMessage());
                }
            } else {
                $results['whatsapp'] = 'skipped: no phone number provided';
            }
        }

        $hasSuccess = collect($results)->contains(fn ($r) => str_starts_with($r, 'sent'));

        if ($hasSuccess) {
            $invoice->update(['status' => 'sent']);
            Log::info("Invoice #{$invoice->invoice_number} status -> 'sent'");
        }

        return response()->json([
            'success' => $hasSuccess,
            'message' => $hasSuccess ? 'Invoice sent successfully' : 'Failed to send invoice',
            'results' => $results,
            'data'    => $invoice,
        ]);
    }

    private function generateWhatsAppMessage($invoice, string $customMessage = ''): string
    {
        // Ensure we have the normalized invoice object
        $invoice = $this->normalise($invoice);
        
        $lines = [
            "🧾 *INVOICE #{$invoice->invoice_number}*",
            "📌 *{$invoice->company_name}*",
            '',
            '📋 *Details:*',
            "• Client: {$invoice->client_name}",
            "• Description: {$invoice->item_description}",
            '• Amount: TZS ' . number_format((float) $invoice->total_amount, 2),
            "• Due Date: {$invoice->due_date}",
        ];

        if (!empty($customMessage)) {
            $lines[] = '';
            $lines[] = "💬 *Message:*\n{$customMessage}";
        }

        $lines[] = '';
        $lines[] = '📎 Full invoice details have been sent to your email.';
        $lines[] = '';
        $lines[] = 'Thank you for your business! 🙏';

        return implode("\n", $lines);
    }

    private function sendWhatsAppMessage(string $phone, string $message): array
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 9 && $phone[0] !== '0') {
            $phone = '255' . $phone;
        } elseif (strlen($phone) === 10 && $phone[0] === '0') {
            $phone = '255' . substr($phone, 1);
        }

        $url = 'https://wa.me/' . $phone . '?text=' . urlencode($message);
        Log::info("WhatsApp URL generated for {$phone}");

        return [
            'success' => true,
            'message' => 'WhatsApp URL generated',
            'url'     => $url,
            'phone'   => $phone,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  MARK AS PAID
    // ═══════════════════════════════════════════════════════════════

    public function markAccountantInvoiceAsPaid(Request $request, $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_date'      => 'required|date',
            'payment_method'    => 'required|string|max:50',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $invoice->update([
            'status'            => 'paid',
            'payment'           => $request->payment_method,
            'payment_date'      => $request->payment_date,
            'payment_reference' => $request->payment_reference,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice marked as paid successfully',
            'data'    => $invoice,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  EXPORTS
    // ═══════════════════════════════════════════════════════════════

    public function exportInvoicesToExcel()
    {
        $invoices = Invoice::with(['creator', 'department', 'project'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($invoice) => $this->normalise($invoice));

        $filename = 'invoices_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($invoices) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Invoice Number', 'Client Name', 'Client Email', 'Client Phone',
                'Item Description', 'Number of Cars', 'Period Months', 'UOM',
                'Unit Price', 'Tax Rate (%)', 'Gross Value', 'Tax Amount', 'Total Amount',
                'Invoice Date', 'Due Date', 'Status', 'Notes', 'Created At',
            ]);

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
                    $invoice->status,
                    $invoice->notes,
                    $invoice->created_at,
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
            ->map(fn ($invoice) => $this->normalise($invoice));

        // Return JSON data for frontend rendering
        return response()->json([
            'success' => true,
            'data' => [
                'invoices' => $invoices,
                'company' => $this->getCompanyInfo($invoices->first() ?? new Invoice()),
                'filename' => 'invoices_' . date('Y-m-d_H-i-s') . '.pdf',
                'generated' => $this->now(),
                'total_records' => $invoices->count(),
                'grand_total' => $invoices->sum('total_amount')
            ]
        ]);
    }

    public function downloadInvoice($id)
    {
        $invoice  = Invoice::findOrFail($id);
        $invoice  = $this->normalise($invoice);
        
        // Return JSON data for frontend rendering
        return response()->json([
            'success' => true,
            'data' => [
                'invoice' => $invoice,
                'company' => $this->getCompanyInfo($invoice),
                'status_badge' => $this->getStatusBadge($invoice->status ?? 'draft'),
                'client_info' => $this->buildClientInfo($invoice),
                'tax_info' => $this->buildTaxInfo($invoice),
                'notes' => $invoice->notes ?? '',
                'filename' => 'invoice_' . $invoice->invoice_number . '.pdf'
            ]
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  PDF RENDER
    // ═══════════════════════════════════════════════════════════════

    /** Render HTML to PDF via DomPDF, or fall back to an HTML download. */
    private function renderPdf(string $html, string $filename, string $orientation = 'portrait')
    {
        if (class_exists(\Dompdf\Dompdf::class)) {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);     // needed for base64 images
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'Arial');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // DomPDF not installed — return as HTML
        $htmlFilename = str_replace('.pdf', '.html', $filename);

        return response($html, 200, [
            'Content-Type'        => 'text/html',
            'Content-Disposition' => "attachment; filename=\"{$htmlFilename}\"",
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  SINGLE-INVOICE HTML
    // ═══════════════════════════════════════════════════════════════

    private function buildSingleInvoiceHtml(Invoice $inv): string
    {
        // Return JSON data for frontend rendering instead of HTML
        return json_encode([
            'invoice' => $inv,
            'company' => $this->getCompanyInfo($inv),
            'status_badge' => $this->getStatusBadge($inv->status ?? 'draft'),
            'client_info' => $this->buildClientInfo($inv),
            'tax_info' => $this->buildTaxInfo($inv),
            'notes' => $inv->notes ?? ''
        ]);
    }

    private function getCompanyInfo($inv)
    {
        return [
            'name' => $inv->company_name ?? 'TERA TECHNOLOGIES AND ENGINEERING LIMITED',
            'address' => $inv->company_address ?? 'Office: Mbezi Beach Africana, Plot No. 2283, Block H, Tarangire Street, Bagamoyo Road/African Drive, P.O. Box 31257, Dar es Salaam, Tanzania.',
            'contacts' => 'Tel/Fax: +255 22 2701611, Cell: +255 713 899 309 E-mail: info@teratech.co.tz, Website: www.teratech.co.tz'
        ];
    }

    private function getStatusBadge($status)
    {
        [$bg, $fg] = match ($status) {
            'paid'    => ['#dcfce7', '#15803d'],
            'sent'    => ['#dbeafe', '#1d4ed8'],
            'unpaid'  => ['#fee2e2', '#b91c1c'],
            default   => ['#f1f5f9', '#64748b'],
        };
        return ['bg' => $bg, 'fg' => $fg, 'text' => $status];
    }

    private function buildClientInfo($inv)
    {
        $info = [];
        if ($inv->client_email) $info[] = ['label' => 'Email', 'value' => $inv->client_email];
        if ($inv->client_phone) $info[] = ['label' => 'Phone', 'value' => $inv->client_phone];
        if ($inv->address) $info[] = ['label' => 'Address', 'value' => $inv->address];
        return $info;
    }

    private function buildTaxInfo($inv)
    {
        $info = [];
        if ($inv->tin) $info[] = ['label' => 'TIN', 'value' => $inv->tin];
        if ($inv->vrn) $info[] = ['label' => 'VRN', 'value' => $inv->vrn];
        return $info;
    }

    // ═══════════════════════════════════════════════════════════════
    //  MICRO HELPERS
    // ═══════════════════════════════════════════════════════════════

    /** HTML-escape a value safely. */
    private function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Render a block only when a condition is truthy. */
    private function when(mixed $condition, string $html): string
    {
        return $condition ? $html : '';
    }

    /** Format a number with 2 decimal places and thousands separator. */
    private function fmt(float|int $value): string
    {
        return number_format((float) $value, 2);
    }

    /** Current timestamp string for footers/generated-on lines. */
    private function now(): string
    {
        return date('d M Y, H:i');
    }
}