<!-- TRANSFER / REASSIGN MODAL -->
{{-- Partial extracted from inventory/index.blade.php lines 1275-1323.
     No variables needed — all data is populated via JS (inventory.js). --}}
<div class="modal-overlay" id="transferModal">
    <div class="modal-card transfer-card">
        <div class="modal-header">
            <h4 class="modal-h4">
                <i class="fa-solid fa-right-left color-green mr-8"></i> Transfer / Reassign Asset
            </h4>
            <button class="close-transfer-btn close-btn" aria-label="Close"><i class="fa-solid fa-times"></i></button>
        </div>
        <form id="transferForm" class="modal-form">
            <input type="hidden" id="transferAssetId">
            <div class="modal-body">

                <div class="transfer-info-box">
                    <div class="transfer-info-title">Asset Being Transferred</div>
                    <div id="transferAssetName" class="transfer-asset-name"></div>
                    <div class="transfer-custodian">
                        Current Custodian: <strong id="transferCurrentCustodian"></strong>
                    </div>
                </div>

                <div class="mb-16">
                    <label class="form-label-gov">New Custodian</label>
                    <p class="info-text-sm inline-info">Select the person who will receive and be accountable for this asset.</p>
                    <select id="transferAssignedUser" class="form-input-gov">
                        <option value="">-- Not Assigned (Return to Stock) --</option>
                    </select>
                </div>

                <div class="mb-16">
                    <label class="form-label-gov">Transfer Remarks (optional)</label>
                    <textarea id="transferRemarks" class="form-input-gov textarea-sm"
                              placeholder="Reason for transfer, condition of asset, etc..."></textarea>
                </div>

                <div class="warning-box">
                    <i class="fa-solid fa-circle-info"></i>
                    Custodian update will be recorded in the asset's Lifecycle History.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action-modern close-transfer-btn btn-cancel-modal">Cancel</button>
                <button type="submit" id="transferSaveBtn" class="btn-success">
                    <i class="fa-solid fa-right-left"></i> Confirm Transfer
                </button>
            </div>
        </form>
    </div>
</div>
