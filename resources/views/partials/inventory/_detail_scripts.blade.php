<script nonce="{{ $cspNonce }}">
const ASSET_ID = {{ $asset->asset_id }};
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const ATTACH_UPLOAD_URL = '{{ route("inventory.attachments.upload", $asset->asset_id) }}';
const ATTACH_DELETE_PATTERN = '{{ route("inventory.attachments.delete", "_ID_") }}';
const CONFIRM_SCRAPPED_URL = '{{ route("inventory.confirm-scrapped", $asset->asset_id) }}';
const QR_STICKER_PATTERN = '{{ route("inventory.qr-sticker", "_ID_") }}';

document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const file = document.getElementById('attachFile').files[0];
    if (!file) {
        Swal.fire({ icon: 'warning', title: 'No File Selected', text: 'Please select a file to upload.', confirmButtonColor: '#0038A8' });
        return;
    }

    const btn = document.getElementById('uploadBtn');
    btn.disabled = true; btn.textContent = 'Uploading...';

    const fd = new FormData();
    fd.append('file', document.getElementById('attachFile').files[0]);
    fd.append('label', document.getElementById('attachLabel').value);
    fd.append('_token', CSRF_TOKEN);

    try {
        const res = await fetch(ATTACH_UPLOAD_URL, { method: 'POST', credentials: 'include', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('uploadModal').style.display = 'none';
            window.location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Upload Failed', text: data.message || 'Upload failed. Please try again.', confirmButtonColor: '#0038A8' });
        }
    } catch(err) {
        Swal.fire({ icon: 'error', title: 'Upload Error', text: 'Could not connect to server. Please try again.', confirmButtonColor: '#0038A8' });
    } finally {
        btn.disabled = false; btn.textContent = 'Upload';
    }
});

async function deleteAttachment(id, btn) {
    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Delete Attachment?',
        text: 'This will permanently remove this document.',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Delete',
    });
    if (!confirm.isConfirmed) return;
    btn.disabled = true;
    try {
        const res = await fetch(ATTACH_DELETE_PATTERN.replace('_ID_', id), {
            method: 'DELETE',
            credentials: 'include',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) window.location.reload();
        else Swal.fire({ icon: 'error', title: 'Delete Failed', text: data.message || 'Could not delete attachment.', confirmButtonColor: '#0038A8' });
    } catch(err) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Could not connect to server.', confirmButtonColor: '#0038A8' });
    } finally {
        btn.disabled = false;
    }
}
document.getElementById('attachFile').addEventListener('change', function() {
    document.getElementById('selectedFileName').textContent = this.files[0]?.name || '';
});
// Modal close functions
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
    if (e.target.classList.contains('scrap-overlay')) {
        e.target.style.display = 'none';
    }
});

// Close modal with Escape key
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const uploadModal = document.getElementById('uploadModal');
        const scrappedModal = document.getElementById('confirmScrappedModal');
        if (uploadModal && uploadModal.style.display === 'flex') {
            uploadModal.style.display = 'none';
        }
        if (scrappedModal && scrappedModal.style.display === 'flex') {
            scrappedModal.style.display = 'none';
        }
    }
});

// Modal event listeners
const uploadModalBtn = document.getElementById('showUploadModalBtn');
if (uploadModalBtn) {
    uploadModalBtn.addEventListener('click', function() {
        openModal('uploadModal');
    });
}

const scrappedModalBtn = document.getElementById('showScrappedModalBtn');
if (scrappedModalBtn) {
    scrappedModalBtn.addEventListener('click', function() {
        openModal('confirmScrappedModal');
    });
}

function printQR(assetId) {
    const url = QR_STICKER_PATTERN.replace('_ID_', assetId);
    window.open(url, '_blank');
}

const printStickerBtn = document.getElementById('printStickerBtn');
if (printStickerBtn) {
    printStickerBtn.addEventListener('click', function() {
        printQR(this.dataset.id);
    });
}

// Delete attachment buttons
document.querySelectorAll('[data-action="delete-attachment"]').forEach(function(el) {
    el.addEventListener('click', function() {
        deleteAttachment(this.dataset.attachId, this);
    });
});

// Upload zone click
const uploadZone = document.getElementById('uploadZone');
if (uploadZone) {
    uploadZone.addEventListener('click', function() {
        document.getElementById('attachFile').click();
    });
}

// Close buttons for upload modal
document.querySelectorAll('.close-upload-btn').forEach(function(el) {
    el.addEventListener('click', function() {
        closeModal('uploadModal');
    });
});

// Close buttons for scrapped modal
document.querySelectorAll('.close-scrapped-btn').forEach(function(el) {
    el.addEventListener('click', function() {
        closeModal('confirmScrappedModal');
    });
});

// Confirm asset scrapped function
async function confirmAssetScrapped() {
    const remarks = document.getElementById('scrappedRemarks').value;
    const btn = document.getElementById('confirmScrappedBtn');
    
    if (!btn) return;
    
    btn.disabled = true;
    btn.textContent = 'Processing...';
    
    try {
        const res = await fetch(CONFIRM_SCRAPPED_URL, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ remarks: remarks })
        });
        
        const data = await res.json();
        if (data.success) {
            closeModal('confirmScrappedModal');
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Asset has been marked as scrapped.',
                confirmButtonColor: '#0038A8'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to confirm disposal.',
                confirmButtonColor: '#0038A8'
            });
        }
    } catch(err) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Could not connect to server.',
            confirmButtonColor: '#0038A8'
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Confirm \u2014 Mark as Scrapped';
    }
}

// Confirm scrapped button
const confirmScrappedBtn = document.getElementById('confirmScrappedBtn');
if (confirmScrappedBtn) {
    confirmScrappedBtn.addEventListener('click', confirmAssetScrapped);
}
</script>