@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel' || trim($slot) === 'Pintech OS')
<img src="{{ rtrim(config('app.url'), '/') . '/favicon-logo.png' }}" class="logo" alt="Pintech OS Logo" style="height:64px;width:64px;object-fit:contain;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
