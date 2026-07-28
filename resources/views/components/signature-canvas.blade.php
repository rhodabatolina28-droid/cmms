@props([
    'canvasId' => 'signature',
    'name' => 'signature',
    'label' => 'Signature',
    'canEdit' => true,
    'width' => 350,
    'height' => 64,
])

<div class="sig-container-minimal">
    <label>{{ $label }}</label>
    <canvas id="{{ $canvasId }}" width="{{ $width }}" height="{{ $height }}"
        data-name="{{ $name }}"
        {{ $canEdit ? '' : 'readonly' }}></canvas>
    @if($canEdit)
        <button type="button" class="btn-clear-sig-minimal" data-canvas="{{ $canvasId }}">Clear</button>
    @endif
</div>
