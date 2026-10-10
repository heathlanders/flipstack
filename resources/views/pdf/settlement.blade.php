<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Settlement Statement - {{ $settlement->settlement_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            background: #fff;
        }
        
        .container {
            padding: 20px 30px;
        }
        
        /* Header */
        .header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header-top {
            display: table;
            width: 100%;
        }
        
        .company-info {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }
        
        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 5px;
        }
        
        .company-address {
            font-size: 9pt;
            color: #666;
        }
        
        .settlement-info {
            display: table-cell;
            width: 40%;
            vertical-align: top;
            text-align: right;
        }
        
        .settlement-title {
            font-size: 14pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .settlement-number {
            font-size: 11pt;
            color: #4f46e5;
            font-weight: bold;
        }
        
        .settlement-date {
            font-size: 9pt;
            color: #666;
            margin-top: 3px;
        }
        
        /* Supplier & Period Info */
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .info-box {
            display: table-cell;
            width: 50%;
            padding: 10px;
            vertical-align: top;
        }
        
        .info-box.left {
            padding-left: 0;
            padding-right: 10px;
        }
        
        .info-box.right {
            padding-right: 0;
            padding-left: 10px;
        }
        
        .info-box-inner {
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 12px;
        }
        
        .info-label {
            font-size: 8pt;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 10pt;
            color: #111;
        }
        
        .info-value.large {
            font-size: 12pt;
            font-weight: bold;
        }
        
        /* Summary Box */
        .summary-section {
            margin-bottom: 20px;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .summary-table td {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
        }
        
        .summary-table .label {
            background: #f8f9fa;
            width: 70%;
            font-weight: 500;
        }
        
        .summary-table .value {
            text-align: right;
            width: 30%;
        }
        
        .summary-table .total-row td {
            background: #4f46e5;
            color: #fff;
            font-weight: bold;
            font-size: 11pt;
        }
        
        /* Items Table */
        .items-section {
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        
        .items-table th {
            background: #f3f4f6;
            padding: 8px 6px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #e5e7eb;
            font-size: 8pt;
            text-transform: uppercase;
        }
        
        .items-table td {
            padding: 6px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }
        
        .items-table tr:nth-child(even) {
            background: #fafafa;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .items-table .text-center {
            text-align: center;
        }
        
        .items-table .return-row {
            background: #fef2f2;
            color: #b91c1c;
        }
        
        .items-table .sku {
            font-family: monospace;
            font-size: 8pt;
            color: #6b7280;
        }
        
        .items-table .product-name {
            font-weight: 500;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-processing {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Payment Details */
        .payment-section {
            margin-bottom: 20px;
        }
        
        .payment-box {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 4px;
            padding: 15px;
        }
        
        .payment-box.pending {
            background: #fefce8;
            border-color: #fde047;
        }
        
        .payment-details {
            display: table;
            width: 100%;
        }
        
        .payment-detail {
            display: table-row;
        }
        
        .payment-detail-label {
            display: table-cell;
            padding: 3px 0;
            width: 150px;
            color: #6b7280;
            font-size: 9pt;
        }
        
        .payment-detail-value {
            display: table-cell;
            padding: 3px 0;
            font-weight: 500;
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 8pt;
            color: #6b7280;
        }
        
        .footer-text {
            margin-bottom: 5px;
        }
        
        .footer-note {
            font-style: italic;
        }
        
        /* Page break for long item lists */
        .page-break {
            page-break-after: always;
        }
        
        /* Totals summary at bottom of items */
        .items-total {
            margin-top: 10px;
            text-align: right;
        }
        
        .items-total-table {
            width: auto;
            margin-left: auto;
            border-collapse: collapse;
        }
        
        .items-total-table td {
            padding: 4px 10px;
        }
        
        .items-total-table .label {
            text-align: right;
            color: #6b7280;
        }
        
        .items-total-table .value {
            text-align: right;
            font-weight: 500;
            min-width: 100px;
        }
        
        .items-total-table .grand-total td {
            font-size: 11pt;
            font-weight: bold;
            border-top: 2px solid #4f46e5;
            padding-top: 8px;
        }
        
        .items-total-table .grand-total .value {
            color: #4f46e5;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <div class="company-info">
                    <div class="company-name">{{ $organization->name ?? 'E-commerce SaaS' }}</div>
                    <div class="company-address">
                        {{ $organization->address ?? '123 Commerce Street' }}<br>
                        {{ $organization->city ?? 'San Francisco' }}, {{ $organization->state ?? 'CA' }} {{ $organization->zip ?? '94102' }}<br>
                        {{ $organization->email ?? 'settlements@example.com' }}
                    </div>
                </div>
                <div class="settlement-info">
                    <div class="settlement-title">Settlement Statement</div>
                    <div class="settlement-number">{{ $settlement->settlement_number }}</div>
                    <div class="settlement-date">Generated: {{ $settlement->generated_at->format('M d, Y') }}</div>
                </div>
            </div>
        </div>
        
        <!-- Supplier & Period Info -->
        <div class="info-section">
            <div class="info-box left">
                <div class="info-box-inner">
                    <div class="info-label">Supplier</div>
                    <div class="info-value large">{{ $supplier->business_name ?? $supplier->name }}</div>
                    <div class="info-value">{{ $supplier->email }}</div>
                    @if($supplier->supplier_id)
                    <div class="info-value" style="color: #6b7280; font-size: 9pt;">ID: {{ $supplier->supplier_id }}</div>
                    @endif
                </div>
            </div>
            <div class="info-box right">
                <div class="info-box-inner">
                    <div class="info-label">Settlement Period</div>
                    <div class="info-value large">
                        {{ $settlement->period_start->format('M d') }} - {{ $settlement->period_end->format('M d, Y') }}
                    </div>
                    <div class="info-value">
                        Status: <span class="status-badge status-{{ strtolower($settlement->status->value) }}">{{ $settlement->status->value }}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Financial Summary -->
        <div class="summary-section">
            <div class="section-title">Financial Summary</div>
            <table class="summary-table">
                <tr>
                    <td class="label">Gross Sales ({{ $settlement->items_count }} items)</td>
                    <td class="value">${{ number_format($settlement->gross_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Commission ({{ number_format($effectiveRate ?? 20, 1) }}%)</td>
                    <td class="value">-${{ number_format($settlement->commission_amount, 2) }}</td>
                </tr>
                @if($settlement->adjustments != 0)
                <tr>
                    <td class="label">Adjustments</td>
                    <td class="value">{{ $settlement->adjustments >= 0 ? '+' : '' }}${{ number_format($settlement->adjustments, 2) }}</td>
                </tr>
                @endif
                @if(isset($settlement->metadata['fees']) && $settlement->metadata['fees'] > 0)
                <tr>
                    <td class="label">Processing Fees</td>
                    <td class="value">-${{ number_format($settlement->metadata['fees'], 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td class="label">Net Amount</td>
                    <td class="value">${{ number_format($settlement->net_amount, 2) }}</td>
                </tr>
            </table>
        </div>
        
        <!-- Payment Details -->
        <div class="payment-section">
            <div class="section-title">Payment Information</div>
            <div class="payment-box {{ $settlement->status->value === 'paid' ? '' : 'pending' }}">
                <div class="payment-details">
                    <div class="payment-detail">
                        <span class="payment-detail-label">Payment Method:</span>
                        <span class="payment-detail-value">{{ ucwords(str_replace('_', ' ', $settlement->payment_method ?? 'Bank Transfer')) }}</span>
                    </div>
                    @if($settlement->payment_reference)
                    <div class="payment-detail">
                        <span class="payment-detail-label">Reference Number:</span>
                        <span class="payment-detail-value">{{ $settlement->payment_reference }}</span>
                    </div>
                    @endif
                    @if($settlement->payment_date)
                    <div class="payment-detail">
                        <span class="payment-detail-label">Payment Date:</span>
                        <span class="payment-detail-value">{{ $settlement->payment_date->format('M d, Y') }}</span>
                    </div>
                    @else
                    <div class="payment-detail">
                        <span class="payment-detail-label">Expected Payment:</span>
                        <span class="payment-detail-value">Within {{ $processingDays ?? 3 }} business days of approval</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Items Detail -->
        @if($items->count() > 0)
        <div class="items-section">
            <div class="section-title">Settlement Items</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Date</th>
                        <th style="width: 15%;">SKU</th>
                        <th style="width: 30%;">Product</th>
                        <th style="width: 12%;" class="text-right">Sale Price</th>
                        <th style="width: 10%;" class="text-center">Rate</th>
                        <th style="width: 12%;" class="text-right">Commission</th>
                        <th style="width: 12%;" class="text-right">Net</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr class="{{ $item->is_return ? 'return-row' : '' }}">
                        <td>{{ $item->sale_date->format('M d, Y') }}</td>
                        <td class="sku">{{ $item->sku }}</td>
                        <td>
                            <span class="product-name">{{ Str::limit($item->product_name, 35) }}</span>
                            @if($item->is_return)
                            <br><small>(Return: {{ $item->return_reason }})</small>
                            @endif
                        </td>
                        <td class="text-right">${{ number_format($item->sale_price, 2) }}</td>
                        <td class="text-center">{{ number_format($item->commission_rate, 1) }}%</td>
                        <td class="text-right">${{ number_format($item->commission_amount, 2) }}</td>
                        <td class="text-right">${{ number_format($item->net_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div class="items-total">
                <table class="items-total-table">
                    <tr>
                        <td class="label">Subtotal ({{ $items->where('is_return', false)->count() }} sales):</td>
                        <td class="value">${{ number_format($items->where('is_return', false)->sum('sale_price'), 2) }}</td>
                    </tr>
                    @if($items->where('is_return', true)->count() > 0)
                    <tr>
                        <td class="label">Returns ({{ $items->where('is_return', true)->count() }}):</td>
                        <td class="value">-${{ number_format(abs($items->where('is_return', true)->sum('sale_price')), 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="label">Total Commission:</td>
                        <td class="value">-${{ number_format($items->sum('commission_amount'), 2) }}</td>
                    </tr>
                    <tr class="grand-total">
                        <td class="label">Net Amount:</td>
                        <td class="value">${{ number_format($items->sum('net_amount'), 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>
        @endif
        
        <!-- Notes -->
        @if($settlement->notes)
        <div class="summary-section">
            <div class="section-title">Notes</div>
            <p style="font-size: 9pt; color: #6b7280;">{{ $settlement->notes }}</p>
        </div>
        @endif
        
        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                This statement reflects all sales and returns processed during the settlement period.
            </p>
            <p class="footer-text">
                For questions about this settlement, please contact {{ $organization->support_email ?? 'support@example.com' }} or reference settlement number {{ $settlement->settlement_number }}.
            </p>
            <p class="footer-note">
                This document was automatically generated on {{ now()->format('F d, Y \a\t g:i A T') }}.
            </p>
        </div>
    </div>
</body>
</html>
