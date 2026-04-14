{{--
    Header de emails Pintech OS
    Usa el logo oficial 2026 de la marca
    Para guías de marca ver: /docs/LOGOS.md
--}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel' || trim($slot) === 'Pintech OS')
<img src="{{ rtrim(config('app.url'), '/') . '/images/logo-pintech.png' }}" class="logo" alt="Pintech OS Logo" style="height:64px;width:auto;object-fit:contain;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
