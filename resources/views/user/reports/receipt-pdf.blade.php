<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt - Order #{{ $order->id }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 14px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #ddd; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 28px; color: #1e40af; letter-spacing: 1px; }
        .header p { margin: 5px 0 0; color: #666; font-size: 13px; }
        .receipt-info { margin-bottom: 30px; }
        .receipt-info table { width: 100%; }
        .receipt-info td { padding: 5px 0; }
        .receipt-info .label { font-weight: bold; color: #64748b; font-size: 12px; text-transform: uppercase; width: 40%; }
        .receipt-info .value { font-weight: bold; color: #0f172a; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { padding: 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .items-table th { background-color: #f8fafc; font-size: 12px; color: #475569; text-transform: uppercase; }
        .totals-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .totals-table td { padding: 8px 10px; text-align: right; }
        .totals-table .label { font-weight: bold; color: #64748b; font-size: 12px; text-transform: uppercase; width: 75%; }
        .totals-table .value { font-weight: bold; color: #0f172a; }
        .totals-table .grand-total .label, .totals-table .grand-total .value { font-size: 17px; color: #1e40af; border-top: 2px solid #1e40af; padding-top: 15px; }
        .footer { text-align: center; font-size: 11px; color: #64748b; margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 20px; line-height: 1.5; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; margin-top: 10px; }
    </style>
</head>
<body>
    @php $pricing = $pricing ?? $order->pricingSummary(); @endphp

    <div class="header">
        <h1>GEORX: A Medicine Hub Portal</h1>
        <p>Official Order Receipt</p>
        <div class="status-badge">{{ $order->status === 'delivered' ? 'DELIVERED (COD)' : strtoupper(str_replace('_', ' ', $order->status)) }}</div>
    </div>

    <div class="receipt-info">
        <table>
            <tr>
                <td class="label">Order Number:</td>
                <td class="value">#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</td>
            </tr>
            <tr>
                <td class="label">Date:</td>
                <td class="value">{{ $date }}</td>
            </tr>
            <tr>
                <td class="label">Pharmacy:</td>
                <td class="value">{{ $order->pharmacy->name }}</td>
            </tr>
            <tr>
                <td class="label">Customer Name:</td>
                <td class="value">{{ $order->user->name }}</td>
            </tr>
            <tr>
                <td class="label">Delivery Address:</td>
                <td class="value">{{ $order->delivery_address }}</td>
            </tr>
            @if($order->delivery_provider)
            <tr>
                <td class="label">Courier Partner:</td>
                <td class="value">
                    {{ $order->delivery_provider === 'grab' ? 'Grab Express' : 'Maxim Delivery' }}
                    @if($order->delivery_booking_id)
                        (Ref: {{ $order->delivery_booking_id }})
                    @endif
                </td>
            </tr>
            @endif
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;">Item Description</th>
                <th style="width: 15%; text-align: center;">Qty</th>
                <th style="width: 15%; text-align: right;">Price</th>
                <th style="width: 20%; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>
                    <div style="font-weight: bold; color: #0f172a;">{{ $item->medicine->brand_name ?? $item->medicine->generic_name }}</div>
                    <div style="font-size: 11px; color: #64748b;">{{ $item->medicine->generic_name }}</div>
                </td>
                <td style="text-align: center; font-weight: bold;">{{ $item->quantity }}</td>
                <td style="text-align: right;">P{{ number_format($item->price, 2) }}</td>
                <td style="text-align: right; font-weight: bold; color: #0f172a;">P{{ number_format($item->price * $item->quantity, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td class="label">Medicine Subtotal:</td>
            <td class="value">P{{ number_format($pricing['medicine_subtotal'], 2) }}</td>
        </tr>
        <tr>
            <td class="label">Delivery Fee:</td>
            <td class="value">P{{ number_format($pricing['estimated_delivery_fee'], 2) }}</td>
        </tr>
        <tr class="grand-total">
            <td class="label">Customer Payable Amount (COD):</td>
            <td class="value">P{{ number_format($pricing['customer_payable_amount'], 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        <p>Thank you for choosing GEORX!</p>
        <p>Customer COD payable reflects the confirmed order total (Medicine Subtotal + Delivery Fee) with no additional courier surcharge.</p>
    </div>

</body>
</html>
