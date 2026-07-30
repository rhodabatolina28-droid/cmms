    {{-- ── Data islands for JS auto-fill ──────────────────────────────────── --}}
    <script nonce="{{ $cspNonce }}">
        // IT user parsed name (only populated when role === 'it')
        const IT_USER_PARTS = @json($itUserParts);

        // Linked asset data (only populated when ticket has a linked asset)
        const LINKED_ASSET = @json($linkedAssetData);
        
        // ICT Assets map for auto-fill on asset selection
        const ICT_ASSETS_MAP = @json($ictAssetsMap ?? []);
        
        const HAS_ASSIGNED_ASSETS = @json($hasAssignedAssets ?? true);
        const IS_NEW_USER_REQUEST = @json($isNewUserRequest ?? false);
        const IS_SUPER_ADMIN = @json($isSuperAdmin ?? false);
        const PRESELECTED_ASSET_ID = @json($preselectedAssetId ?? null);
    </script>

    <script nonce="{{ $cspNonce }}">
        const signaturePads = {};

        function initSignaturePad(canvasId, hiddenInputId) {
            const canvas = document.getElementById(canvasId);
            const hiddenInput = document.getElementById(hiddenInputId);

            if (!canvas || !hiddenInput) return;
            if (canvas.closest('.disabled-section')) return;

            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;

            ctx.strokeStyle = '#000';
            ctx.lineWidth = 1.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            ctx.fillStyle = '#fafafa';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const getPos = (e) => {
                const rect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            };

            const startDrawing = (e) => {
                isDrawing = true;
                const pos = getPos(e);
                lastX = pos.x;
                lastY = pos.y;
            };

            const draw = (e) => {
                if (!isDrawing) return;
                e.preventDefault();
                const pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                lastX = pos.x;
                lastY = pos.y;
                hiddenInput.value = canvas.toDataURL('image/png');
            };

            const stopDrawing = () => {
                isDrawing = false;
            };

            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            window.addEventListener('mouseup', stopDrawing);

            canvas.addEventListener('touchstart', startDrawing, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', stopDrawing);
            canvas.addEventListener('touchcancel', stopDrawing);
            
            // Prevent scrolling while signing
            canvas.style.touchAction = 'none';
        }

        function clearSignature(canvasId, hiddenInputId) {
            const canvas = document.getElementById(canvasId);
            const hiddenInput = document.getElementById(hiddenInputId);
            if (canvas && hiddenInput) {
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fafafa';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                hiddenInput.value = '';
            }
        }

        // ── IT Auto-fill logic ───────────────────────────────────────────────
        function autoFillItSection() {
            // Fill IT personnel name only when the fields are empty (first open, not on re-edit)
            if (IT_USER_PARTS && IT_USER_PARTS.last_name) {
                const lastEl   = document.getElementById('itReceivedLastName');
                const firstEl  = document.getElementById('itReceivedFirstName');
                const middleEl = document.getElementById('itReceivedMiddleName');

                if (lastEl  && !lastEl.value)  lastEl.value  = IT_USER_PARTS.last_name;
                if (firstEl && !firstEl.value) firstEl.value = IT_USER_PARTS.first_name;
                if (middleEl && !middleEl.value) middleEl.value = IT_USER_PARTS.middle_name;
            }

            if (LINKED_ASSET) {
                const cat   = (LINKED_ASSET.category || '').toLowerCase();
                const specs = LINKED_ASSET.specifications || {};

                // ARTICLE / SERIAL NO (serial_number only)
                const snEl = document.getElementById('articleSerialNo');
                if (snEl && !snEl.value && LINKED_ASSET.serial_number) {
                    snEl.value = LINKED_ASSET.serial_number;
                }

                // PROPERTY NO (property_number only)
                const propEl = document.getElementById('propertyNo');
                if (propEl && !propEl.value && LINKED_ASSET.property_number) {
                    propEl.value = LINKED_ASSET.property_number;
                }

                // OFFICE / DATE ACQUIRED
                const dateEl = document.getElementById('officeDateAcquired');
                if (dateEl && !dateEl.value && LINKED_ASSET.date_acquired) {
                    dateEl.value = LINKED_ASSET.date_acquired;
                }
            }
        }

        /**
         * Auto-fill ICT form fields when user selects an asset from the dropdown.
         * Fills: articleSerialNo, propertyNo, officeDateAcquired
         */
        function ictAutoFillFromAsset(assetId) {
            if (!assetId || !ICT_ASSETS_MAP[assetId]) return;

            const asset = ICT_ASSETS_MAP[assetId];
            console.log('DEBUG: ictAutoFillFromAsset called with assetId:', assetId);
            console.log('DEBUG: asset:', asset);

            // Auto-fill ARTICLE / SERIAL NO (from serial_number only)
            const snEl = document.getElementById('articleSerialNo');
            if (snEl && asset.serial_number) {
                snEl.value = asset.serial_number;
                console.log('DEBUG: Filled articleSerialNo with:', asset.serial_number);
            }

            // Auto-fill PROPERTY NO (from property_number only)
            const propEl = document.getElementById('propertyNo');
            if (propEl && asset.property_number) {
                propEl.value = asset.property_number;
                console.log('DEBUG: Filled propertyNo with:', asset.property_number);
            }

            // Auto-fill OFFICE / DATE ACQUIRED
            const dateEl = document.getElementById('officeDateAcquired');
            if (dateEl && asset.date_acquired) {
                dateEl.value = asset.date_acquired;
                console.log('DEBUG: Filled officeDateAcquired with:', asset.date_acquired);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initSignaturePad('endUserSignatureCanvas', 'endUserSignature');
            initSignaturePad('technicianSignatureCanvas', 'technicianSignature');
            initSignaturePad('itPersonnelSignatureCanvas', 'itPersonnelSignature');
            initSignaturePad('endUserAcceptanceSignatureCanvas', 'endUserAcceptanceSignature');

            // Auto-fill IT section on load
            autoFillItSection();

            // Asset dropdown auto-fill
            const assetSelect = document.getElementById('linked_asset_id');
            if (assetSelect) {
                assetSelect.addEventListener('change', function () {
                    ictAutoFillFromAsset(this.value);
                });
            }

            // Radio styling
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const group = this.name;
                    document.querySelectorAll(`input[name="${group}"]`).forEach(r => {
                        r.closest('.radio-label').classList.remove('radio-checked');
                    });
                    this.closest('.radio-label').classList.add('radio-checked');
                });
            });

            // Service Provider checkbox wiring
            document.querySelectorAll('.repair-type-cb').forEach(cb => {
                cb.addEventListener('change', function () {
                    const spBanner = document.getElementById('referredSpBanner');
                    const spSection = document.getElementById('serviceProviderSection');
                    const anySpChecked = document.querySelector('.repair-type-cb[data-triggers-sp="1"]:checked');
                    if (spBanner) spBanner.style.display = anySpChecked ? 'block' : 'none';
                    if (spSection) {
                        const keepActive = spSection.dataset.keepActive === '1';
                        if (anySpChecked || keepActive) {
                            spSection.classList.remove('disabled-section');
                            spSection.querySelectorAll('input, textarea, select').forEach(el => el.removeAttribute('disabled'));
                        } else {
                            spSection.classList.add('disabled-section');
                            spSection.querySelectorAll('input, textarea, select').forEach(el => el.setAttribute('disabled', 'disabled'));
                        }
                    }
                });
            });

            // Super Admin self-assign via dropdown
            const assignItSelect = document.getElementById('assignItSelect');
            const currentUserId = '{{ Auth::user()->id }}';
            
            if (assignItSelect) {
                const toggleItSection = () => {
                    const itSection = document.getElementById('itPersonnelAfterRepairSection');
                    const isSelfAssigned = assignItSelect.value === currentUserId;
                    
                    // Only the assigned IT/Admin personnel can edit Section 5
                    // Super Admin must assign themselves first before they can edit
                    if (isSelfAssigned) {
                        // Enable IT section when self-assigned
                        itSection.classList.remove('disabled-section');
                        itSection.querySelectorAll('input, textarea, select').forEach(el => el.removeAttribute('disabled'));
                        itSection.querySelectorAll('.signature-controls').forEach(el => el.classList.remove('hidden'));
                    } else {
                        // Disable IT section for all other cases (must be assigned first)
                        itSection.classList.add('disabled-section');
                        itSection.querySelectorAll('input, textarea, select').forEach(el => el.setAttribute('disabled', 'disabled'));
                        itSection.querySelectorAll('.signature-controls').forEach(el => el.classList.add('hidden'));
                    }
                };
                
                assignItSelect.addEventListener('change', toggleItSection);
                toggleItSection(); // Initialize on load
            }

            // Form submission via AJAX
            const form = document.getElementById('repairRequestForm');
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const isNewRequest = !form.querySelector('input[name="_method"]');
                if (IS_NEW_USER_REQUEST && !HAS_ASSIGNED_ASSETS) {
                    Swal.fire({
                        icon: 'error',
                        title: 'No Assigned Equipment',
                        text: 'You cannot submit until the Administrative supply admin assigns accountable equipment to your account.',
                        confirmButtonColor: '#0038A8'
                    });
                    return;
                }
                if (isNewRequest) {
                    const assetSel = document.getElementById('linked_asset_id');
                    if (assetSel && !assetSel.disabled && !assetSel.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Asset Required',
                            text: 'Please select the device or asset to be repaired.',
                            confirmButtonColor: '#0038A8'
                        });
                        return;
                    }
                }

                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Submitting...';
                }

                const formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            confirmButtonColor: '#0038A8'
                        }).then(() => {
                            if (data.print_url) {
                                window.open(data.print_url, '_blank');
                            }
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            }
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = form.querySelector('[name="_method"]') ? 'Update Request' : 'Submit Request';
                        }
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'An unexpected error occurred.' });
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = form.querySelector('[name="_method"]') ? 'Update Request' : 'Submit Request';
                    }
                });
            });

            // Assign IT button
            const assignItBtn = document.getElementById('assignItBtn');
            if (assignItBtn) {
                assignItBtn.addEventListener('click', function() {
                    const select = document.getElementById('assignItSelect');
                    const assignedTo = select ? select.value : '';
                    const url = '{{ $isUpdate ? route("ict.assign-it", $request->id) : "" }}';

                    if (!url) return;

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ assigned_to: assignedTo })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Assigned!', text: data.message, confirmButtonColor: '#0038A8' })
                                .then(() => window.location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                        }
                    });
                });
            }
            // Division Admin Review Buttons
            const divisionReviewBtns = document.querySelectorAll('.division-review-btn');
            if (divisionReviewBtns.length > 0) {
                divisionReviewBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        const status = this.getAttribute('data-status');
                        const notes = document.getElementById('divisionAdminNotes').value;
                        const url = '{{ $isUpdate ? route("ict.review", $request->id) : "" }}';

                        if (!url) return;

                        let confirmMessage = status === 'Approved' 
                            ? 'Approve and forward this request to Super Admin?' 
                            : 'Reject this request?';

                        Swal.fire({
                            title: 'Confirm Review',
                            text: confirmMessage,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: status === 'Approved' ? '#10b981' : '#ef4444',
                            confirmButtonText: 'Yes, ' + status
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch(url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    },
                                    body: JSON.stringify({
                                        status: status,
                                        notes: notes
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire({
                                            title: 'Success!',
                                            text: data.message,
                                            icon: 'success'
                                        }).then(() => {
                                            window.location.reload();
                                        });
                                    } else {
                                        Swal.fire('Error', data.message || 'Something went wrong.', 'error');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    Swal.fire('Error', 'Failed to submit review.', 'error');
                                });
                            }
                        });
                    });
                });
            }

        });
    </script>

    {{-- ── QR Scanner libraries & logic ──────────────────────────────────── --}}
    <script nonce="{{ $cspNonce }}" src="{{ asset('js/html5-qrcode.min.js') }}"></script>
    @vite(['resources/js/qr-scanner.js'])
    <script nonce="{{ $cspNonce }}">
    (function() {
        // ── URL param auto-select ────────────────────────────────────────
        const urlParams = new URLSearchParams(window.location.search);
        const assetIdFromUrl = urlParams.get('asset_id');

        // If redirect from scan, use param; otherwise use PRESELECTED_ASSET_ID from server
        const preselectedId = assetIdFromUrl || (typeof PRESELECTED_ASSET_ID !== 'undefined' ? PRESELECTED_ASSET_ID : null);

        if (preselectedId) {
            const select = document.getElementById('linked_asset_id');
            if (select) {
                const option = select.querySelector('option[value="' + preselectedId + '"]');
                if (option) {
                    select.value = preselectedId;
                    // ictAutoFillFromAsset is defined globally in the script block above.
                    // We call it directly here because this IIFE runs before DOMContentLoaded
                    // fires, so the 'change' event listener hasn't been attached yet.
                    if (typeof ictAutoFillFromAsset === 'function') {
                        ictAutoFillFromAsset(parseInt(preselectedId, 10));
                    }
                    // Also dispatch the event so any other listeners can react
                    const event = new Event('change', { bubbles: true });
                    select.dispatchEvent(event);
                } else {
                    // Asset not in the list (status filtered out) — show friendly warning
                    console.warn('Pre-selected asset ID ' + preselectedId + ' not found in asset dropdown. It may be For Repair or already linked.');
                }
            }
        }

        // ── Scan button → camera modal ───────────────────────────────────
        const scanBtn = document.getElementById('scanQrBtn');
        const modal = document.getElementById('qrScannerModal');
        const cancelBtn = document.getElementById('cancelScanBtn');
        const readerEl = document.getElementById('scannerReader');
        const statusEl = document.getElementById('scannerStatus');

        if (!scanBtn || !modal) return;

        let scanner = null;
        let isScanning = false;

        const assetScanner = new AssetScanner({
            onScan: function(assetId) {
                handleScanResult(assetId);
            },
            onError: function(err) {
                statusEl.textContent = 'Camera error: ' + (err.message || err);
            }
        });

        function openScanner() {
            modal.style.display = 'flex';
            statusEl.textContent = 'Initializing camera...';

            if (!assetScanner.isCameraAvailable()) {
                statusEl.textContent = 'Camera not available on this device/browser. Try using the dropdown.';
                return;
            }

            isScanning = true;
            setTimeout(async () => {
                try {
                    await assetScanner.startCamera('scannerReader');
                    statusEl.textContent = 'Point your camera at the QR code...';
                } catch (err) {
                    statusEl.textContent = 'Failed to start camera: ' + (err.message || err);
                }
            }, 300);
        }

        function closeScanner() {
            if (isScanning) {
                assetScanner.stopCamera();
                isScanning = false;
            }
            modal.style.display = 'none';
        }

        function handleScanResult(assetId) {
            closeScanner();

            const select = document.getElementById('linked_asset_id');
            if (!select) return;

            const option = select.querySelector('option[value="' + assetId + '"]');
            if (option) {
                select.value = assetId;
                const event = new Event('change', { bubbles: true });
                select.dispatchEvent(event);
                Swal.fire({
                    icon: 'success',
                    title: 'Asset Found!',
                    text: select.options[select.selectedIndex].text,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Asset Not Found',
                    text: 'The scanned asset (ID: ' + assetId + ') is not in your assigned assets list.'
                });
            }
        }

        scanBtn.addEventListener('click', openScanner);
        cancelBtn.addEventListener('click', closeScanner);

        // Close on backdrop click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeScanner();
        });

        // Stop camera when modal closes
        window.addEventListener('beforeunload', function() {
            if (isScanning) assetScanner.stopCamera();
        });
    })();
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-canvas]');
        if (btn) {
            clearSignature(btn.dataset.canvas, btn.dataset.input);
        }
    });
    </script>