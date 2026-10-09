<!DOCTYPE html>
<html lang="{{ $cv->language }}">
<head>
<meta charset="UTF-8">
<title>{{ $cv->name }}</title>
<style>
    @page { margin: 12mm 16mm 14mm 16mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; line-height: 1.45; color: #222; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; padding: 0; }
    .top { background: #1f3a5f; color: #fff; margin: -12mm -16mm 6mm -16mm; padding: 12mm 16mm 7mm 16mm; }
    .top td { vertical-align: middle; }
    .photo { width: 34mm; }
    .photo img { width: 28mm; height: 28mm; }
    h1 { font-size: 19pt; margin: 0; color: #fff; }
    .headline { font-size: 11pt; color: #c5d2e4; margin: 2px 0 0 0; }
    .contact { text-align: right; font-size: 8.5pt; color: #e3eaf4; width: 32%; }
    h2 { font-size: 10.5pt; text-transform: uppercase; letter-spacing: 1px; color: #1f3a5f; border-bottom: 2px solid #1f3a5f; padding-bottom: 2px; margin: 15px 0 7px 0; }
    .entry { margin-bottom: 9px; page-break-inside: avoid; }
    .head td.period { text-align: right; color: #666; font-size: 8.5pt; white-space: nowrap; width: 30%; }
    .title { font-weight: bold; }
    .sub { color: #444; }
    .text { margin: 3px 0 0 0; }
    .chip { display: inline-block; background: #e8eef7; color: #1f3a5f; padding: 1px 6px; margin: 0 4px 4px 0; font-size: 8.5pt; }
    .col { width: 50%; padding-right: 10px; }
    .muted { color: #666; }
</style>
</head>
<body>
<div class="top">
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
</div>

@if ($cv->summary)
    <h2>{{ $cv->labels['summary'] }}</h2>
    <p class="text">{!! nl2br(e($cv->summary)) !!}</p>
@endif

@if ($cv->experiences)
    <h2>{{ $cv->labels['experience'] }}</h2>
    @foreach ($cv->experiences as $entry)
        <div class="entry">
            <table class="head"><tr>
                <td>
                    <span class="title">{{ $entry['title'] }}</span><br>
                    <span class="sub">{{ $entry['company'] }}@if ($entry['location']), {{ $entry['location'] }}@endif</span>
                </td>
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
                <td>
                    <span class="title">{{ $entry['institution'] }}</span>
                    @if ($entry['title'])<br><span class="sub">{{ $entry['title'] }}@if ($entry['gpa']) &nbsp;&middot;&nbsp; {{ $entry['gpa'] }}@endif</span>@endif
                </td>
                <td class="period">{{ $entry['period'] }}</td>
            </tr></table>
            @if ($entry['description'])
                <p class="text">{!! nl2br(e($entry['description'])) !!}</p>
            @endif
        </div>
    @endforeach
@endif

@if ($cv->skills)
    <h2>{{ $cv->labels['skills'] }}</h2>
    <div>@foreach ($cv->skills as $skill)<span class="chip">{{ $skill }}</span> @endforeach</div>
@endif

@if ($cv->languages || $cv->personal)
    <table><tr>
        <td class="col">
            @if ($cv->languages)
                <h2>{{ $cv->labels['languages'] }}</h2>
                @foreach ($cv->languages as $language)
                    <p class="text">{{ $language['name'] }} <span class="muted">- {{ $language['level'] }}</span></p>
                @endforeach
            @endif
        </td>
        <td class="col">
            @if ($cv->personal)
                <h2>{{ $cv->labels['personal'] }}</h2>
                @foreach ($cv->personal as $row)
                    <p class="text"><span class="muted">{{ $row['label'] }}:</span> {{ $row['value'] }}</p>
                @endforeach
            @endif
        </td>
    </tr></table>
@endif

@if ($cv->links)
    <h2>{{ $cv->labels['links'] }}</h2>
    @foreach ($cv->links as $link)
        <p class="text"><span class="muted">{{ $link['label'] }}:</span> {{ $link['url'] }}</p>
    @endforeach
@endif
</body>
</html>
