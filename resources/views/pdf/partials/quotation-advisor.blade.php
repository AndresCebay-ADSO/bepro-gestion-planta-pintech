<div class="advisor-label">Asesor:</div>
<div class="advisor-signature">
    @if (!empty($quotation['advisor']['signature']))
        <img src="{{ $quotation['advisor']['signature'] }}" alt="Firma del asesor">
    @endif
</div>
<div class="advisor-name">{{ $quotation['advisor']['name'] ?? 'N.D.' }}</div>
<div class="advisor-detail">
    @if (!empty($quotation['advisor']['job_title']))
        <div>{{ $quotation['advisor']['job_title'] }}</div>
    @endif
    @if (!empty($quotation['advisor']['phone']))
        <div>Móvil: {{ $quotation['advisor']['phone'] }}</div>
    @endif
    @if (!empty($quotation['advisor']['email']))
        <div>Email: {{ $quotation['advisor']['email'] }}</div>
    @endif
</div>
