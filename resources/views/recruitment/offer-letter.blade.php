<!DOCTYPE html>
<html lang="{{ $letter->language }}">
<head>
<meta charset="UTF-8">
<title>{{ $letter->labels['title'] }}</title>
<style>
    @page { margin: 20mm 20mm 18mm 20mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.5; color: #222; }
    .head { width: 100%; border-collapse: collapse; border-bottom: 1px solid #222; margin-bottom: 14px; }
    .head td { padding: 0 0 8px 0; vertical-align: middle; }
    .logo { width: 22mm; }
    .logo img { max-width: 20mm; max-height: 20mm; }
    .company { font-size: 14pt; font-weight: bold; }
    .address { font-size: 8.5pt; color: #555; }
    h1 { font-size: 15pt; text-align: center; margin: 18px 0 14px 0; text-transform: uppercase; letter-spacing: 1px; }
    .terms { width: 100%; border-collapse: collapse; margin: 10px 0; }
    .terms td { padding: 4px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
    .terms td.label { width: 36%; color: #555; }
    h2 { font-size: 10.5pt; margin: 14px 0 4px 0; }
    .right { text-align: right; }
    .footer { margin-top: 28px; font-size: 8.5pt; color: #666; border-top: 1px solid #ddd; padding-top: 6px; }
</style>
</head>
<body>
<table class="head"><tr>
    @if ($letter->logo)
        <td class="logo"><img src="{{ $letter->logo }}" alt=""></td>
    @endif
    <td>
        <div class="company">{{ $letter->companyName }}</div>
        @if ($letter->address)
            <div class="address">{{ $letter->address }}</div>
        @endif
    </td>
</tr></table>

<p class="right">{{ $letter->date }}</p>
<p>{{ $letter->labels['dear'] }} {{ $letter->candidateName }},</p>

<h1>{{ $letter->labels['title'] }}</h1>
<p>{{ $letter->labels['intro'] }}</p>

<table class="terms">
    @foreach ($letter->rows as $row)
        <tr><td class="label">{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
    @endforeach
</table>

@if ($letter->allowances)
    <h2>{{ $letter->labels['allowances'] }}</h2>
    <table class="terms">
        @foreach ($letter->allowances as $line)
            <tr><td class="label">{{ $line['name'] }}</td><td>{{ $line['amount'] }}</td></tr>
        @endforeach
        <tr><td class="label"><strong>{{ $letter->labels['allowances_total'] }}</strong></td><td><strong>{{ $letter->allowancesTotal }}</strong></td></tr>
    </table>
@endif

@if ($letter->benefits)
    <h2>{{ $letter->labels['benefits'] }}</h2>
    <ul>
        @foreach ($letter->benefits as $benefit)
            <li>{{ $benefit }}</li>
        @endforeach
    </ul>
@endif

@if ($letter->note)
    <h2>{{ $letter->labels['note'] }}</h2>
    <p>{!! nl2br(e($letter->note)) !!}</p>
@endif

<p>{{ $letter->labels['deadline'] }}: <strong>{{ $letter->deadline }}</strong>.</p>

<div class="footer">{{ $letter->issuedBy }}</div>
</body>
</html>
