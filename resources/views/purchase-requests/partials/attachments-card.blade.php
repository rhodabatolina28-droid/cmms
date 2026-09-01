{{-- Phase C3 - receipts & documents card. --}}
@php
    $canUploadReceipt = ! $purchaseRequest->isDelivered()
        && ($purchaseRequest->isOwnedBy(Auth::user()) || Auth::user()->canProcessSupply());
@endphp
<div id="receipts" class="rx-panel no-print" role="region" aria-label="Proof of purchase">
    <div class="rx-step-head">
        <div>
            <h2 style="font-size:15.5px; font-weight:800; color:#111827; margin:0;">Proof of purchase</h2>
            <span class="st-sub">Official receipt or invoice &mdash; <b>required for every purchase, any amount</b> (PDF / JPG / PNG)</span>
        </div>
    </div>

    @forelse($purchaseRequest->attachments as $att)
        <div class="pr-attach-row" style="display:flex; align-items:center; gap:10px; padding:9px 12px; border:1px solid #e5e7eb; border-radius:10px; margin-bottom:8px; background:#fff; font-size:12.5px; flex-wrap:wrap;">
            <i class="fa-regular fa-file-lines" style="color:#6b7280; font-size:15px;"></i>
            <strong style="color:#111827;">{{ $att->filename }}</strong>
            @if($att->label)<span style="background:#f1f5f9; border-radius:999px; padding:2px 10px; font-size:11px; color:#475569;">{{ $att->label }}</span>@endif
            <span class="pr-attach-meta" style="color:#94a3b8; font-size:11.5px;">{{ $att->uploader?->full_name ?? '&mdash;' }} &middot; {{ optional($att->created_at)->format('M d, Y g:i A') }}</span>
            <a href="{{ route('purchase_requests.attachments.download', $att) }}" class="rxb rxb-white rxb-sm" aria-label="Download receipt {{ $att->filename }}"><i class="fa-solid fa-download"></i>Download</a>
            @if($canUploadReceipt)
                <button type="button" class="pr-del-att rxb rxb-white rxb-sm" data-url="{{ route('purchase_requests.attachments.destroy', $att) }}" aria-label="Delete receipt {{ $att->filename }}" style="color:#b91c1c;"><i class="fa-solid fa-trash"></i></button>
            @endif
        </div>
    @empty
        <p style="font-size:12.5px; color:#6b7280; margin:0 0 12px;">
            Nothing uploaded yet.@if(! $purchaseRequest->isDelivered()) Delivery cannot be confirmed without at least one receipt.@endif
        </p>
    @endforelse

    @if($canUploadReceipt)
        <form id="rz-form" method="POST" action="{{ route('purchase_requests.attachments.store', $purchaseRequest->id) }}" enctype="multipart/form-data" aria-label="Upload receipt form">
            @csrf
            <input type="file" id="receipt-file" name="file" accept=".pdf,.jpg,.jpeg,.png" required aria-label="Choose receipt file" style="display:none;">
            <div class="rx-dropzone" role="button" tabindex="0" aria-label="Choose receipt file to upload">
                <i class="dz-ic fa-solid fa-cloud-arrow-up"></i>
                <span class="dz-t" data-dz-text>Drop your receipt here or click to browse</span>
                <span class="dz-d">PDF / JPG / PNG &middot; up to 10MB</span>
            </div>
            <div id="rz-details" style="display:none; margin-top:10px; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="text" name="label" placeholder="Label (optional, e.g. Official receipt)" maxlength="100" style="flex:1; min-width:180px; padding:9px 12px; border:1.5px solid #d1d5db; border-radius:9px; font-size:12.5px;" aria-label="Receipt label">
                <button type="submit" class="rxb rxb-blue"><i class="fa-solid fa-upload"></i>Upload receipt</button>
                <button type="button" class="rxb rxb-white" onclick="rzReset()"><i class="fa-solid fa-xmark"></i>Cancel</button>
            </div>
        </form>
    @endif
</div>

<script>
// ===== Receipt upload via AJAX =====
(function () {
    var form = document.getElementById('rz-form');
    if (!form) return;
    var fileInput = document.getElementById('receipt-file');
    var details = document.getElementById('rz-details');
    var dzText = form.querySelector('[data-dz-text]');
    var dz = form.querySelector('.rx-dropzone');
    var submitBtn = form.querySelector('button[type=submit]');

    function openPicker() { fileInput.click(); }

    // Click OR keyboard on the dropzone opens the picker exactly once.
    dz.addEventListener('click', openPicker);
    dz.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPicker(); }
    });

    window.rzReset = function () {
        form.reset();
        details.style.display = 'none';
        dzText.textContent = 'Drop your receipt here or click to browse';
    };

    fileInput.addEventListener('change', function () {
        if (this.files.length) {
            details.style.display = 'flex';
            dzText.textContent = this.files[0].name;
        } else {
            details.style.display = 'none';
            dzText.textContent = 'Drop your receipt here or click to browse';
        }
    });

    // Drag & drop support
    ['dragover', 'dragenter'].forEach(function (ev) {
        dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('dragover'); });
    });
    dz.addEventListener('drop', function (e) {
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!fileInput.files.length) return;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>Uploading...';

        var fd = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: fd
        })
        .then(function (res) { return res.json().catch(function () { window.location.reload(); }); })
        .then(function (data) {
            if (data.success) {
                if (window.Swal) {
                    window.Swal.fire({ icon: 'success', title: 'Receipt uploaded', timer: 1600, showConfirmButton: false });
                }
                setTimeout(function () { window.location.reload(); }, 800);
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-upload"></i>Upload receipt';
                if (window.Swal) { window.Swal.fire('Upload failed', data.message || 'Please try again.', 'error'); }
            }
        })
        .catch(function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-upload"></i>Upload receipt';
        });
    });
})();

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.pr-del-att');
    if (!btn || !window.Swal) return;
    e.preventDefault();
    window.Swal.fire({
        icon: 'warning',
        title: 'Delete this receipt?',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626'
    }).then(function (r) {
        if (!r.isConfirmed) return;
        fetch(btn.dataset.url, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        }).then(function (res) { return res.json(); }).then(function (data) {
            if (data.success) { window.location.reload(); }
            else if (window.Swal) { window.Swal.fire('Cannot delete', data.message || '', 'error'); }
        });
    });
});
</script>
