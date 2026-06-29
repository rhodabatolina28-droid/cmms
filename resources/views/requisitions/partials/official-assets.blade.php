<link href="{{ asset('css/cmms-official.css') }}?v={{ filemtime(public_path('css/cmms-official.css')) }}" rel="stylesheet">
<style nonce="{{ $cspNonce }}">
    /* Use full main area width (sidebar aside) */
    #mainContent.main:has(.cmms-official-page) {
        padding: 14px 18px 24px;
    }
    @media (min-width: 1200px) {
        #mainContent.main:has(.cmms-official-page) {
            padding: 16px 22px 28px;
        }
    }
</style>
