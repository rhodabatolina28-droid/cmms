<style nonce="{{ $cspNonce }}">
    /* Essential overrides for Laravel integration */
    .hidden { display: none; }
    .signature-preview { width: 100%; max-width: 220px; height: auto; border-bottom: 1px solid #ccc; }
    .btn-secondary {
        background-color: #64748b;
        color: white;
        padding: 12px 24px;
        border-radius: 6px;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .btn-secondary:hover { background-color: #475569; }
    .ict-assign-panel { background: #eff6ff; border: 2px solid #93c5fd; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
    .ict-assign-inner { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 16px; justify-content: space-between; }
    .ict-assign-left { flex: 1; min-width: 260px; }
    .ict-assign-label { font-size: 11px; font-weight: 800; color: #1e40af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
    .ict-assign-text { margin: 0 0 12px; font-size: 13px; color: #334155; }
    .ict-assign-select { max-width: 400px; width: 100%; }
    .ict-assign-notice { margin: 8px 0 0; font-size: 12px; color: #b45309; }
    .ict-assign-right { text-align: right; }
    .ict-assign-current-label { font-size: 12px; color: #64748b; margin-bottom: 8px; }
    .ict-assign-current-name { font-weight: 800; color: #0038A8; margin-bottom: 12px; }
    .ict-assign-unassigned { display: inline-block; background: #fef3c7; color: #92400e; padding: 6px 12px; border-radius: 99px; font-size: 11px; font-weight: 800; margin-bottom: 12px; }
    .ict-assign-btn { background: #0038A8; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; text-transform: uppercase; font-size: 13px; }
    .ict-div-review-panel { background: #fdf4ff; border: 2px solid #e879f9; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
    .ict-div-review-label { font-size: 11px; font-weight: 800; color: #86198f; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
    .ict-div-review-text { margin: 0 0 12px; font-size: 13px; color: #4a044e; }
    .ict-div-review-body { display: flex; flex-direction: column; gap: 12px; }
    .ict-div-review-textarea { width: 100%; }
    .ict-div-review-actions { display: flex; gap: 12px; justify-content: flex-end; }
    .ict-div-review-btn-reject { background-color: #ef4444; border: none; color: white; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
    .ict-div-review-btn-approve { background-color: #10b981; border: none; color: white; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
    .ict-review-status-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; margin-bottom: 24px; }
    .ict-review-status-label { font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; margin-bottom: 4px; }
    .ict-review-status-row { display: flex; align-items: center; gap: 8px; }
    .ict-review-approved { color: #10b981; font-weight: 800; }
    .ict-review-rejected { color: #ef4444; font-weight: 800; }
    .ict-review-date { font-size: 13px; color: #64748b; }
    .ict-review-notes { margin-top: 8px; font-size: 13px; color: #334155; padding: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; }
    .ict-alert-rejected { margin: 0 0 20px; padding: 16px 18px; background: #fefce8; border: 2px solid #fbbf24; border-radius: 8px; color: #92400e; }
    .ict-alert-rejected-title { display: block; margin-bottom: 6px; font-size: 14px; }
    .ict-alert-rejected-text { margin: 0; font-size: 13px; line-height: 1.5; }
    .ict-alert-no-asset { margin: 0 0 20px; padding: 16px 18px; background: #fef2f2; border: 2px solid #fca5a5; border-radius: 8px; color: #991b1b; }
    .ict-alert-no-asset-title { display: block; margin-bottom: 6px; font-size: 14px; }
    .ict-alert-no-asset-text { margin: 0; font-size: 13px; line-height: 1.5; }
    .ict-field-mt { margin-top: 15px; margin-bottom: 15px; }
    .ict-disabled-bg { background: #f1f5f9; color: #475569; font-weight: 600; }
    .ict-asset-row { display: flex; gap: 8px; align-items: flex-start; }
    .ict-asset-select-wrap { flex: 1; }
    .ict-asset-select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; outline: none; background: white; box-sizing: border-box; }
    .ict-asset-hint { margin: 6px 0 0; font-size: 12px; color: #475569; }
    .ict-scan-btn { flex-shrink: 0; padding: 10px 16px; background: #0038A8; color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; margin-top: 0; }
    .ict-cost-input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; outline: none; }
    .ict-section-header-note { font-size: 12px; color: #475569; margin: 8px 0 0; }
    .ict-acceptance-block { background: #fffbeb; border-color: #fcd34d; margin-bottom: 16px; }
    .ict-acceptance-block-text { margin: 0; color: #92400e; font-weight: 600; }
    .ict-btn-group { flex-wrap: wrap; gap: 10px; justify-content: center; padding: 20px 0 8px; border-top: 1px solid #e5e7eb; margin-top: 24px; }
    .ict-btn-resubmit { background-color: #f59e0b; color: white; }
    .ict-btn-pdf { background-color: #059669; color: white; border-color: #059669; }
    .ict-btn-disposal { background-color: #4f46e5; color: white; border-color: #4f46e5; }
    .ict-scanner-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 99999; flex-direction: column; align-items: center; justify-content: center; }
    .ict-scanner-card { background: white; border-radius: 12px; padding: 24px; max-width: 440px; width: 90%; text-align: center; }
    .ict-scanner-title { margin: 0 0 8px; color: #1e293b; font-size: 18px; }
    .ict-scanner-text { margin: 0 0 16px; color: #64748b; font-size: 13px; }
    .ict-scanner-reader { width: 100%; max-width: 320px; margin: 0 auto 16px; }
    .ict-scanner-status { font-size: 13px; color: #64748b; margin-bottom: 12px; }
    .ict-scanner-cancel { padding: 10px 24px; background: #dc2626; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
    .ict-sp-banner { display: none; margin-top: 10px; padding: 10px 12px; background: #fff7ed; border-left: 4px solid #ea580c; font-size: 12px; color: #9a3412; line-height: 1.5; }
    .ict-sp-banner-visible { display: block; }
    .ict-notice-compact { background: #fffbeb; border-color: #fcd34d; margin-bottom: 16px; }
    .ict-mt-8 { margin-top: 8px; }

    /* Deskstop UX Upgrades & Hidden Scanner */
    @media (min-width: 768px) {
        .ict-scan-btn { display: none !important; }
    }
    
    .btn-submit, .btn-cancel, .btn-secondary, .ict-btn-resubmit, .ict-btn-pdf, .ict-btn-disposal, .btn-primary {
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-submit:hover, .btn-cancel:hover, .btn-secondary:hover, .ict-btn-resubmit:hover, .ict-btn-pdf:hover, .ict-btn-disposal:hover, .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }
    .swal2-deny { display: none !important; }
    .swal2-actions { flex-direction: row !important; gap: 4px !important; }
    .swal2-popup .swal2-actions button.swal2-confirm { width: auto !important; min-width: 80px !important; }
    .swal2-popup .swal2-actions button[style*="display: none"] { display: none !important; }
</style>