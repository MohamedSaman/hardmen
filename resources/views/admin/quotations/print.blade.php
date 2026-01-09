<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation - {{ $quotation->quotation_number }}</title>
    <style>
        @page {
            margin: 20mm; /* Add some default page margins */
        }

        body {
            font-family: "Inter", "Roboto", "Helvetica Neue", Arial, sans-serif;
            font-size: 11px; /* Smaller base font */
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
            background: white;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }

        /* --- NEW HEADER STYLE --- */
        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0;
            color: #000; /* Black, not green */
            font-size: 24px;
            font-weight: bold;
        }

        .header p {
            margin: 2px 0;
            font-size: 11px;
        }
        
        .header .quotation-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #057642; /* Green line from image */
        }
        /* --- END NEW HEADER STYLE --- */
        

        /* --- NEW INFO SECTION STYLE --- */
        .customer-info {
            margin-bottom: 20px;
        }
        
        .customer-info strong {
            font-weight: bold;
        }

        .quotation-details {
             margin-bottom: 25px;
        }
        
        /* This table creates the perfect alignment from your image */
        .details-table {
            border-collapse: collapse;
            width: auto; /* Shrink to content */
        }
        
        .details-table td {
            padding: 2px 8px 2px 0;
            vertical-align: top;
        }
        
        .details-table td:first-child {
            
            font-weight: bold;
            text-align: left;
        }
        /* --- END NEW INFO SECTION STYLE --- */


        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
            
        }

        table.items-table th {
            background: #057642;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
        }

        table.items-table td {
            padding: 8px 6px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        
        tr.items-row:nth-child(even) {
            background-color: #f8f9fa;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }
        
        .amount {
    font-family: "Inter", "Roboto", "Helvetica Neue", Arial, sans-serif;
    color: #333;
}

        /* --- UPDATED TOTALS STYLE --- */
        .totals-section {
            margin-top: 20px;
            float: right; /* Align block to the right */
            width: 280px; /* Fixed width */
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .totals-table td {
            padding: 5px 0;
        }
        
        .totals-table td:first-child {
            font-weight: bold;
            text-align: right;
            padding-right: 15px;
        }
        
        .totals-table td:last-child {
            text-align: right;
            width: 120px; /* Fixed width for values */
        }
        
        .totals-table tr.grand-total td {
            border-top: 2px solid #333;
            font-size: 13px;
            font-weight: bold;
            padding-top: 8px;
        }
        /* --- END UPDATED TOTALS STYLE --- */

        /* Utility to clear floats */
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .notes-terms {
            clear: both; /* Clear the float from totals */
            padding-top: 30px;
            font-size: 11px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        @media print {
            body {
                padding: 0;
                margin: 0;
                font-size: 10px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .invoice-container {
                border: none;
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        
        <div class="header">
            <h2>MI-KING</h2>
            <p>122/10A, Super Paradise Market, Keyzer Street, Colombo 11 .</p>
            <p>Phone: (076) 1234567 | Email: sample@gmail.com</p>
            <div class="quotation-title">QUOTATION</div>
        </div>

        <div class="customer-info">
            <strong>Bill To:</strong><br>
            <strong>{{ $quotation->customer_name }}</strong><br>
            @if($quotation->customer_address)
                {{ $quotation->customer_address }}<br>
            @endif
            Tel: {{ $quotation->customer_phone }}<br>
            @if($quotation->customer_email)
                Email: {{ $quotation->customer_email }}<br>
            @endif
            Customer Type: {{ ucfirst($quotation->customer_type) }}
        </div>
        
        <div class="quotation-details">
            <table class="details-table">
                            <strong>Quotation Details:</strong><br>

                <tr>
                    <td><strong>Quotation No:</strong></td>
                    <td>{{ $quotation->quotation_number }}</td>
                </tr>
                <tr>
                    <td><strong>Date:</strong></td>
                    <td>{{ $quotation->quotation_date->format('M d, Y') }}</td>
                </tr>
                <tr>
                    <td><strong>Valid Until:</strong></td>
                    <td>{{ \Carbon\Carbon::parse($quotation->valid_until)->format('M d, Y') }}</td>
                </tr>
                 <tr>
                    <td><strong>Status:</strong></td>
                    <td>{{ ucfirst($quotation->status) }}</td>
                </tr>
            </table>
        </div>
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%" class="text-center">#</th>
                    <th width="12%">Item Code</th>
                    <th>Description</th>
                    <th width="8%" class="text-center">Qty</th>
                    <th width="15%" class="text-right">Unit Price (LKR)</th>
                    <th width="15%" class="text-right">Discount/Unit (LKR)</th>
                    <th width="15%" class="text-right">Subtotal (LKR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quotation->items as $index => $item)
                <tr class="items-row">
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item['product_code'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td class="text-center">{{ $item['quantity'] }}</td>
                    <td class="text-right amount">{{ number_format($item['unit_price'], 2) }}</td>
                    <td class="text-right amount">{{ number_format($item['discount_per_unit'] ?? 0, 2) }}</td>
                    <td class="text-right amount">{{ number_format($item['total'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-section">
            @php
                $totalDiscount = $quotation->discount_amount;
            @endphp
            <table class="totals-table">
                <tr>
                    <td>Subtotal:</td>
                    <td class="amount">LKR {{ number_format($quotation->subtotal, 2) }}</td>
                </tr>
                @if($totalDiscount > 0)
                <tr>
                    <td>Discount:</td>
                    <td class="amount">- LKR {{ number_format($totalDiscount, 2) }}</td>
                </tr>
                @endif
                <tr class="grand-total">
                    <td>Grand Total:</td>
                    <td class="amount">LKR {{ number_format($quotation->total_amount, 2) }}</td>
                </tr>
            </table>
        </div>


        <div class="notes-terms">
            @if($quotation->terms_conditions)
            <div style="margin-bottom: 15px;">
                <strong>Terms & Conditions:</strong><br>
                {!! nl2br(e($quotation->terms_conditions)) !!}
            </div>
            @endif
            
            @if($quotation->notes)
            <div>
                <strong>Notes:</strong><br>
                {!! nl2br(e($quotation->notes)) !!}
            </div>
            @endif
        </div>


        <div class="footer">
            <p><strong>Thank you for your business!</strong></p>
            <p>This is a computer-generated document — no signature required.</p>
            <p>Quotation generated on: {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>
</body>
</html>