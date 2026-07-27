{{-- UPLOAD ATTACHMENT MODAL --}}
{{-- Partial extracted from inventory/detail.blade.php lines 701-730.
     NOTE: Caller (detail.blade.php) wraps this with @if(Auth::user()->canProcessSupply()).
     All upload interaction handled by detail.js. --}}
    <div id="uploadModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title"><i class="fa-solid fa-paperclip"></i> Upload Document</div>
            <form id="uploadForm">
                @csrf
                <div class="modal-row">
                    <label class="modal-label">Label / Document Type (optional)</label>
                    <input type="text" id="attachLabel" placeholder="e.g. Purchase Order, Inspection Report, Manual..."
                           class="modal-input">
                </div>
                <div class="modal-row-last">
                    <label class="modal-label">File</label>
                    <div class="upload-zone" id="uploadZone">
                        <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                        <div class="upload-text">Click to select file</div>
                        <div class="upload-hint">PDF, Word, Excel, Images, ZIP — max 10MB</div>
                        <div id="selectedFileName" class="upload-filename"></div>
                    </div>
                    <input type="file" id="attachFile" name="file" class="d-none">
                </div>
                <div class="modal-actions">
                    <button type="button" class="close-upload-btn btn-modal-cancel">Cancel</button>
                    <button type="submit" id="uploadBtn" class="btn-modal-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

