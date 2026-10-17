@isset($frame)
<div style="margin-bottom: 16px;">
@if ($frame['logo_url'] !== null)
<img src="{{ $frame['logo_url'] }}" alt="{{ $frame['name'] }}" style="max-height: 64px; max-width: 240px;">
@else
<strong>{{ $frame['name'] }}</strong>
@endif
</div>
@endisset
{!! $html !!}
@isset($frame)
<p style="color: #6b7280; font-size: 12px;">{{ $frame['notice'] }}</p>
@endisset
