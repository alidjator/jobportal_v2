<!DOCTYPE html>
<html lang="{{ $cv->language }}">
<head>
<meta charset="UTF-8">
<title>{{ $cv->name }}</title>
<style>
    @page { margin: 12mm 14mm 12mm 14mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; line-height: 1.35; color: #222; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; padding: 0; }
    h1 { font-size: 17pt; margin: 0; }
    .headline { font-size: 9.5pt; color: #555; margin: 1px 0 0 0; }
    .contact { text-align: right; font-size: 8pt; color: #444; width: 42%; }
    .photo { width: 24mm; padding-right: 4mm; }
    .photo img { width: 20mm; height: 20mm; }
    h2 { font-size: 9pt; text-transform: uppercase; letter-spacing: 0.8px; background: #eceff3; padding: 2px 5px; margin: 9px 0 5px 0; }
    .entry { margin-bottom: 5px; page-break-inside: avoid; }
    .head td.period { text-align: right; color: #555; font-size: 8pt; white-space: nowrap; width: 28%; }
    .title { font-weight: bold; }
    .sub { color: #444; }
    .text { margin: 1px 0 0 0; }
    .col { width: 50%; padding-right: 8px; }
</style>
</head>
<body>
<table><tr>
    @if ($cv->photo)<td class="photo"><img src="{{ $cv->photo }}" alt=""></td>@endif
    <td>
        <h1>{{ $cv->name }}</h1>
        @if ($cv->headline)<p class="headline">{{ $cv->headline }}</p>@endif
    </td>
    <td class="contact">
        {{ $cv->email }}@if ($cv->phone)<br>{{ $cv->phone }}@endif @if ($cv->location)<br>{{ $cv->location }}@endif
    </td>
</tr></table>

@if ($cv->summary)
    <h2>{{ $cv->labels['summary'] }}</h2>
    <p class="text">{!! nl2br(e($cv->summary)) !!}</p>
@endif

@if ($cv->experiences)
    <h2>{{ $cv->labels['experience'] }}</h2>
    @foreach ($cv->experiences as $entry)
        <div class="entry">
            <table class="head"><tr>
                <td><span class="title">{{ $entry['title'] }}</span> <span class="sub">- {{ $entry['company'] }}@if ($entry['location']), {{ $entry['location'] }}@endif</span></td>
                <td class="period">{{ $entry['period'] }}</td>
            </tr></table>
            @if ($entry['description'])
                <p class="text">{!! nl2br(e($entry['description'])) !!}</p>
            @endif
        </div>
    @endforeach
@endif

@if ($cv->educations)
    <h2>{{ $cv->labels['education'] }}</h2>
    @foreach ($cv->educations as $entry)
        <div class="entry">
            <table class="head"><tr>
                <td><span class="title">{{ $entry['institution'] }}</span>@if ($entry['title']) <span class="sub">- {{ $entry['title'] }}@if ($entry['gpa']), {{ $entry['gpa'] }}@endif</span>@endif</td>
                <td class="period">{{ $entry['period'] }}</td>
            </tr></table>
            @if ($entry['description'])
                <p class="text">{!! nl2br(e($entry['description'])) !!}</p>
            @endif
        </div>
    @endforeach
@endif

@if ($cv->skills || $cv->languages)
    <table><tr>
        <td class="col">
            @if ($cv->skills)
                <h2>{{ $cv->labels['skills'] }}</h2>
                <p class="text">{{ implode(', ', $cv->skills) }}</p>
            @endif
        </td>
        <td class="col">
            @if ($cv->languages)
                <h2>{{ $cv->labels['languages'] }}</h2>
                @foreach ($cv->languages as $language)
                    <p class="text">{{ $language['name'] }} - {{ $language['level'] }}</p>
                @endforeach
            @endif
        </td>
    </tr></table>
@endif

@if ($cv->personal || $cv->links)
    <table><tr>
        <td class="col">
            @if ($cv->personal)
                <h2>{{ $cv->labels['personal'] }}</h2>
                @foreach ($cv->personal as $row)
                    <p class="text">{{ $row['label'] }}: {{ $row['value'] }}</p>
                @endforeach
            @endif
        </td>
        <td class="col">
            @if ($cv->links)
                <h2>{{ $cv->labels['links'] }}</h2>
                @foreach ($cv->links as $link)
                    <p class="text">{{ $link['label'] }}: {{ $link['url'] }}</p>
                @endforeach
            @endif
        </td>
    </tr></table>
@endif
</body>
</html>
