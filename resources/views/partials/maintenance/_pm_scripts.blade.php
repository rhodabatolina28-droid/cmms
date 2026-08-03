<script nonce="{{ $cspNonce }}">
        // Signature and Admin Controls Logic
        function clearSignature(canvasId, hiddenInputId) {
            const canvas = document.getElementById(canvasId);
            const hiddenInput = document.getElementById(hiddenInputId);
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fafafa';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                hiddenInput.value = '';
            }
        }

        function initSignature(canvasId, hiddenInputId) { const canvas = document.getElementById(canvasId); const hiddenInput = document.getElementById(hiddenInputId); if (!canvas || !hiddenInput) return; if (canvas.closest('.disabled-section')) return; const ctx = canvas.getContext('2d'); let drawing = false; let lastX = 0, lastY = 0; ctx.strokeStyle = '#000'; ctx.lineWidth = 1.5; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; if (hiddenInput.value && hiddenInput.value.startsWith('data:image')) { const img = new Image(); img.onload = () => ctx.drawImage(img, 0, 0); img.src = hiddenInput.value; } const getPos = (e) => { const rect = canvas.getBoundingClientRect(); const clientX = e.touches ? e.touches[0].clientX : e.clientX; const clientY = e.touches ? e.touches[0].clientY : e.clientY; return { x: clientX - rect.left, y: clientY - rect.top }; }; const startDraw = (e) => { e.preventDefault(); drawing = true; const pos = getPos(e); lastX = pos.x; lastY = pos.y; }; const doDraw = (e) => { if (!drawing) return; e.preventDefault(); const pos = getPos(e); ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(pos.x, pos.y); ctx.stroke(); lastX = pos.x; lastY = pos.y; hiddenInput.value = canvas.toDataURL(); }; const stopDraw = () => { drawing = false; }; canvas.addEventListener('mousedown', startDraw); canvas.addEventListener('mousemove', doDraw); window.addEventListener('mouseup', stopDraw); canvas.addEventListener('touchstart', startDraw, { passive: false }); canvas.addEventListener('touchmove', doDraw, { passive: false }); canvas.addEventListener('touchend', stopDraw); canvas.addEventListener('touchcancel', stopDraw); canvas.style.touchAction = 'none'; } document.addEventListener('DOMContentLoaded', () => {
            // Initialize signatures
            const canvases = ['technicianSignatureCanvas', 'endUserSignatureCanvas'];
            canvases.forEach(id => {
                const canvas = document.getElementById(id);
                const hiddenInput = document.getElementById(id.replace('Canvas', ''));
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                let drawing = false;
                let lastX = 0, lastY = 0;
                ctx.strokeStyle = '#000'; ctx.lineWidth = 1.5;
                ctx.lineCap = 'round'; ctx.lineJoin = 'round'; // Smoother lines
                
                // Restore saved signature
                if (hiddenInput.value && hiddenInput.value.startsWith('data:image')) {
                    const img = new Image();
                    img.onload = () => ctx.drawImage(img, 0, 0);
                    img.src = hiddenInput.value;
                }

                const getPos = (e) => {
                    const rect = canvas.getBoundingClientRect();
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    return { x: clientX - rect.left, y: clientY - rect.top };
                };

                const startDraw = (e) => {
                    e.preventDefault();
                    drawing = true;
                    const pos = getPos(e);
                    lastX = pos.x; lastY = pos.y;
                };

                const doDraw = (e) => {
                    if (!drawing) return;
                    e.preventDefault();
                    const pos = getPos(e);
                    ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(pos.x, pos.y); ctx.stroke();
                    lastX = pos.x; lastY = pos.y;
                    hiddenInput.value = canvas.toDataURL();
                };

                const stopDraw = () => { drawing = false; };

                // Mouse events (desktop)
                canvas.addEventListener('mousedown', startDraw);
                canvas.addEventListener('mousemove', doDraw);
                window.addEventListener('mouseup', stopDraw);

                // Touch events (mobile)
                canvas.addEventListener('touchstart', startDraw, { passive: false });
                canvas.addEventListener('touchmove', doDraw, { passive: false });
                canvas.addEventListener('touchend', stopDraw);
                canvas.addEventListener('touchcancel', stopDraw);
                
                // Prevent scrolling while signing
                canvas.style.touchAction = 'none';
            });

            // FOR DISPOSAL checkbox toggle
            const forDisposalCheck = document.getElementById('forDisposalCheck');
            const disposalAssetSelector = document.getElementById('disposalAssetSelector');
            if (forDisposalCheck && disposalAssetSelector) {
                forDisposalCheck.addEventListener('change', function() {
                    disposalAssetSelector.style.display = this.checked ? 'block' : 'none';
                    if (!this.checked) {
                        document.querySelector('[name="disposal_asset_id"]').value = '';
                    }
                });
            }

            // AJAX Form Submission
            const form = document.getElementById('pmForm');
            if (form) {
                // Handle Monitor and Printer Count Display Logic
                const monitorCountSelect = document.getElementById('monitorCountSelect');
                if (monitorCountSelect) {
                    const toggleMonitorRows = () => {
                        const count = monitorCountSelect.value;
                        document.querySelectorAll('.monitor-2-row, .monitor-2-checklist-row').forEach(row => {
                            row.style.display = count === '2' ? '' : 'none';
                        });
                    };
                    monitorCountSelect.addEventListener('change', toggleMonitorRows);
                    toggleMonitorRows(); // trigger on load
                }

                const printerCountSelect = document.getElementById('printerCountSelect');
                if (printerCountSelect) {
                    const togglePrinterRows = () => {
                        const count = printerCountSelect.value;
                        document.querySelectorAll('.printer-2-row, .printer-2-checklist-row').forEach(row => {
                            row.style.display = count === '2' ? '' : 'none';
                        });
                    };
                    printerCountSelect.addEventListener('change', togglePrinterRows);
                    togglePrinterRows(); // trigger on load
                }

                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (!this.querySelector('input[name="_method"]')) {
                        const pmAssetSel = document.getElementById('pm_linked_asset_id');
                        if (pmAssetSel && pmAssetSel.tagName === 'SELECT' && !pmAssetSel.value) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Asset Required',
                                text: 'Please select the accountable device or asset for this maintenance request.',
                                confirmButtonColor: '#0038A8'
                            });
                            return;
                        }
                    }

                    // Manual Signature Validation
                    const techSig = document.getElementById('technicianSignature');
                    if (techSig && !techSig.disabled && !techSig.closest('.disabled-section') && !techSig.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Signature Required',
                            text: 'Please provide the Technician Signature before submitting.',
                            confirmButtonColor: '#0038A8'
                        });
                        return;
                    }
                    const endUserSig = document.getElementById('endUserSignature');
                    if (endUserSig && !endUserSig.closest('.disabled-section') && !endUserSig.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Signature Required',
                            text: 'Please provide your End-User Signature before submitting.',
                            confirmButtonColor: '#0038A8'
                        });
                        return;
                    }
                    const endUserPrinted = document.querySelector('input[name="end_user_printed_name"]');
                    if (endUserPrinted && !endUserPrinted.closest('.disabled-section') && !endUserPrinted.disabled && !endUserPrinted.value.trim()) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Printed Name Required',
                            text: 'Please enter your printed name below your signature.',
                            confirmButtonColor: '#0038A8'
                        });
                        return;
                    }
                    
                    unlockPmSpecFields();
                    const formData = new FormData(this);
                    const url = this.getAttribute('action');
                    const method = this.querySelector('input[name="_method"]')?.value || 'POST';

                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.textContent;
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Submitting...';

                    fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message,
                                confirmButtonColor: '#0038A8'
                            }).then(() => {
                                if (data.redirect) {
                                    window.location.href = data.redirect;
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.message,
                                confirmButtonColor: '#0038A8'
                            });
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalBtnText;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An unexpected error occurred.',
                            confirmButtonColor: '#0038A8'
                        });
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText;
                    });
                });
            }

            // Handle Admin Controls
            const enableEditBtn = document.getElementById('enableEditBtn');
            if (enableEditBtn) {
                enableEditBtn.addEventListener('click', function() {
                    const sections = ['technicianSection', 'deviceInfoSection', 'analysisSection', 'suggestionSection', 'checklistSection'];
                    const isEditing = this.textContent.includes("View Only");
                    sections.forEach(id => {
                        const sec = document.getElementById(id);
                        if (sec) {
                            if (isEditing) {
                                sec.classList.add('disabled-section');
                                sec.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
                            } else {
                                sec.classList.remove('disabled-section');
                                sec.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
                            }
                        }
                    });
                    this.textContent = isEditing ? "Enable Editing" : "Switch to View Only";
                    this.style.backgroundColor = isEditing ? "#28a745" : "#dc3545";
                });
            }

            const assignItBtn = document.getElementById('assignItBtn');
            if (assignItBtn) {
                assignItBtn.addEventListener('click', function () {
                    const select = document.getElementById('assignItSelect');
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    assignItBtn.disabled = true;
                    fetch('{{ $request ? route("maintenance.assign", $request->id) : "" }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ assigned_to: select.value || null }),
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Assigned', text: data.message, confirmButtonColor: '#0038A8' })
                                .then(() => {
                                    // Redirect to PM Work Orders page after assignment
                                    window.location.href = '{{ route("pm-schedules.orders") }}';
                                });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Assignment failed', confirmButtonColor: '#0038A8' });
                            assignItBtn.disabled = false;
                        }
                    })
                    .catch(() => {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Could not save assignment.', confirmButtonColor: '#0038A8' });
                        assignItBtn.disabled = false;
                    });
                });
            }

            // Super Admin self-assign via dropdown
            const assignItSelect = document.getElementById('assignItSelect');
            const currentUserId = '{{ Auth::user()->id }}';
            
            if (assignItSelect) {
                const toggleItSection = () => {
                    // For maintenance form, there's no IT section to toggle like ICT form
                    // This just ensures the dropdown change is registered
                };
                
                assignItSelect.addEventListener('change', toggleItSection);
            }


        });
    </script>

    {{-- ── PM Asset auto-fill data island + logic ────────────────────────── --}}
    <script nonce="{{ $cspNonce }}">
        const PM_ASSETS_MAP = @json($assetsMap ?? []);
        console.log('DEBUG: PM_ASSETS_MAP initialized:', PM_ASSETS_MAP);

        /**
         * List of auto-fillable device/specs field names.
         */
        const PM_SPEC_FIELD_NAMES = [
            'desktopBrand', 'desktopModel', 'desktopPno', 'computerName',
            'monitor1Pno', 'monitor1Brand', 'monitor1Model',
            'monitor2Pno', 'monitor2Brand', 'monitor2Model',
            'printer1Pno', 'printer1Brand', 'printer1Model',
            'printer2Pno', 'printer2Brand', 'printer2Model',
            'upsPno', 'upsBrand', 'upsModel',
            'scannerPno', 'scannerBrand', 'scannerModel',
            'laptopPno', 'laptopBrand', 'laptopModel', 'laptopComputerName',
            'webcamBrand', 'webcamModel', 'webcamPno',
            'speakersBrand', 'speakersModel', 'speakersPno',
            'earphoneBrand', 'earphoneModel',
            'dtCpu', 'dtRam', 'dtGpu', 'dtOs', 'dtHd1', 'dtHd2', 'dtOffice', 'dtYear',
            'ltCpu', 'ltRam', 'ltGpu', 'ltOs', 'ltHd1', 'ltHd2', 'ltOffice', 'ltYear'
        ];

        /**
         * Clears all auto-fillable device information inputs.
         */
        function clearPmDeviceFields() {
            PM_SPEC_FIELD_NAMES.forEach(name => {
                const el = document.querySelector(`[name="${name}"]`);
                if (el) {
                    el.value = '';
                    el.disabled = false;
                    el.readOnly = false;
                }
            });
        }

        /**
         * Lock auto-filled specs fields as read-only.
         */
        function lockPmSpecFields() {
            PM_SPEC_FIELD_NAMES.forEach(name => {
                const el = document.querySelector(`[name="${name}"]`);
                if (el && el.value) {
                    if (el.tagName === 'SELECT') {
                        el.disabled = true;
                    } else {
                        el.readOnly = true;
                    }
                }
            });
        }

        /**
         * Unlock specs fields before form submission so values are included.
         */
        function unlockPmSpecFields() {
            PM_SPEC_FIELD_NAMES.forEach(name => {
                const el = document.querySelector(`[name="${name}"]`);
                if (el) {
                    el.disabled = false;
                    el.readOnly = false;
                }
            });
        }

        /**
         * Given a selected asset_id, pre-fill device info fields based on the asset's
         * category and specifications JSON stored in inventory_assets.
         */
        function pmAutoFillFromAsset(assetId) {
            clearPmDeviceFields();
            if (!assetId || !PM_ASSETS_MAP[assetId]) return;

            const asset = PM_ASSETS_MAP[assetId];
            const cat   = (asset.category || '').toLowerCase();
            const specs = asset.specs || {};
            
            console.log('DEBUG: pmAutoFillFromAsset called with assetId:', assetId);
            console.log('DEBUG: asset category:', cat);
            console.log('DEBUG: asset specs:', specs);
            console.log('DEBUG: specs.ram:', specs.ram);
            console.log('DEBUG: specs.hd1:', specs.hd1);
            console.log('DEBUG: specs.desktop_ram:', specs.desktop_ram);
            console.log('DEBUG: specs.desktop_hd1:', specs.desktop_hd1);

            // Helper — set a form field's value by name
            const fill = (name, value) => {
                if (!value) return;
                const el = document.querySelector(`[name="${name}"]`);
                if (!el) return;
                console.log(`DEBUG: fill('${name}', '${value}')`);
                if (el.tagName === 'SELECT') {
                    const valStr = String(value).trim().toLowerCase();
                    const valClean = valStr.replace(/[^a-z0-9]/g, ''); // e.g. "16gb", "corei9"
                    const opts = Array.from(el.options);
                    console.log(`DEBUG: ${name} options:`, opts.map(o => o.value));
                    
                    // 1. Try exact match (case-insensitive)
                    let match = opts.find(o => o.value.toLowerCase() === valStr);
                    
                    // 2. Try normalized match (remove non-alphanumeric, like spaces/hyphens)
                    if (!match) {
                        match = opts.find(o => o.value.toLowerCase().replace(/[^a-z0-9]/g, '') === valClean);
                    }
                    
                    // 3. Smart fallbacks for specific specs
                    if (!match) {
                        if (name.includes('Cpu')) {
                            // Try to match CPU by brand and type
                            if (valStr.includes('i3') || valStr.includes('core i3')) match = opts.find(o => o.value.includes('i3'));
                            else if (valStr.includes('i5') || valStr.includes('core i5')) match = opts.find(o => o.value.includes('i5'));
                            else if (valStr.includes('i7') || valStr.includes('core i7')) match = opts.find(o => o.value.includes('i7'));
                            else if (valStr.includes('i9') || valStr.includes('core i9')) match = opts.find(o => o.value.includes('i9'));
                            else if (valStr.includes('ryzen 3') || valStr.includes('r3')) match = opts.find(o => o.value.includes('Ryzen 3'));
                            else if (valStr.includes('ryzen 5') || valStr.includes('r5')) match = opts.find(o => o.value.includes('Ryzen 5'));
                            else if (valStr.includes('ryzen 7') || valStr.includes('r7')) match = opts.find(o => o.value.includes('Ryzen 7'));
                            else if (valStr.includes('m1') || valStr.includes('m2') || valStr.includes('m3') || valStr.includes('apple') || valStr.includes('mac')) {
                                match = opts.find(o => o.value.includes('Apple'));
                            } else if (valStr.includes('ryzen')) {
                                // Generic ryzen match
                                match = opts.find(o => o.value.toLowerCase().includes('ryzen'));
                            } else if (valStr.includes('core') || valStr.includes('intel')) {
                                // Generic intel/core match
                                match = opts.find(o => o.value.toLowerCase().includes('core'));
                            }
                        } else if (name.includes('Ram')) {
                            // Extract FIRST number sequence only (e.g., "64GB DDR4" → "64", not "644")
                            const numMatch = valStr.match(/^(\d+)/);
                            const num = numMatch ? numMatch[1] : '';
                            console.log(`DEBUG: ${name} RAM matching - extracted num: '${num}' from '${valStr}'`);
                            if (num) {
                                // Try exact number match first: "8gb ddr4" → extracts "8" → matches "8 GB"
                                match = opts.find(o => {
                                    const optNum = o.value.replace(/[^0-9]/g, '');
                                    console.log(`  Comparing '${num}' with option '${o.value}' (extracted: '${optNum}')`);
                                    return optNum === num;
                                });
                                console.log(`DEBUG: ${name} after number extraction, match:`, match ? match.value : 'null');
                                // Fallback: try substring matching if exact doesn't work
                                if (!match) {
                                    match = opts.find(o => o.value.toLowerCase().includes(num + ' gb') || o.value.toLowerCase().includes(num + 'gb'));
                                    console.log(`DEBUG: ${name} after substring fallback, match:`, match ? match.value : 'null');
                                }
                            }
                        } else if (name.includes('Gpu')) {
                            if (valStr.includes('intel') || valStr.includes('integrated') || valStr.includes('uhd') || valStr.includes('iris') || valStr.includes('shared') || valStr.includes('m1') || valStr.includes('m2') || valStr.includes('m3')) {
                                match = opts.find(o => o.value === 'Integrated');
                            } else if (valStr.includes('rtx') || valStr.includes('gtx') || valStr.includes('nvidia') || valStr.includes('geforce') || valStr.includes('amd') || valStr.includes('dedicated') || valStr.includes('radeon')) {
                                // Extract LAST number sequence for VRAM (e.g., "RTX 4070 12GB" → "12", not "407012")
                                const gpuVramMatch = valStr.match(/(\d+)\s*gb\s*$/i);
                                if (gpuVramMatch && gpuVramMatch[1]) {
                                    const vram = parseInt(gpuVramMatch[1]);
                                    console.log(`DEBUG: ${name} GPU - extracted VRAM: '${vram}'`);
                                    if (vram >= 8) match = opts.find(o => o.value.includes('8GB+'));
                                    else if (vram >= 4) match = opts.find(o => o.value.includes('4GB'));
                                    else if (vram >= 2) match = opts.find(o => o.value.includes('2GB'));
                                } else {
                                    // No VRAM found, default to highest available
                                    match = opts.find(o => o.value.includes('8GB+')) || opts.find(o => o.value.includes('4GB'));
                                }
                            } else {
                                // Fallback: substring matching for unknown GPU types
                                match = opts.find(o => o.value.toLowerCase().includes(valStr) || valStr.includes(o.value.toLowerCase()));
                            }
                        } else if (name.includes('Os')) {
                            if (valStr.includes('11')) match = opts.find(o => o.value.includes('11'));
                            else if (valStr.includes('10')) match = opts.find(o => o.value.includes('10'));
                            else if (valStr.includes('mac')) match = opts.find(o => o.value.toLowerCase().includes('mac'));
                            else if (valStr.includes('linux')) match = opts.find(o => o.value.toLowerCase().includes('linux'));
                        } else if (name.includes('Hd') || name.includes('Storage')) {
                            const isSsd = valStr.includes('ssd') || valStr.includes('nvme');
                            const isHdd = valStr.includes('hdd');
                            const isNvme = valStr.includes('nvme') || valStr.includes('m.2');
                            const has2tb = valStr.includes('2tb') || valStr.includes('2 tb') || valStr.includes('2000gb') || valStr.includes('2048gb');
                            const has1 = valStr.includes('1tb') || valStr.includes('1 tb') || valStr.includes('1000gb') || valStr.includes('1024gb');
                            const has512 = valStr.includes('512') || valStr.includes('500');
                            const has256 = valStr.includes('256') || valStr.includes('240');
                            const has128 = valStr.includes('128');
                            
                            if (isNvme) {
                                // NVMe/M.2 detected — try exact match first, then fallback to SATA SSD
                                if (has2tb) match = opts.find(o => o.value === '2TB M.2 NVMe SSD') || opts.find(o => o.value === 'SSD - 2TB');
                                else if (has1) match = opts.find(o => o.value === '1TB M.2 NVMe SSD') || opts.find(o => o.value === 'SSD - 1TB');
                                else if (has512) match = opts.find(o => o.value === '512GB M.2 NVMe SSD') || opts.find(o => o.value === 'SSD - 512GB');
                                else if (has256) match = opts.find(o => o.value === '256GB M.2 NVMe SSD') || opts.find(o => o.value === 'SSD - 256GB');
                                else if (has128) match = opts.find(o => o.value === '128GB M.2 NVMe SSD');
                            } else if (isSsd) {
                                // Generic SSD (SATA)
                                if (has2tb) match = opts.find(o => o.value === 'SSD - 2TB');
                                else if (has1) match = opts.find(o => o.value === 'SSD - 1TB');
                                else if (has512) match = opts.find(o => o.value === 'SSD - 512GB');
                                else if (has256) match = opts.find(o => o.value === 'SSD - 256GB');
                            } else if (isHdd) {
                                if (has1) match = opts.find(o => o.value === 'HDD - 1TB');
                                else if (valStr.includes('500')) match = opts.find(o => o.value === 'HDD - 500GB');
                            } else {
                                match = opts.find(o => o.value.toLowerCase().includes(valStr) || valStr.includes(o.value.toLowerCase()));
                            }
                        } else if (name.includes('Office')) {
                            if (valStr.includes('365')) match = opts.find(o => o.value.includes('365'));
                            else if (valStr.includes('2021')) match = opts.find(o => o.value.includes('2021'));
                            else if (valStr.includes('2019')) match = opts.find(o => o.value.includes('2019'));
                            else if (valStr.includes('2016')) match = opts.find(o => o.value.includes('2016'));
                            else if (valStr.includes('office') || valStr.includes('microsoft')) {
                                // If contains Office/Microsoft but no version, try to find any Office option
                                match = opts.find(o => o.value && o.value.toLowerCase().includes('office'));
                            }
                        } else if (name.includes('Brand')) {
                            // Substring brand match (e.g. "Samsung Monitor" matches Samsung, "Epson Printer" matches Epson)
                            match = opts.find(o => valStr.includes(o.value.toLowerCase()) || o.value.toLowerCase().includes(valStr));
                        }
                    }
                    
                    if (match) {
                        el.value = match.value;
                        console.log(`DEBUG: ${name} matched to: '${match.value}'`);
                    } else {
                        // Last resort: try substring matching for any remaining values
                        if (!match) {
                            match = opts.find(o => o.value && (o.value.toLowerCase().includes(valStr.substring(0, 3)) || (valStr.length > 3 && o.value.toLowerCase().includes(valStr))));
                        }
                        
                        if (match) {
                            el.value = match.value;
                            console.log(`DEBUG: ${name} matched via substring to: '${match.value}'`);
                        } else {
                            const otherOpt = opts.find(o => o.value === 'Other' || o.value === 'Others');
                            if (otherOpt) {
                                el.value = otherOpt.value;
                                console.log(`DEBUG: ${name} NO MATCH - set to Other`);
                            } else {
                                el.value = '';
                                console.log(`DEBUG: ${name} NO MATCH - left empty`);
                            }
                        }
                    }
                } else {
                    el.value = value;
                }
            };

            // 2. Fill the selected primary asset's fields
            if (cat.includes('desktop') || cat.includes('computer')) {
                fill('desktopBrand',  specs.desktop_brand   || specs.brand || asset.item_name);
                fill('desktopModel',  specs.desktop_model   || specs.model || asset.item_name);
                fill('desktopPno',    asset.serial_number);
                fill('dtCpu',         specs.desktop_cpu     || specs.cpu);
                fill('dtRam',         specs.desktop_ram     || specs.ram);
                fill('dtGpu',         specs.desktop_gpu     || specs.gpu);
                fill('dtOs',          specs.desktop_os      || specs.os);
                fill('dtHd1',         specs.desktop_hd1     || specs.storage || specs.hd1);
                fill('dtHd2',         specs.desktop_hd2     || specs.hd2);
                fill('dtOffice',      specs.desktop_office  || specs.office);
                fill('dtYear',        specs.desktop_year_purchased || specs.year_purchased);
            } else if (cat.includes('laptop')) {
                fill('laptopBrand',   specs.laptop_brand    || specs.brand || asset.item_name);
                fill('laptopModel',   specs.laptop_model    || specs.model || asset.item_name);
                fill('laptopPno',     asset.serial_number);
                fill('ltCpu',         specs.laptop_cpu      || specs.cpu);
                fill('ltRam',         specs.laptop_ram      || specs.ram);
                fill('ltGpu',         specs.laptop_gpu      || specs.gpu);
                fill('ltOs',          specs.laptop_os       || specs.os);
                fill('ltHd1',         specs.laptop_hd1      || specs.storage || specs.hd1);
                fill('ltHd2',         specs.laptop_hd2      || specs.hd2);
                fill('ltOffice',      specs.laptop_office   || specs.office);
                fill('ltYear',        specs.laptop_year_purchased || specs.year_purchased);
            } else if (cat.includes('monitor')) {
                fill('monitor1Brand', specs.monitor_brand   || specs.brand || asset.item_name);
                fill('monitor1Model', specs.monitor_model   || specs.model || asset.item_name);
                fill('monitor1Pno',   asset.serial_number);
            } else if (cat.includes('printer')) {
                fill('printer1Brand', specs.printer_brand   || specs.brand || asset.item_name);
                fill('printer1Model', specs.printer_model   || specs.model || asset.item_name);
                fill('printer1Pno',   asset.serial_number);
            } else if (cat.includes('ups')) {
                fill('upsBrand',      specs.ups_brand       || specs.brand || asset.item_name);
                fill('upsModel',      specs.ups_model       || specs.model || asset.item_name);
                fill('upsPno',        asset.serial_number);
            } else if (cat.includes('scanner')) {
                fill('scannerBrand',  specs.scanner_brand   || specs.brand || asset.item_name);
                fill('scannerModel',  specs.scanner_model   || specs.model || asset.item_name);
                fill('scannerPno',    asset.serial_number);
            }

            // 3. Find and auto-fill OTHER matching category assets assigned to the same user (sub-components)
            for (const otherId in PM_ASSETS_MAP) {
                if (otherId === String(assetId)) continue;
                const otherAsset = PM_ASSETS_MAP[otherId];
                const otherCat = (otherAsset.category || '').toLowerCase();
                const otherSpecs = otherAsset.specs || {};

                if (otherCat.includes('monitor')) {
                    fill('monitor1Brand', otherSpecs.monitor_brand || otherSpecs.brand || otherAsset.item_name);
                    fill('monitor1Model', otherSpecs.monitor_model || otherSpecs.model || otherAsset.item_name);
                    fill('monitor1Pno',   otherAsset.serial_number);
                } else if (otherCat.includes('printer')) {
                    fill('printer1Brand', otherSpecs.printer_brand || otherSpecs.brand || otherAsset.item_name);
                    fill('printer1Model', otherSpecs.printer_model || otherSpecs.model || otherAsset.item_name);
                    fill('printer1Pno',   otherAsset.serial_number);
                } else if (otherCat.includes('ups')) {
                    fill('upsBrand',      otherSpecs.ups_brand       || otherSpecs.brand || otherAsset.item_name);
                    fill('upsModel',      otherSpecs.ups_model       || otherSpecs.model || otherAsset.item_name);
                    fill('upsPno',        otherAsset.serial_number);
                } else if (otherCat.includes('scanner')) {
                    fill('scannerBrand',  otherSpecs.scanner_brand   || otherSpecs.brand || otherAsset.item_name);
                    fill('scannerModel',  otherSpecs.scanner_model   || otherSpecs.model || otherAsset.item_name);
                    fill('scannerPno',    otherAsset.serial_number);
                } else if (otherCat.includes('webcam')) {
                    fill('webcamBrand',   otherSpecs.webcam_brand    || otherSpecs.brand || otherAsset.item_name);
                    fill('webcamModel',   otherSpecs.webcam_model    || otherSpecs.model || otherAsset.item_name);
                    fill('webcamPno',     otherAsset.serial_number);
                } else if (otherCat.includes('speaker') || otherCat.includes('audio')) {
                    fill('speakersBrand', otherSpecs.speakers_brand  || otherSpecs.brand || otherAsset.item_name);
                    fill('speakersModel', otherSpecs.speakers_model  || otherSpecs.model || otherAsset.item_name);
                    fill('speakersPno',   otherAsset.serial_number);
                } else if (otherCat.includes('earphone') || otherCat.includes('headset')) {
                    fill('earphoneBrand', otherSpecs.earphone_brand  || otherSpecs.brand || otherAsset.item_name);
                    fill('earphoneModel', otherSpecs.earphone_model  || otherSpecs.model || otherAsset.item_name);
                }
            }

            // Lock auto-filled specs fields as read-only
            lockPmSpecFields();
        }

        function initSignature(canvasId, hiddenInputId) { const canvas = document.getElementById(canvasId); const hiddenInput = document.getElementById(hiddenInputId); if (!canvas || !hiddenInput) return; if (canvas.closest('.disabled-section')) return; const ctx = canvas.getContext('2d'); let drawing = false; let lastX = 0, lastY = 0; ctx.strokeStyle = '#000'; ctx.lineWidth = 1.5; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; if (hiddenInput.value && hiddenInput.value.startsWith('data:image')) { const img = new Image(); img.onload = () => ctx.drawImage(img, 0, 0); img.src = hiddenInput.value; } const getPos = (e) => { const rect = canvas.getBoundingClientRect(); const clientX = e.touches ? e.touches[0].clientX : e.clientX; const clientY = e.touches ? e.touches[0].clientY : e.clientY; return { x: clientX - rect.left, y: clientY - rect.top }; }; const startDraw = (e) => { e.preventDefault(); drawing = true; const pos = getPos(e); lastX = pos.x; lastY = pos.y; }; const doDraw = (e) => { if (!drawing) return; e.preventDefault(); const pos = getPos(e); ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(pos.x, pos.y); ctx.stroke(); lastX = pos.x; lastY = pos.y; hiddenInput.value = canvas.toDataURL(); }; const stopDraw = () => { drawing = false; }; canvas.addEventListener('mousedown', startDraw); canvas.addEventListener('mousemove', doDraw); window.addEventListener('mouseup', stopDraw); canvas.addEventListener('touchstart', startDraw, { passive: false }); canvas.addEventListener('touchmove', doDraw, { passive: false }); canvas.addEventListener('touchend', stopDraw); canvas.addEventListener('touchcancel', stopDraw); canvas.style.touchAction = 'none'; } document.addEventListener('DOMContentLoaded', () => {
            const pmSelect = document.getElementById('pm_linked_asset_id');
            if (pmSelect) {
                // Auto-fill on page load if an asset is already pre-selected
                if (pmSelect.value) pmAutoFillFromAsset(pmSelect.value);

                pmSelect.addEventListener('change', function () {
                    pmAutoFillFromAsset(this.value);
                });
            }

        });
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-canvas]');
            if (btn) {
                clearSignature(btn.dataset.canvas, btn.dataset.input);
            }
        });
        var resignBtn = document.getElementById('resignBtn');
        if (resignBtn) {
            resignBtn.addEventListener('click', function() {
                document.getElementById('endUserSignature').value = '';
                this.parentElement.innerHTML = '<canvas id="endUserSignatureCanvas" class="signature-canvas" width="350" height="64"></canvas><input type="hidden" id="endUserSignature" name="endUserSignature" value=""><button type="button" class="btn-clear-sig-minimal" data-canvas="endUserSignatureCanvas" data-input="endUserSignature">Clear</button>';
                initSignature('endUserSignatureCanvas', 'endUserSignature');
            });
        }
    </script>