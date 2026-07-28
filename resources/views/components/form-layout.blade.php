@props([
    'cssFile' => 'ict-form.css',
    'title' => 'NCMB Form',
    'bannerImage' => null,
    'cspNonce' => null,
    'extraHead' => null,
])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | NCMB</title>
    <link rel="stylesheet" href="{{ asset('css/' . $cssFile) }}?v={{ filemtime(public_path('css/' . $cssFile)) }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-responsive.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @if($cspNonce)
        <script nonce="{{ $cspNonce }}" src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @endif
    @isset($extraHead)
        {{ $extraHead }}
    @endisset
</head>
<body>
    <div class="container">
        @if($bannerImage)
            <div class="header">
                <div class="banner">
                    <img src="{{ asset('images/' . $bannerImage) }}" class="banner-image" alt="NCMB Banner">
                </div>
            </div>
        @endif
        {{ $slot }}
    </div>
</body>
</html>
