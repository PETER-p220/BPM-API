<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Financial Records Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .summary {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .summary h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .summary-item {
            text-align: center;
            padding: 10px;
            background-color: white;
            border-radius: 3px;
            border: 1px solid #ddd;
        }
        .summary-item strong {
            display: block;
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
        }
        .summary-item span {
            font-size: 16px;
            font-weight: bold;
            color: #2196F3;
        }
        .filters {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 3px;
        }
        .filters strong {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #333;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .income {
            color: #4CAF50;
            font-weight: bold;
        }
        .expense {
            color: #f44336;
            font-weight: bold;
        }
        .status-pending {
            color: #FF9800;
        }
        .status-verified {
            color: #2196F3;
        }
        .status-approved {
            color: #4CAF50;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #666;
            font-size: 10px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Financial Records Report</h1>
        <p>Generated on: {{ $exportDate->format('d M Y H:i:s') }}</p>
        <p>Total Records: {{ $records->count() }}</p>
    </div>

    @if(!empty($filters))
    <div class="filters">
        <strong>Applied Filters:</strong>
        @if($filters['type'] ?? null) Type: {{ ucfirst($filters['type']) }} @endif
        @if($filters['category'] ?? null) | Category: {{ ucfirst($filters['category']) }} @endif
        @if($filters['date'] ?? null) | Date: {{ $filters['date'] }} @endif
        @if($filters['search'] ?? null) | Search: "{{ $filters['search'] }}" @endif
    </div>
    @endif

    <div class="summary">
        <h3>Financial Summary</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <strong>Total Income</strong>
                <span>TZS {{ number_format($summary['totalIncome'], 0) }}</span>
            </div>
            <div class="summary-item">
                <strong>Total Expenses</strong>
                <span>TZS {{ number_format($summary['totalExpenses'], 0) }}</span>
            </div>
            <div class="summary-item">
                <strong>Net Balance</strong>
                <span>TZS {{ number_format($summary['netBalance'], 0) }}</span>
            </div>
            <div class="summary-item">
                <strong>Total Records</strong>
                <span>{{ $summary['totalRecords'] }}</span>
            </div>
            <div class="summary-item">
                <strong>Approved</strong>
                <span>{{ $summary['approvedRecords'] }}</span>
            </div>
            <div class="summary-item">
                <strong>Pending</strong>
                <span>{{ $summary['pendingRecords'] }}</span>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Description</th>
                <th>Reference</th>
                <th>Type</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Created By</th>
                <th>Approved By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
            <tr>
                <td>{{ $record->id }}</td>
                <td>{{ $record->date->format('d M Y') }}</td>
                <td>{{ Str::limit($record->description, 50) }}</td>
                <td>{{ $record->reference ?? 'N/A' }}</td>
                <td class="{{ $record->type == 'income' ? 'income' : 'expense' }}">
                    {{ ucfirst($record->type) }}
                </td>
                <td>{{ ucfirst($record->category) }}</td>
                <td>TZS {{ number_format($record->amount, 0) }}</td>
                <td class="status-{{ $record->status }}">
                    {{ ucfirst($record->status) }}
                </td>
                <td>{{ $record->creator ? $record->creator->name : 'N/A' }}</td>
                <td>{{ $record->approver ? $record->approver->name : 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This report was generated automatically on {{ $exportDate->format('d M Y H:i:s') }}</p>
        <p>© {{ date('Y') }} Financial Management System</p>
    </div>
</body>
</html>
