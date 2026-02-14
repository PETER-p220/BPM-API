<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 28px;
        }
        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .detail-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .detail-group h3 {
            margin: 0 0 10px 0;
            color: #007bff;
            font-size: 16px;
        }
        .detail-group p {
            margin: 5px 0;
            font-size: 14px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .invoice-table th {
            background: #007bff;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        .invoice-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .invoice-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .totals {
            text-align: right;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .total-row:last-child {
            border-bottom: 2px solid #007bff;
            font-weight: bold;
            font-size: 18px;
            color: #007bff;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 14px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-draft { background: #6c757d; color: white; }
        .status-sent { background: #007bff; color: white; }
        .status-paid { background: #28a745; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>INVOICE</h1>
            <p><strong>Invoice Number:</strong> {{ $invoice->invoice_number }}</p>
            <p><strong>Status:</strong> <span class="status-badge status-{{ $invoice->status ?? 'draft' }}">{{ ucfirst($invoice->status ?? 'draft') }}</span></p>
        </div>

        <div class="invoice-details">
            <div class="detail-group">
                <h3>Client Information</h3>
                <p><strong>Name:</strong> {{ $invoice->client_name }}</p>
                <p><strong>Email:</strong> {{ $invoice->client_email }}</p>
                <p><strong>Phone:</strong> {{ $invoice->client_phone }}</p>
            </div>

            <div class="detail-group">
                <h3>Invoice Details</h3>
                <p><strong>Invoice Date:</strong> {{ $invoice->invoice_date }}</p>
                <p><strong>Due Date:</strong> {{ $invoice->due_date }}</p>
                <p><strong>Notes:</strong> {{ $invoice->notes ?: 'N/A' }}</p>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Number of Cars</th>
                    <th>Period (Months)</th>
                    <th>UOM</th>
                    <th>Unit Price</th>
                    <th>Gross Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $invoice->item_description }}</td>
                    <td>{{ $invoice->number_of_cars }}</td>
                    <td>{{ $invoice->period_months }}</td>
                    <td>{{ $invoice->uom }}</td>
                    <td>TZS {{ number_format($invoice->unit_price, 2) }}</td>
                    <td>TZS {{ number_format($invoice->gross_value, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row">
                <span>Gross Value:</span>
                <span>TZS {{ number_format($invoice->gross_value, 2) }}</span>
            </div>
            <div class="total-row">
                <span>Tax Amount ({{ $invoice->tax_rate }}%):</span>
                <span>TZS {{ number_format($invoice->tax_amount, 2) }}</span>
            </div>
            <div class="total-row">
                <span>Total Amount:</span>
                <span>TZS {{ number_format($invoice->total_amount, 2) }}</span>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>This is a computer-generated invoice. No signature is required.</p>
            <p>Generated on {{ date('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
