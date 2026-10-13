<div class="notes-title">Notas y alcance:</div>
<table class="notes-table">
    @foreach (config('quotation.legal_notes', []) as $note)
        <tr>
            <td class="dash">-</td>
            <td>{{ $note }}</td>
        </tr>
    @endforeach
</table>
