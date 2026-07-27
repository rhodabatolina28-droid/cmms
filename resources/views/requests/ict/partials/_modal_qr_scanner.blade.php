{{-- QR SCANNER MODAL --}}
{{-- Partial extracted from requests/ict/form.blade.php lines 886-895.
     No Blade variables — all interaction handled by ict-form.js / html5-qrcode. --}}
{{-- ── QR Scanner Modal ─────────────────────────────────────────────────── --}}
<div id="qrScannerModal" class="ict-scanner-modal">
    <div class="ict-scanner-card">
        <h3 class="ict-scanner-title">Scan QR Sticker</h3>
        <p class="ict-scanner-text">Point your camera at the asset QR sticker</p>
        <div id="scannerReader" class="ict-scanner-reader"></div>
        <div id="scannerStatus" class="ict-scanner-status">Initializing camera...</div>
        <button type="button" id="cancelScanBtn" class="ict-scanner-cancel">Cancel</button>
    </div>
</div>
