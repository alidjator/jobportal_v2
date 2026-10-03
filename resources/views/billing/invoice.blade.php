@php
    $perJob = ($transaction['payment_type'] ?? null) === 'per_job_based';
    $plan = $transaction['plan'] ?? null;
    $amount = ($transaction['currency_symbol'] ?? '').($transaction['amount'] ?? '');
    $date = ! empty($transaction['created_at']) ? \Illuminate\Support\Carbon::parse($transaction['created_at'])->format('M d, Y') : '';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $appName ?? config('app.name') }} | Invoice</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; color: #21232A; }
        table { width: 100%; border-collapse: collapse; }
        .top { border-bottom: 1px solid #D2D5D9; padding-bottom: 20px; margin-bottom: 30px; }
        .top p { margin: 2px 0; }
        .logo img { max-width: 200px; height: auto; }
        .meta td { padding: 6px 12px; }
        .items th { background: #D2D5D9; text-align: left; padding: 10px 15px; }
        .items td { padding: 10px 15px; border-bottom: 1px solid #D2D3D9; vertical-align: top; }
        .total td { background: #D2D5D9; padding: 8px 18px; font-weight: bold; }
    </style>
</head>
<body>
    <table class="top">
        <tr>
            <td style="width:60%">
                <h4>Company Information:</h4>
                <p>{{ $transaction['company']['name'] ?? '' }}</p>
                <p>{{ $transaction['company']['email'] ?? '' }}</p>
                <p>{{ $transaction['company']['phone'] ?? '' }}</p>
                <p>{{ $transaction['company']['address'] ?? '' }}</p>
            </td>
            <td style="text-align:right" class="logo">
                @if (! empty($logo))
                    <img src="{{ $logo }}" alt="logo">
                @endif
            </td>
        </tr>
    </table>

    <table class="meta" style="width:auto; margin-left:auto;">
        <tr><td><strong>INVOICE</strong></td><td>#{{ $transaction['order_id'] ?? '' }}</td></tr>
        <tr><td><strong>INVOICE DATE</strong></td><td>{{ $date }}</td></tr>
    </table>

    <table class="items" style="margin-top:20px;">
        <thead>
            <tr>
                <th>Plan</th>
                @unless ($perJob)<th>Benefits</th>@endunless
                <th>Payment</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $perJob ? 'Pay per job' : ($plan['label'] ?? '') }}</td>
                @unless ($perJob)
                    <td>
                        Job Limit : {{ $plan['job_limit'] ?? '' }}<br>
                        Featured Job Limit : {{ $plan['featured_job_limit'] ?? '' }}<br>
                        Highlight Job Limit : {{ $plan['highlight_job_limit'] ?? '' }}<br>
                        Candidate CV View Limit : {{ ($plan['candidate_cv_view_limitation'] ?? 'limited') === 'limited' ? ($plan['candidate_cv_view_limit'] ?? '') : 'Unlimited' }}
                    </td>
                @endunless
                <td>{{ ucfirst((string) ($transaction['payment_provider'] ?? '')) }}</td>
                <td>{{ $amount }}</td>
            </tr>
        </tbody>
    </table>

    <table class="total" style="width:auto; margin-left:auto; margin-top:60px;">
        <tr><td>TOTAL</td><td>{{ $amount }}</td></tr>
    </table>
</body>
</html>
