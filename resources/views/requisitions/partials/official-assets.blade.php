<link href="{{ asset('css/cmms-official.css') }}?v={{ filemtime(public_path('css/cmms-official.css')) }}" rel="stylesheet">
<style nonce="{{ $cspNonce }}">
    /* Use full main area width (sidebar aside) */
    #mainContent.main:has(.cmms-official-page) {
        padding: 14px 18px 24px;
        box-sizing: border-box;
        max-width: 100%;
        overflow-x: hidden;
    }
    @media (max-width: 768px) {
        #mainContent.main:has(.cmms-official-page) {
            padding: 8px 8px 18px !important;
            max-width: 100vw !important;
            overflow-x: hidden !important;
        }
    }
    @media (min-width: 1200px) {
        #mainContent.main:has(.cmms-official-page) {
            padding: 16px 22px 28px;
        }
    }
</style>

