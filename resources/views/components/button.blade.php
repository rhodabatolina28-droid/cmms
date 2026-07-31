@props(['type' => 'button', 'icon' => '', 'color' => 'modern', 'href' => null])

@php
    $class = "btn-action-{$color}";
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>
        @if($icon) <i class="{{ $icon }}"></i> @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>
        @if($icon) <i class="{{ $icon }}"></i> @endif
        {{ $slot }}
    </button>
@endif
