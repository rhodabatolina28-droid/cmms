@props(['title' => '', 'accent' => true])

<div {{ $attributes->merge(['class' => 'polish-card']) }}>
    @if($title)
        <div class="card-header{{ $accent ? '-accent' : '' }}">
            <h4 class="mb-0" style="font-weight: 800; color: #0f172a; font-size: 16px;">
                {{ $title }}
            </h4>
            @if(isset($headerActions))
                <div class="header-actions">
                    {{ $headerActions }}
                </div>
            @endif
        </div>
    @endif
    <div class="card-body-content">
        {{ $slot }}
    </div>
</div>
