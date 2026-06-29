class AssetScanner {
    constructor(options = {}) {
        this.onScan = options.onScan || null;
        this.onError = options.onError || null;
        this.scanner = null;
        this.isScanning = false;
    }

    parseQrContent(content) {
        content = content.trim();

        // URL format: /r/5 or https://domain/r/5
        const urlMatch = content.match(/\/r\/(\d+)/i);
        if (urlMatch) {
            return parseInt(urlMatch[1], 10);
        }

        // New format: ID:{number}
        const idMatch = content.match(/^ID[:\s]*(\d+)$/i);
        if (idMatch) {
            return parseInt(idMatch[1], 10);
        }

        // Old format: JSON
        try {
            const parsed = JSON.parse(content);
            if (parsed && parsed.id) {
                return parseInt(parsed.id, 10);
            }
        } catch (e) {
            // not JSON
        }

        // Fallback: try direct numeric
        const numMatch = content.match(/(\d+)/);
        if (numMatch) {
            return parseInt(numMatch[1], 10);
        }

        return null;
    }

    async startCamera(elementId) {
        if (typeof Html5Qrcode === 'undefined') {
            if (this.onError) this.onError('html5-qrcode library not loaded');
            return;
        }

        if (this.isScanning) return;

        const container = document.getElementById(elementId);
        if (container) container.innerHTML = '';

        try {
            this.scanner = new Html5Qrcode(elementId);
            this.isScanning = true;

            const config = { fps: 10, qrbox: { width: 250, height: 250 } };
            const onSuccess = (decodedText) => {
                const assetId = this.parseQrContent(decodedText);
                if (assetId && this.onScan) {
                    this.stopCamera();
                    this.onScan(assetId, decodedText);
                }
            };

            try {
                // Try environment (rear) camera first
                await this.scanner.start({ facingMode: 'environment' }, config, onSuccess, () => {});
            } catch (e) {
                try {
                    // Fallback to whatever camera is available
                    const devices = await Html5Qrcode.getCameras();
                    if (devices && devices.length) {
                        await this.scanner.start(devices[devices.length - 1].id, config, onSuccess, () => {});
                    } else {
                        throw e;
                    }
                } catch (e2) {
                    throw e2;
                }
            }
        } catch (err) {
            this.isScanning = false;
            if (this.onError) this.onError(err);
        }
    }

    async stopCamera() {
        // Reset the flag FIRST (synchronously) so that a quick reopen does not bail
        // out of startCamera() due to isScanning still being true while we await stop().
        this.isScanning = false;
        if (this.scanner) {
            try {
                await this.scanner.stop();
                this.scanner.clear();
            } catch (e) {}
            this.scanner = null;
        }
    }

    isCameraAvailable() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }
}

