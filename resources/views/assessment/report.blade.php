<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>{{ $report->reportNumber }}</title>
<style>
    @page { margin: 20mm 18mm 18mm 18mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.5; color: #222; }
    .provider { text-align: center; margin-bottom: 14px; }
    .provider .name { font-size: 13pt; font-weight: bold; }
    .provider .legal { font-size: 9pt; color: #555; }
    h1 { font-size: 15pt; text-align: center; margin: 10px 0 2px 0; }
    .number { text-align: center; color: #555; margin: 0 0 14px 0; }
    .meta { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .meta td { padding: 2px 8px 2px 0; vertical-align: top; }
    .meta td.label { color: #555; width: 26%; }
    h2 { font-size: 11pt; border-bottom: 1px solid #222; padding-bottom: 2px; margin: 16px 0 8px 0; }
    .scales { width: 100%; border-collapse: collapse; }
    .scales th, .scales td { border: 1px solid #999; padding: 3px 6px; text-align: left; }
    .scales th { background: #eee; }
    .body { margin: 0; }
    .signature { margin-top: 26px; page-break-inside: avoid; }
    .signature .line { margin-top: 44px; font-weight: bold; }
    .notice { margin-top: 14px; padding: 6px 8px; border: 1px solid #999; background: #f6f6f6; font-size: 9pt; }
</style>
</head>
<body>
<div class="provider">
    <div class="name">{{ $report->providerName }}</div>
    <div class="legal">{{ $report->providerLegalName }}</div>
</div>

<h1>{{ $report->testTitle }}</h1>
<p class="number">{{ $report->reportNumber }}</p>

<table class="meta">
    <tr><td class="label">Candidate</td><td>{{ $report->candidateName }}</td></tr>
    <tr><td class="label">Signed on</td><td>{{ $report->signedAt->format('d F Y') }}</td></tr>
</table>

@if ($report->scales)
    <h2>Scores</h2>
    <table class="scales">
        <tr><th>Scale</th><th>Raw score</th><th>Standard score</th><th>Band</th></tr>
        @foreach ($report->scales as $row)
            <tr>
                <td>{{ $row['scale'] }}</td>
                <td>{{ rtrim(rtrim(number_format($row['raw'], 2, '.', ''), '0'), '.') }}</td>
                <td>{{ $row['standard'] === null ? '-' : rtrim(rtrim(number_format($row['standard'], 2, '.', ''), '0'), '.') }}</td>
                <td>{{ $row['band'] ?? '-' }}</td>
            </tr>
        @endforeach
    </table>
@endif

<h2>Report</h2>
<p class="body">{!! nl2br(e($report->body)) !!}</p>

@if ($report->autoIssued)
    <p class="notice">This report was issued automatically at the time the test was submitted, under the responsibility of the psychologist named below.</p>
@endif

<div class="signature">
    <div>Signed by</div>
    <div class="line">{{ $report->signerName }}</div>
    <div>STR {{ $report->signerStrNumber }} &nbsp;|&nbsp; SIPP/SILP {{ $report->signerSilpNumber }}</div>
</div>
</body>
</html>
