@props([
    'show' => false,
    'ticket' => null,
    'showPdf' => true,
    'showToggleEdit' => true,
    'showBack' => true,
    'pdfRoute' => null,
    'backRoute' => null,
])

@if($show)
<div id="adminControls" class="admin-controls">
    @if($showPdf && $ticket)
        <a href="{{ $pdfRoute ?: route('maintenance.pdf', $ticket->id) }}" class="pdf-link">PDF</a>
    @endif
    @if($showToggleEdit)
        <button type="button" class="toggle-edit-btn">Toggle Edit</button>
    @endif
    @if($showBack)
        <a href="{{ $backRoute ?: route('maintenance.index') }}" class="btn-back-link">Back</a>
    @endif
</div>
@endif
