<!DOCTYPE html>
<html lang="{{ $cv->language }}">
<head>
<meta charset="UTF-8">
<title>{{ $cv->name }}</title>
<style>
    @page { margin: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; line-height: 1.45; color: #222; margin: 0; }
    .band { position: fixed; left: 0; top: 0; bottom: 0; width: 68mm; background: #1f3a5f; }
    table.layout { width: 100%; border-collapse: collapse; }
    td.side { width: 68mm; vertical-align: top; padding: 14mm 8mm 10mm 9mm; color: #fff; font-size: 8.8pt; }
    td.main { vertical-align: top; padding: 14mm 14mm 10mm 12mm; }
    .photo { text-align: center; margin-bottom: 10px; }
    .photo img { width: 40mm; height: 40mm; }
    td.side h3 { font-size: 9pt; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #8fa6c4; padding-bottom: 2px; margin: 14px 0 6px 0; color: #fff; }
    td.side p { margin: 0 0 4px 0; word-wrap: break-word; }
    td.side .muted { color: #c5d2e4; font-size: 8pt; }
    h1 { font-size: 22pt; margin: 0; color: #1f3a5f; }
    .headline { font-size: 11pt; color: #555; margin: 2px 0 0 0; }
    h2 { font-size: 11pt; text-transform: uppercase; letter-spacing: 1px; color: #1f3a5f; border-bottom: 2px solid #1f3a5f; padding-bottom: 2px; margin: 16px 0 7px 0; }
    .entry { margin-bottom: 9px; page-break-inside: avoid; }
    .title { font-weight: bold; }
    .sub { color: #444; }
    .period { color: #777; font-size: 8.5pt; }
    .text { margin: 3px 0 0 0; }
</style>
</head>
<body>
<div class="band"></div>
<table class="layout"><tr>
<td class="side">
    @if ($cv->photo)
        <div class="photo"><img src="{{ $cv->photo }}" alt=""></div>
    @endif
    <h3>{{ $cv->labels['contact'] }}</h3>
    <p>{{ $cv->email }}</p>
    @if ($cv->phone)<p>{{ $cv->phone }}</p>@endif
    @if ($cv->location)<p>{{ $cv->location }}</p>@endif

    @if ($cv->personal)
        <h3>{{ $cv->labels['personal'] }}</h3>
        @foreach ($cv->personal as $row)
            <p><span class="muted">{{ $row['label'] }}</span><br>{{ $row['value'] }}</p>
        @endforeach
    @endif

    @if ($cv->skills)
        <h3>{{ $cv->labels['skills'] }}</h3>
        @foreach ($cv->skills as $skill)
            <p>{{ $skill }}</p>
        @endforeach
    @endif

    @if ($cv->languages)
        <h3>{{ $cv->labels['languages'] }}</h3>
        @foreach ($cv->languages as $language)
            <p>{{ $language['name'] }}<br><span class="muted">{{ $language['level'] }}</span></p>
        @endforeach
    @endif

    @if ($cv->links)
        <h3>{{ $cv->labels['links'] }}</h3>
        @foreach ($cv->links as $link)
            <p><span class="muted">{{ $link['label'] }}</span><br>{{ $link['url'] }}</p>
        @endforeach
    @endif
</td>
<td class="main">
    <h1>{{ $cv->name }}</h1>
    @if ($cv->headline)
        <p class="headline">{{ $cv->headline }}</p>
    @endif

    @if ($cv->summary)
        <h2>{{ $cv->labels['summary'] }}</h2>
        <p class="text">{!! nl2br(e($cv->summary)) !!}</p>
    @endif

    @if ($cv->experiences)
        <h2>{{ $cv->labels['experience'] }}</h2>
        @foreach ($cv->experiences as $entry)
            <div class="entry">
                <div class="title">{{ $entry['title'] }}</div>
                <div class="sub">{{ $entry['company'] }}@if ($entry['location']), {{ $entry['location'] }}@endif</div>
                <div class="period">{{ $entry['period'] }}</div>
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
                <div class="title">{{ $entry['institution'] }}</div>
                @if ($entry['title'])<div class="sub">{{ $entry['title'] }}@if ($entry['gpa']) &nbsp;&middot;&nbsp; {{ $entry['gpa'] }}@endif</div>@endif
                <div class="period">{{ $entry['period'] }}</div>
                @if ($entry['description'])
                    <p class="text">{!! nl2br(e($entry['description'])) !!}</p>
                @endif
            </div>
        @endforeach
    @endif
</td>
</tr></table>
</body>
</html>
