<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Physical count mobile scanning (user 2026-10-13): "need natin update un qr
 * scan cam natin ... since maliit nga un qr sticker natin".
 *
 * The shared scanner (public/js/qr-scanner.js — physical-count-show + ICT
 * ticket scans) ran with a FIXED 250x250px qrbox and the library's default
 * 640x480 stream: a 20mm printed QR at arm's length lands as a few dozen
 * pixels — undecodable. The config must now:
 *   - request a HIGH-RES stream (1920x1080 ideal, rear camera),
 *   - size the scan window adaptively (80% of the container, 180-320px),
 *   - remember the last used camera (faster re-scans during counting).
 */
class QrScannerConfigTest extends TestCase
{
    private function scannerJs(): string
    {
        return file_get_contents(base_path('public/js/qr-scanner.js'));
    }

    public function test_scanner_requests_high_res_rear_camera_stream(): void
    {
        $js = $this->scannerJs();

        $this->assertStringContainsString('videoConstraints', $js, 'Scanner must request an explicit high-res stream');
        $this->assertStringContainsString('width: { ideal: 1920 }', $js);
        $this->assertStringContainsString('height: { ideal: 1080 }', $js);
        $this->assertStringContainsString('facingMode: { ideal: \'environment\' }', $js, 'Rear camera is the sticker scanner');
    }

    public function test_scanner_uses_adaptive_qrbox_not_a_fixed_250px_box(): void
    {
        $js = $this->scannerJs();

        $this->assertStringNotContainsString('qrbox: { width: 250, height: 250 }', $js, 'The fixed 250px box breaks close-range small-QR scans');
        $this->assertStringContainsString('qrbox:', $js);
        $this->assertStringContainsString('Math.min(320', $js, 'Adaptive box: 80% of container, capped at 320px');
        $this->assertStringContainsString('Math.max(180', $js, 'Adaptive box floor');
        $this->assertStringContainsString('rememberLastUsedCamera: true', $js, 'Physical count does many scans in a row');
    }

    public function test_physical_count_and_ict_load_the_shared_scanner(): void
    {
        $pc = file_get_contents(resource_path('views/inventory/physical-count-show.blade.php'));
        $ict = file_get_contents(resource_path('views/partials/ict/_ict_scripts.blade.php'));

        $this->assertStringContainsString('qr-scanner.js', $pc);
        $this->assertStringContainsString('qr-scanner.js', $ict);
    }
}
