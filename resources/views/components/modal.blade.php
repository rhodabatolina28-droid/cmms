@props(['id', 'title' => '', 'maxWidth' => '680px', 'noPadding' => false, 'titleId' => null, 'hideClose' => false])

<!-- {{ $title }} MODAL -->
<div id="{{ $id }}" class="modal-overlay">
    <div class="modal-card" style="max-width: {{ $maxWidth }};">
        <div class="modal-header">
            <h4 class="modal-h4" @if(isset($titleId)) id="{{ $titleId }}" @endif>{!! $title !!}</h4>
            @if(!isset($hideClose))
            <button type="button" class="btn-close close-btn" onclick="document.getElementById('{{ $id }}').style.display = 'none';">
                <i class="fa-solid fa-xmark"></i>
            </button>
            @endif
        </div>
        {{ $slot }}
    </div>
</div>
