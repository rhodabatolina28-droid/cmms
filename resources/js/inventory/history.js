async function viewAssetHistory(assetId) {
    console.log("Opening Lifecycle History for asset ID: ", assetId);
    const modal = document.getElementById("assetHistoryModal");
    const content = document.getElementById("historyContent");
    
    if (!modal || !content) {
        console.error("Lifecycle Modal elements not found in the DOM!");
        Swal.fire('Error', 'Modal elements not found.', 'error');
        return;
    }

    modal.style.display = "flex";
    content.innerHTML = '<div style="text-align: center; color: #999; padding: 40px;"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading...</div>';

    const historyPrefix = window.CMMS_INVENTORY_DETAIL_PREFIX || '/inventory';
    try {
        const response = await fetch(`${historyPrefix}/${assetId}/history`, {
            credentials: "include",
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();

        if (result.success && result.history.length > 0) {
            content.innerHTML = result.history.map(h => {
                const date = new Date(h.created_at).toLocaleString();
                let detailHtml = `<p style="margin: 5px 0; color: #4b5563; font-size: 13px;">${h.remarks || ''}</p>`;
                
                if (h.previous_user_id != h.new_user_id) {
                    detailHtml += `<p style="margin: 3px 0; font-size: 12px; color: #6b7280;">User: ${h.previous_user ? h.previous_user.full_name : 'Unassigned'} &rarr; <strong>${h.new_user ? h.new_user.full_name : 'Unassigned'}</strong></p>`;
                }
                if (h.previous_status !== h.new_status) {
                    detailHtml += `<p style="margin: 3px 0; font-size: 12px; color: #6b7280;">Status: ${h.previous_status || ''} &rarr; <strong>${h.new_status || ''}</strong></p>`;
                }

                const receiptPrefix = window.CMMS_RECEIPT_PREFIX || '/inventory';
                const receiptBtn = ''; // PTR process is physical — no system-generated receipt

                return `
                    <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #f3f4f6;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <div style="display: flex; align-items: center;">
                                <strong style="color: #111827;">${h.action}</strong>
                                ${receiptBtn}
                            </div>
                            <span style="font-size: 12px; color: #9ca3af;">${date}</span>
                        </div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Performed by: ${h.performed_by_user ? h.performed_by_user.full_name : 'System'}</div>
                        ${detailHtml}
                    </div>
                `;
            }).join("");
        } else {
            content.innerHTML = '<div style="text-align: center; color: #999; padding: 40px;">No history records found.</div>';
        }
    } catch (error) {
        console.error("Error loading history:", error);
        content.innerHTML = '<div style="text-align: center; color: red; padding: 40px;">Error loading history records.</div>';
    }
}

function closeHistoryModal() {
    document.getElementById("assetHistoryModal").style.display = "none";
}

export { viewAssetHistory, closeHistoryModal };