<!DOCTYPE html>
<html lang="{{ $cv->language }}">
<head>
<meta charset="UTF-8">
<title>{{ $cv->name }}</title>
<style>
    @page { margin: 18mm 18mm 16mm 18mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; line-height: 1.45; color: #222; }
    h1 { font-size: 21pt; margin: 0; text-align: center; letter-spacing: 0.5px; }
    .headline { text-align: center; font-size: 11pt; color: #555; margin: 2px 0 0 0; }
    .contact { text-align: center; font-size: 8.5pt; color: #555; margin: 6px 0 0 0; }
    .photo { text-align: center; margin-bottom: 8px; }
    .photo img { width: 28mm; height: 28mm; }
    h2 { font-size: 10.5pt; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #222; padding-bottom: 2px; margin: 16px 0 7px 0; }
    .entry { margin-bottom: 8px; page-break-inside: avoid; }
    .entry-head { width: 100%; border-collapse: collapse; }
    .entry-head td { padding: 0; vertical-align: top; }
    .period { text-align: right; color: #555; white-space: nowrap; font-size: 8.5pt; width: 32%; }
    .title { font-weight: bold; }
    .sub { color: #444; }
    .text { margin: 2px 0 0 0; }
    .personal td { padding: 1px 12px 1px 0; vertical-align: top; }
    .personal td.label { color: #555; width: 34%; }
    .tags span { display: inline-block; border: 1px solid #999; padding: 1px 6px; margin: 0 4px 4px 0; font-size: 8.5pt; }
</style>
</head>
<body>
@if ($cv->photo)
    <div class="photo"><img src="{{ $cv->photo }}" alt=""></div>
@endif
<h1>{{ $cv->name }}</h1>
@if ($cv->headline)
    <p class="headline">{{ $cv->headline }}</p>
@endif
<p class="contact">
    {{ $cv->email }}@if ($cv->phone) &nbsp;|&nbsp; {{ $cv->phone }}@endif @if ($cv->location) &nbsp;|&nbsp; {{ $cv->location }}@endif
</p>

@if ($cv->summary)
    <h2>{{ $cv->labels['summary'] }}</h2>
    <p class="text">{!! nl2br(e($cv->summary)) !!}</p>
@endif

@if ($cv->experiences)
    <h2>{{ $cv->labels['experience'] }}</h2>
    @foreach ($cv->experiences as $entry)
        <div class="entry">
            <table class="entry-head"><tr>
                <td><span class="title">{{ $entry['title'] }}</span><br><span class="sub">{{ $entry['company'] }}@if ($entry['location']), {{ $entry['location'] }}@endif</span></td>
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
            <table class="entry-head"><tr>
                <td><span class="title">{{ $entry['institution'] }}</span>@if ($entry['title'])<br><span class="sub">{{ $entry['title'] }}@if ($entry['gpa']) &nbsp;&middot;&nbsp; {{ $entry['gpa'] }}@endif</span>@endif</td>
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
    <div class="tags">@foreach ($cv->skills as $skill)<span>{{ $skill }}</span> @endforeach</div>
@endif

@if ($cv->languages)
    <h2>{{ $cv->labels['languages'] }}</h2>
    <table class="personal">
        @foreach ($cv->languages as $language)
            <tr><td class="label">{{ $language['name'] }}</td><td>{{ $language['level'] }}</td></tr>
        @endforeach
    </table>
@endif

@if ($cv->personal)
    <h2>{{ $cv->labels['personal'] }}</h2>
    <table class="personal">
        @foreach ($cv->personal as $row)
            <tr><td class="label">{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
        @endforeach
    </table>
@endif

@if ($cv->links)
    <h2>{{ $cv->labels['links'] }}</h2>
    <table class="personal">
        @foreach ($cv->links as $link)
            <tr><td class="label">{{ $link['label'] }}</td><td>{{ $link['url'] }}</td></tr>
        @endforeach
    </table>
@endif
</body>
</html>
