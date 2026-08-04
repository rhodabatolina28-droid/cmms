 // Inventory Management Script for Laravel
import { getDivisionAbbr, INVENTORY_BRANCH_MAP } from './inventory/config.js';
import { updateModalBranchDropdown, fetchFilteredUsers } from './inventory/modal-helpers.js';
import { viewAssetHistory, closeHistoryModal } from './inventory/history.js';

let allAssets = [];
let assetLookup = {};
let allUsers = [];
let currentInventoryImportToken = null;
let currentPage = 1;
let lastPage = 1;
const perPage = 50;
let filterChangeTimer = null;

document.addEventListener("DOMContentLoaded", function () {
    // Load inventory data immediately on page load
    loadInventory();
    
    // Auto-update status when assigned user changes
    const assignedUserSelect = document.getElementById("assetAssignedUser");
    if (assignedUserSelect) {
        assignedUserSelect.addEventListener("change", function() {
            const statusSelect = document.getElementById("assetStatus");
            if (this.value !== "") {
                if (statusSelect.value === "Spare") statusSelect.value = "Active";
            } else {
                if (statusSelect.value === "Active") statusSelect.value = "Spare";
            }
        });
    }

    // Category change listener for dynamic specs
    const categorySelect = document.getElementById("assetCategory");
    if (categorySelect) {
        categorySelect.addEventListener("change", toggleSpecsForm);
    }

    // Search and filter listeners
    const searchInput = document.getElementById("searchInventoryInput");
    const regionFilter = document.getElementById("filterAssetRegion");
    const divFilter = document.getElementById("filterAssetDivision");
    const deptFilter = document.getElementById("filterAssetDepartment");
    const categoryFilter = document.getElementById("filterAssetCategory");
    const statusFilter = document.getElementById("filterAssetStatus");

    if (searchInput) searchInput.addEventListener("keyup", onFilterChange);
    if (regionFilter) regionFilter.addEventListener("change", () => loadInventory(1));
    if (divFilter) divFilter.addEventListener("change", () => loadInventory(1));
    if (deptFilter) deptFilter.addEventListener("change", () => loadInventory(1));
    if (categoryFilter) categoryFilter.addEventListener("change", () => loadInventory(1));
    if (statusFilter) statusFilter.addEventListener("change", () => loadInventory(1));

    // Form submission
    const assetForm = document.getElementById("assetForm");
    if (assetForm) {
        assetForm.addEventListener("submit", saveAsset);
    }

    // Network device type listener
    const networkDeviceType = document.getElementById("specNetworkDeviceType");
    if (networkDeviceType) {
        networkDeviceType.addEventListener("change", toggleNetworkDeviceSpecs);
    }
    // Note: Region, Branch, Dept, Office modal dropdowns use inline onchange handlers
});


async function loadInventory(page) {
    try {
        const params = new URLSearchParams();
        const searchInput = document.getElementById("searchInventoryInput");
        const catFilter = document.getElementById("filterAssetCategory");
        const statFilter = document.getElementById("filterAssetStatus");

        if (searchInput && searchInput.value) params.set('search', searchInput.value);
        if (catFilter && catFilter.value) params.set('category', catFilter.value);
        if (statFilter && statFilter.value) params.set('status', statFilter.value);
        if (page) params.set('page', page);
        params.set('per_page', perPage);

        const qs = params.toString();
        const dataUrl = (window.CMMS_INVENTORY_DATA_URL || '/inventory/data') + (qs ? '?' + qs : '');

        const response = await fetch(dataUrl, {
            credentials: "include",
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const result = await response.json();

        if (result.success) {
            allAssets = result.assets || [];
            allAssets.forEach(a => { assetLookup[a.asset_id || a.id] = a; });
            currentPage = result.current_page || 1;
            lastPage = result.last_page || 1;
            renderInventoryTable(allAssets);
            renderPagination(result.total || 0);
            updateInventorySummary(result.total || 0, result.stats);
        }
    } catch (error) {
        console.error("Error loading inventory:", error);
        document.getElementById("inventoryTableBody").innerHTML = '<tr><td colspan="7" style="text-align: center; color: red;">Error loading inventory.</td></tr>';
    }
}

function exportFilteredInventory() {
    const prefix = window.CMMS_INVENTORY_DETAIL_PREFIX || '/inventory';
    const params = new URLSearchParams();
    const search = document.getElementById("searchInventoryInput")?.value;
    const category = document.getElementById("filterAssetCategory")?.value;
    const status = document.getElementById("filterAssetStatus")?.value;
    if (search) params.set('search', search);
    if (category) params.set('category', category);
    if (status) params.set('status', status);
    window.location.href = prefix + '/export?' + params.toString();
}

function updateInventorySummary(total, stats) {
    if (!document.getElementById("statTotal")) return;

    // Check if any filter is active
    const statusFilter = document.getElementById("filterAssetStatus");
    const categoryFilter = document.getElementById("filterAssetCategory");
    const searchInput = document.getElementById("searchInventoryInput");
    const selectedStatus = statusFilter ? statusFilter.value : '';
    const selectedCategory = categoryFilter ? categoryFilter.value : '';
    const searchVal = searchInput ? searchInput.value.trim() : '';
    const isFiltered = selectedStatus || selectedCategory || searchVal;

    if (isFiltered) {
        // Any filter active: show counts from filtered results only
        document.getElementById("statTotal").textContent = total || 0;
        document.getElementById("statActive").textContent = allAssets.filter(a => a.status === 'Active').length;
        document.getElementById("statSpare").textContent = allAssets.filter(a => a.status === 'Spare').length;
        document.getElementById("statRepair").textContent = allAssets.filter(a => a.status === 'For Repair').length;
        document.getElementById("statDisposal").textContent = allAssets.filter(a => ['For Disposal', 'Scrapped', 'Disposed'].includes(a.status)).length;
    } else {
        // No filter: show unfiltered totals from server
        if (stats) {
            document.getElementById("statTotal").textContent = stats.total || 0;
            document.getElementById("statActive").textContent = stats.active || 0;
            document.getElementById("statSpare").textContent = stats.spare || 0;
            document.getElementById("statRepair").textContent = stats.repair || 0;
            document.getElementById("statDisposal").textContent = stats.disposal || 0;
        } else {
            document.getElementById("statTotal").textContent = total || 0;
            document.getElementById("statActive").textContent = allAssets.filter(a => a.status === 'Active').length;
            document.getElementById("statSpare").textContent = allAssets.filter(a => a.status === 'Spare').length;
            document.getElementById("statRepair").textContent = allAssets.filter(a => a.status === 'For Repair').length;
            document.getElementById("statDisposal").textContent = allAssets.filter(a => ['For Disposal', 'Scrapped', 'Disposed'].includes(a.status)).length;
        }
    }
}

function renderInventoryTable(assets) {
    const tbody = document.getElementById("inventoryTableBody");
    if (!tbody) return;

    if (assets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">No asset records found for the selected filters.</td></tr>';
        return;
    }

    tbody.innerHTML = assets.map(asset => {
        let statusClass = 'sp-active'; 
        if (asset.status === 'Spare') statusClass = 'sp-spare';
        if (asset.status === 'Defective') statusClass = 'sp-defective';
        if (asset.status === 'For Repair') statusClass = 'sp-repair';
        if (asset.status === 'Under Maintenance') statusClass = 'sp-maintenance';
        if (asset.status === 'For Disposal') statusClass = 'sp-disposal';
        if (asset.status === 'Scrapped' || asset.status === 'Disposed') statusClass = 'sp-scrapped';

        let rowClass = '';
        if (asset.status === 'For Disposal') rowClass = 'row-disposal';

        // â”€â”€ PAR No â”€â”€
        const parDisplay = asset.par_number
            ? `<span class="par-badge">${asset.par_number}</span>`
            : `<span class="par-badge na">N/A</span>`;

        // â”€â”€ Property No â”€â”€
        const propDisplay = asset.property_number
            ? `<span class="prop-no">${asset.property_number}</span>`
            : `<span style="color:#94a3b8;">—</span>`;

        // â”€â”€ Item Name â”€â”€
        const brandModel = (asset.brand || asset.model)
            ? `<br><span style="font-size: 11px; color: #64748b; font-weight: 400;">${[asset.brand, asset.model].filter(Boolean).join(' ')}</span>`
            : '';
        // â”€â”€ Set badges â”€â”€
        // Parent: show a teal badge with component count (e.g. "Set ▾ (1)")
        // Child : show a muted indent badge ("⤷ Set component")
        let setBadge = '';
        if (asset.components_count > 0) {
            setBadge = `<br><span style="display:inline-block;margin-top:3px;font-size:10px;font-weight:800;color:#0e7490;background:#ecfeff;border:1px solid #a5f3fc;border-radius:4px;padding:1px 7px;letter-spacing:0.02em;"><i class="fa-solid fa-layer-group" style="font-size:9px;margin-right:3px;"></i>Set ▾ (${asset.components_count})</span>`;
        } else if (asset.parent_asset_id) {
            setBadge = `<br><span style="display:inline-block;margin-top:3px;font-size:10px;font-weight:700;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px;padding:1px 7px;">⤷ Set component</span>`;
        }

        const itemNameDisplay = asset.is_depreciated 
            ? `${asset.item_name}${brandModel}${setBadge}<br><span style="background: #fee2e2; color: #dc2626; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 800; border: 1px solid #fca5a5;">DUE FOR REPLACEMENT</span>` 
            : `${asset.item_name}${brandModel}${setBadge}`;

        // â”€â”€ Assigned To â”€â”€
        const userName = asset.assigned_user ? asset.assigned_user.full_name : '<span style="color:#94a3b8;font-style:italic;">Unassigned (Stock)</span>';
        const divAbbr = asset.assigned_user && asset.assigned_user.office
            ? getDivisionAbbr(asset.assigned_user.office) : '';
        const divBadge = divAbbr
            ? `<br><span style="display:inline-block;font-size:10px;font-weight:800;color:#475569;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;padding:1px 5px;letter-spacing:0.03em;font-family:monospace;">${divAbbr}</span>`
            : '';
        const custodianDisplay = asset.assigned_user ? `${userName}${divBadge}` : userName;

        // â”€â”€ Actions Dropdown â”€â”€
        const assetId = asset.asset_id || asset.id;
        const isLocked = ['Scrapped', 'For Disposal'].includes(asset.status);
        const detailPrefix = window.CMMS_INVENTORY_DETAIL_PREFIX || '/inventory';

        let dropdownItems = '';

        if (window.CMMS_INVENTORY_CAN_WRITE) {
            if (isLocked) {
                dropdownItems += `
                    <div style="padding:9px 16px;font-size:12px;color:#94a3b8;">
                        <i class="fa-solid fa-lock"></i> Locked Asset
                    </div>`;
            } else {
                dropdownItems += `
                    <button class="dropdown-item-custom" onclick="editAsset('${assetId}')">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Details
                    </button>
                    <button class="dropdown-item-custom" onclick="openTransferModal('${assetId}')">
                        <i class="fa-solid fa-right-left"></i> Transfer to User
                    </button>`;
            }
        }

        dropdownItems += `
            <a class="dropdown-item-custom" href="${detailPrefix}/${assetId}/detail">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> View Full Details
            </a>
            <button class="dropdown-item-custom" onclick="viewAssetHistory('${assetId}')">
                <i class="fa-solid fa-clock-rotate-left"></i> Lifecycle History
            </button>`;

        if (window.CMMS_INVENTORY_CAN_WRITE && !isLocked) {
            dropdownItems += `
                <hr class="dropdown-divider-custom">
                <button class="dropdown-item-custom text-danger" onclick="confirmDeleteAsset('${assetId}')">
                    <i class="fa-solid fa-trash-can"></i> Delete
                </button>`;
        }

        return `
            <tr class="${rowClass} tr-hover-row">
                <td>${parDisplay}</td>
                <td>${propDisplay}</td>
                <td style="font-weight: 700; color: #1e293b; line-height: 1.4;">${itemNameDisplay}</td>
                <td style="font-size:12px;color:#475569;">${asset.category || '—'}</td>
                <td class="serial-font">${asset.serial_number || 'N/A'}</td>
                <td style="color: #475569;">${custodianDisplay}</td>
                <td style="text-align: center;">
                    <span class="status-pill ${statusClass}">
                        ${asset.status}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div class="actions-dropdown">
                        <button class="btn-dropdown-toggle" onclick="toggleDropdown(event, this)" title="Actions">
                            ⋯
                        </button>
                        <div class="dropdown-menu-custom">
                            ${dropdownItems}
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }).join("") + `<div class="dropdown-backdrop" id="dropdownBackdrop" onclick="closeAllDropdowns()"></div>`;
}

// â”€â”€ Dropdown Toggle â”€â”€
function toggleDropdown(event, btn) {
    event.stopPropagation();
    closeAllDropdowns();
    const menu = btn.nextElementSibling;
    const backdrop = document.getElementById('dropdownBackdrop');
    if (menu) {
        menu.classList.add('show');
        if (backdrop) backdrop.classList.add('show');
    }
}

function closeAllDropdowns() {
    document.querySelectorAll('.dropdown-menu-custom.show').forEach(m => m.classList.remove('show'));
    const backdrop = document.getElementById('dropdownBackdrop');
    if (backdrop) backdrop.classList.remove('show');
}

// Close dropdowns on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAllDropdowns();
});

// â”€â”€ Delete Asset â”€â”€
function confirmDeleteAsset(assetId) {
    closeAllDropdowns();
    const asset = assetLookup[assetId];
    if (!asset) return;

    Swal.fire({
        icon: 'warning',
        title: 'Delete Asset?',
        html: `Are you sure you want to delete <strong>${asset.item_name}</strong> (SN: ${asset.serial_number || 'N/A'})?<br><br><span style="color:#dc2626;font-size:12px;">This will permanently remove the asset record.</span>`,
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fa-solid fa-trash-can"></i> Delete',
        cancelButtonText: 'Cancel'
    }).then(async (result) => {
        if (!result.isConfirmed) return;

        try {
            const response = await fetch(`/inventory/${assetId}`, {
            credentials: "include",
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: data.message || 'Asset has been deleted.',
                    confirmButtonColor: '#0038A8',
                    timer: 2000,
                    timerProgressBar: true
                });
                loadInventory();
            } else {
                Swal.fire({ icon: 'error', title: 'Delete Failed', text: data.message || 'Unknown error.', confirmButtonColor: '#d33' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Could not connect to server.', confirmButtonColor: '#d33' });
        }
    });
}

function filterInventory() {
    loadInventory(1);
}

function onFilterChange() {
    clearTimeout(filterChangeTimer);
    filterChangeTimer = setTimeout(() => loadInventory(1), 300);
}

function renderPagination(totalFiltered) {
    const container = document.getElementById("inventoryPagination");
    if (!container) return;

    if (lastPage <= 1) {
        container.innerHTML = "";
        return;
    }

    let html = '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;">';
    html += `<span style="font-size:12px;color:#64748b;">Showing ${Math.min((currentPage-1)*perPage+1, totalFiltered)}–${Math.min(currentPage*perPage, totalFiltered)} of ${totalFiltered}</span>`;
    html += '<div style="display:flex;gap:4px;">';

    html += `<button onclick="goToPage(${currentPage - 1})" style="padding:5px 10px;border:1px solid #cbd5e1;border-radius:4px;background:${currentPage <= 1 ? '#f1f5f9' : 'white'};color:${currentPage <= 1 ? '#94a3b8' : '#1e293b'};cursor:${currentPage <= 1 ? 'default' : 'pointer'};font-size:12px;font-weight:700;" ${currentPage <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button>`;

    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(lastPage, currentPage + 2);
    if (startPage > 1) {
        html += `<button onclick="goToPage(1)" style="padding:5px 10px;border:1px solid #cbd5e1;border-radius:4px;background:white;color:#1e293b;cursor:pointer;font-size:12px;font-weight:700;">1</button>`;
        if (startPage > 2) html += '<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>';
    }
    for (let i = startPage; i <= endPage; i++) {
        const active = i === currentPage;
        html += `<button onclick="goToPage(${i})" style="padding:5px 10px;border:1px solid ${active ? '#0038A8' : '#cbd5e1'};border-radius:4px;background:${active ? '#0038A8' : 'white'};color:${active ? 'white' : '#1e293b'};cursor:pointer;font-size:12px;font-weight:700;">${i}</button>`;
    }
    if (endPage < lastPage) {
        if (endPage < lastPage - 1) html += '<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>';
        html += `<button onclick="goToPage(${lastPage})" style="padding:5px 10px;border:1px solid #cbd5e1;border-radius:4px;background:white;color:#1e293b;cursor:pointer;font-size:12px;font-weight:700;">${lastPage}</button>`;
    }

    html += `<button onclick="goToPage(${currentPage + 1})" style="padding:5px 10px;border:1px solid #cbd5e1;border-radius:4px;background:${currentPage >= lastPage ? '#f1f5f9' : 'white'};color:${currentPage >= lastPage ? '#94a3b8' : '#1e293b'};cursor:${currentPage >= lastPage ? 'default' : 'pointer'};font-size:12px;font-weight:700;" ${currentPage >= lastPage ? 'disabled' : ''}>Next &rsaquo;</button>`;

    html += '</div></div>';
    container.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > lastPage || page === currentPage) return;
    loadInventory(page);
}

function toggleSpecsForm() {
    const category = document.getElementById("assetCategory").value;
    const dynamicSpecs = document.getElementById("dynamicSpecsContainer");
    const monitorSpecs = document.getElementById("monitorSpecsContainer");
    const networkSpecs = document.getElementById("networkSpecsContainer");
    const generalSpecs = document.getElementById("generalSpecsGroup");
    const itPartsSection = document.getElementById("itPartsSection");
    const desktopAccessories = document.getElementById("desktopAccessoriesSection");
    const laptopBattery = document.getElementById("laptopBatterySection");

    if (dynamicSpecs) dynamicSpecs.style.display = "none";
    if (monitorSpecs) monitorSpecs.style.display = "none";
    if (networkSpecs) networkSpecs.style.display = "none";
    if (generalSpecs) generalSpecs.style.display = "none";
    if (itPartsSection) itPartsSection.style.display = "none";

    if ((category === "Desktop" || category === "Laptop" || category === "Desktop/Laptop") && dynamicSpecs) {
        dynamicSpecs.style.display = "block";
        // Show desktop-only or laptop-only sections
        if (desktopAccessories) desktopAccessories.style.display = category === "Laptop" ? "none" : "block";
        if (laptopBattery) laptopBattery.style.display = category === "Laptop" ? "block" : "none";
    } else if (category === "Monitor" && monitorSpecs) {
        monitorSpecs.style.display = "block";
    } else if (category === "Network/Server" && networkSpecs) {
        networkSpecs.style.display = "block";
    } else if (generalSpecs) {
        generalSpecs.style.display = "block";
        if (category === "IT Parts / Components" && itPartsSection) {
            itPartsSection.style.display = "block";
        }
    }
}

function itPartTypeChange() {
    const type = document.getElementById("itPartType")?.value;
    const spec = document.getElementById("itPartSpec")?.value?.trim();
    const textarea = document.getElementById("generalSpecifications");
    if (!textarea || !type) return;
    const combined = spec ? `${type} — ${spec}` : type;
    textarea.value = combined;
}

function toggleNetworkDeviceSpecs() {
    const deviceType = document.getElementById("specNetworkDeviceType").value;
    const desktopLaptopSpecs = document.getElementById("networkDesktopLaptopSpecs");
    const equipmentSpecs = document.getElementById("networkEquipmentSpecs");

    desktopLaptopSpecs.style.display = "none";
    equipmentSpecs.style.display = "none";

    if (deviceType === "Desktop" || deviceType === "Laptop") {
        desktopLaptopSpecs.style.display = "grid";
    } else if (["Server", "Network Equipment", "Firewall", "Switch", "Router"].includes(deviceType)) {
        equipmentSpecs.style.display = "block";
    }
}

// Safe helper: sets an element's value only if the element exists
function setInputVal(id, value, defaultVal = "") {
    const el = document.getElementById(id);
    if (!el) return;
    const v = (value !== undefined && value !== null && value !== '') ? value : defaultVal;

    // For <select> elements, if the value (e.g. from CSV import) doesn't match
    // any existing option, append it as a custom option so the imported spec is
    // still visible to the supply officer instead of silently clearing the field.
    if (el.tagName === 'SELECT' && v !== '' && v !== 'None') {
        let exists = false;
        for (const opt of el.options) {
            if (opt.value === v) { exists = true; break; }
        }
        if (!exists) {
            const custom = document.createElement('option');
            custom.value = v;
            // Tag imported specs so the supply officer can tell they're unverified.
            custom.textContent = v + ' (imported)';
            custom.dataset.imported = '1';
            el.appendChild(custom);
        }
    }
    el.value = v;
}

function openAddAssetModal() {
    document.getElementById("assetId").value = "";
    document.getElementById("assetForm").reset();
    const hiddenUser = document.getElementById("assetAssignedUser");
    if (hiddenUser) hiddenUser.value = "";
    // Hide edit banner
    const banner = document.getElementById('editAssetBanner');
    if (banner) banner.style.display = 'none';
    toggleSpecsForm();
    document.getElementById("assetModalTitle").textContent = "Register Asset";
    fetchFilteredUsers();
    document.getElementById("assetModal").style.display = "flex";
}

function closeAssetModal() {
    document.getElementById("assetModal").style.display = "none";
}

async function editAsset(id) {
    const asset = assetLookup[id];
    if (!asset) {
        console.warn("editAsset: asset not found for id:", id);
        return;
    }

    // Scrapped assets are fully locked — do not open edit modal
    if (asset.status === 'Scrapped') {
        Swal.fire({
            icon: 'warning',
            title: 'Asset Locked',
            html: `<strong>${asset.item_name}</strong> is <span style="color:#dc2626; font-weight:800;">Scrapped</span>.<br><br>Scrapped assets cannot be edited to preserve disposal audit records.`,
            confirmButtonColor: '#64748b',
            confirmButtonText: 'Understood'
        });
        return;
    }

    // Temporarily detach category change listener so form.reset() doesn't
    // trigger toggleSpecsForm before we set the correct category.
    const categorySelect = document.getElementById("assetCategory");
    if (categorySelect) categorySelect.removeEventListener("change", toggleSpecsForm);

    // Reset form first then populate
    document.getElementById("assetForm").reset();

    // Re-attach the category change listener
    if (categorySelect) categorySelect.addEventListener("change", toggleSpecsForm);

    document.getElementById("assetId").value = asset.asset_id || asset.id;
    document.getElementById("assetCategory").value = asset.category;
    toggleSpecsForm();
    setInputVal("assetItemName", asset.item_name);

    // Populate spec fields
    let specs = asset.specifications;
    // Parse JSON if specs is a string
    if (specs && typeof specs === 'string') {
        try {
            specs = JSON.parse(specs);
        } catch (e) {
            console.warn("Could not parse specs JSON:", e);
            specs = {};
        }
    }
    
    if (specs && typeof specs === 'object') {
        if (asset.category === "Desktop" || asset.category === "Laptop" || asset.category === "Desktop/Laptop") {
            setInputVal("specCpu", specs.cpu);
            setInputVal("specRam", specs.ram);
            setInputVal("specHd1", specs.hd1);
            setInputVal("specHd2", specs.hd2, "None");
            setInputVal("specOs", specs.os);
            setInputVal("specOffice", specs.office);
            setInputVal("specGpu", specs.gpu);
            // Desktop accessories
            setInputVal("specFormFactor", specs.form_factor);
            setInputVal("specMonitorIncluded", specs.monitor_included);
            setInputVal("specKeyboard", specs.keyboard);
            setInputVal("specMouse", specs.mouse);
            // Laptop battery
            setInputVal("specBattery", specs.battery);
        } else if (asset.category === "Monitor") {
            setInputVal("specMonitorBrand", specs.monitor_brand);
            setInputVal("specMonitorModel", specs.monitor_model);
            setInputVal("specMonitorSize", specs.monitor_size);
            setInputVal("specMonitorResolution", specs.monitor_resolution);
            setInputVal("specMonitorNotes", specs.monitor_notes);
        } else if (asset.category === "Network/Server") {
            setInputVal("specNetworkDeviceType", specs.network_device_type);
            toggleNetworkDeviceSpecs();
            setInputVal("specNetworkCpu", specs.network_cpu);
            setInputVal("specNetworkRam", specs.network_ram);
            setInputVal("specNetworkStorage", specs.network_storage);
            setInputVal("specNetworkOs", specs.network_os);
            setInputVal("specNetworkBrand", specs.network_brand);
            setInputVal("specNetworkModel", specs.network_model);
            setInputVal("specNetworkEquipmentSpecs", specs.network_equipment_specs);
        }
    } else {
        setInputVal("generalSpecifications", asset.specifications);
    }

    setInputVal("assetSerialNumber", asset.serial_number);
    setInputVal("assetStatus", asset.status);
    setInputVal("assetBrandInput", asset.brand ?? "");
    setInputVal("assetModelInput", asset.model ?? "");
    setInputVal("assetPropertyNumber", asset.property_number);
    setInputVal("assetDateAcquired", asset.date_acquired ? asset.date_acquired.substring(0, 10) : "");
    setInputVal("assetWarrantyExpiration", asset.warranty_expiration ? asset.warranty_expiration.substring(0, 10) : "");
    setInputVal("assetAcquisitionCost", asset.acquisition_cost ?? "");
    setInputVal("assetEndOfUsefulLife", asset.end_of_useful_life ? asset.end_of_useful_life.substring(0, 10) : "");
    setInputVal("assetNotes", asset.asset_notes ?? "");

    // Load users and pre-select the current custodian
    if (asset.assigned_user) {
        setInputVal("assetDepartment", asset.assigned_user.department);
        setInputVal("assetOffice", asset.assigned_user.office);
    }

    await fetchFilteredUsers();

    // Ensure currently assigned user appears and is selected
    const assignedSelect = document.getElementById("assetAssignedUser");
    if (assignedSelect && asset.assigned_to_user) {
        if (!assignedSelect.querySelector(`option[value="${asset.assigned_to_user}"]`)) {
            const opt = document.createElement('option');
            opt.value = asset.assigned_to_user;
            opt.textContent = asset.assigned_user ? asset.assigned_user.full_name : `User #${asset.assigned_to_user}`;
            assignedSelect.appendChild(opt);
        }
        assignedSelect.value = asset.assigned_to_user;
    }

    // Show edit banner with current asset info
    const banner = document.getElementById('editAssetBanner');
    if (banner) {
        banner.style.display = 'block';
        document.getElementById('editBannerName').textContent = asset.item_name;
        document.getElementById('editBannerSN').textContent = asset.serial_number || 'N/A';
        document.getElementById('editBannerCustodian').textContent =
            asset.assigned_user ? asset.assigned_user.full_name : 'Unassigned (Stock)';
    }

    document.getElementById("assetModalTitle").textContent = "Edit Asset";
    document.getElementById("assetModal").style.display = "flex";
}

async function saveAsset(event) {
    event.preventDefault();
    
    const id = document.getElementById("assetId").value;
    const category = document.getElementById("assetCategory").value;
    let specificationsData = {};

    if (category === "Desktop" || category === "Laptop" || category === "Desktop/Laptop") {
        specificationsData = {
            os:       document.getElementById("specOs")?.value || '',
            cpu:      document.getElementById("specCpu")?.value || '',
            ram:      document.getElementById("specRam")?.value || '',
            hd1:      document.getElementById("specHd1")?.value || '',
            hd2:      document.getElementById("specHd2")?.value || 'None',
            gpu:      document.getElementById("specGpu")?.value || '',
            office:   document.getElementById("specOffice")?.value || '',
            // Desktop-only accessories
            form_factor:      document.getElementById("specFormFactor")?.value || '',
            monitor_included: document.getElementById("specMonitorIncluded")?.value || '',
            keyboard:         document.getElementById("specKeyboard")?.value || '',
            mouse:            document.getElementById("specMouse")?.value || '',
            // Laptop-only
            battery:          document.getElementById("specBattery")?.value || '',
        };
    } else if (category === "Monitor") {
        specificationsData = {
            monitor_brand: document.getElementById("specMonitorBrand").value,
            monitor_model: document.getElementById("specMonitorModel").value,
            monitor_size: document.getElementById("specMonitorSize").value,
            monitor_resolution: document.getElementById("specMonitorResolution").value,
            monitor_notes: document.getElementById("specMonitorNotes").value
        };
    } else if (category === "Network/Server") {
        const deviceType = document.getElementById("specNetworkDeviceType").value;
        specificationsData = { network_device_type: deviceType };

        if (["Desktop", "Laptop"].includes(deviceType)) {
            specificationsData.network_cpu = document.getElementById("specNetworkCpu").value;
            specificationsData.network_ram = document.getElementById("specNetworkRam").value;
            specificationsData.network_storage = document.getElementById("specNetworkStorage").value;
            specificationsData.network_os = document.getElementById("specNetworkOs").value;
        } else {
            specificationsData.network_brand = document.getElementById("specNetworkBrand").value;
            specificationsData.network_model = document.getElementById("specNetworkModel").value;
            specificationsData.network_equipment_specs = document.getElementById("specNetworkEquipmentSpecs").value;
        }
    } else {
        specificationsData = document.getElementById("generalSpecifications").value;
    }

    const assignedUserEl = document.getElementById("assetAssignedUser");
    const regionEl = document.getElementById("assetRegion");

    const payload = {
        category: category,
        item_name: document.getElementById("assetItemName").value,
        brand: document.getElementById("assetBrandInput")?.value || null,
        model: document.getElementById("assetModelInput")?.value || null,
        specifications: typeof specificationsData === 'object' ? JSON.stringify(specificationsData) : specificationsData,
        serial_number: document.getElementById("assetSerialNumber").value,
        assigned_to_user: assignedUserEl ? assignedUserEl.value : "",
        status: document.getElementById("assetStatus").value,
        region: regionEl ? regionEl.value : null,
        property_number: document.getElementById("assetPropertyNumber")?.value || null,
        date_acquired: document.getElementById("assetDateAcquired")?.value || null,
        warranty_expiration: document.getElementById("assetWarrantyExpiration")?.value || null,
        acquisition_cost: document.getElementById("assetAcquisitionCost")?.value || null,
        end_of_useful_life: document.getElementById("assetEndOfUsefulLife")?.value || null,
        asset_notes: document.getElementById("assetNotes")?.value || null,
    };
    
    const url = id ? `/inventory/${id}` : "/inventory";
    const method = id ? "PUT" : "POST";
    
    // Disable save button to prevent double-submit
    const saveBtn = document.querySelector('#assetForm button[type="submit"]');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }

    try {
        const response = await fetch(url, {
            credentials: "include",
            method: method,
            headers: { 
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        if (result.success) {
            closeAssetModal();
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: result.message || 'Asset record saved successfully.',
                confirmButtonColor: '#0038A8',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            });
            loadInventory();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Save Failed',
                text: result.message || 'An unknown error occurred.',
                confirmButtonColor: '#d33'
            });
        }
    } catch (error) {
        console.error("Error saving asset:", error);
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Could not connect to the server. Please try again.',
            confirmButtonColor: '#d33'
        });
    } finally {
        // Re-enable save button
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Asset Record'; }
    }
}

function openDisposalFromList(assetId) {
    const prefix = window.CMMS_INVENTORY_DETAIL_PREFIX || '/inventory';
    window.location.href = `${prefix}/${assetId}/detail#disposal`;
}

function openTransferModal(assetId) {
    console.log("Opening transfer modal for asset ID: ", assetId);
    const asset = assetLookup[assetId];
    if (!asset) {
        console.error("Asset not found in assetLookup!");
        Swal.fire('Error', 'Could not find asset data locally. Try refreshing the page.', 'error');
        return;
    }

    // Block transfer of scrapped assets
    if (asset.status === 'Scrapped') {
        Swal.fire({
            icon: 'warning',
            title: 'Asset Locked',
            text: 'Scrapped assets cannot be transferred or reassigned.',
            confirmButtonColor: '#64748b'
        });
        return;
    }

    const modal = document.getElementById("transferModal");
    if (!modal) {
        console.error("Transfer Modal element not found!");
        return;
    }

    document.getElementById("transferAssetId").value = assetId;
    document.getElementById("transferAssetName").textContent = asset.item_name + ' (SN: ' + (asset.serial_number || 'N/A') + ')';
    document.getElementById("transferCurrentCustodian").textContent = 
        asset.assigned_user ? asset.assigned_user.full_name : 'Unassigned (Stock)';

    // Pre-populate the custodian dropdown
    const select = document.getElementById("transferAssignedUser");
    select.innerHTML = '<option value="">-- Not Assigned (Return to Stock) --</option>';
    
    // Load users
    const usersPrefix = window.CMMS_INVENTORY_DETAIL_PREFIX || '/inventory';
    fetch(`/inventory/users?` + new URLSearchParams({ branch: asset.branch || '' }), {
            credentials: "include",
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.json()).then(data => {
        if (data.success) {
            data.users.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.name;
                if (asset.assigned_to_user && u.id == asset.assigned_to_user) opt.selected = true;
                select.appendChild(opt);
            });
        }
    }).catch(() => {
        console.error('Failed to load users for transfer modal');
    });

    document.getElementById("transferModal").style.display = "flex";
}

function closeTransferModal() {
    document.getElementById("transferModal").style.display = "none";
}

async function saveTransfer(event) {
    event.preventDefault();
    const assetId = document.getElementById("transferAssetId").value;
    const assignedUser = document.getElementById("transferAssignedUser").value;
    const remarks = document.getElementById("transferRemarks").value;
    const transferReceiptNoEl = document.getElementById("transferReceiptNo");
    const transferReceiptNo = transferReceiptNoEl ? transferReceiptNoEl.value : '';

    const asset = assetLookup[assetId];
    if (!asset) return;

    // Prevent transferring to the same custodian
    const currentCustodian = asset.assigned_to_user ? String(asset.assigned_to_user) : "";
    if (assignedUser === currentCustodian) {
        Swal.fire({
            icon: 'warning',
            title: 'No Change',
            text: 'This asset is already assigned to this person. Select a different custodian.',
            confirmButtonColor: '#0038A8'
        });
        return;
    }

    // Build update payload — only change custodian + status, preserve all other fields
    // Status logic: Active when assigned, Spare when unassigned
    // BUT preserve Defective/Scrapped/For Repair — only change Active↔Spare
    const preservedStatuses = ['Defective', 'Scrapped', 'For Repair'];
    const newStatus = preservedStatuses.includes(asset.status)
        ? asset.status  // keep the problematic status — don't auto-set to Active
        : (assignedUser ? 'Active' : 'Spare');
    const payload = {
        item_name: asset.item_name,
        serial_number: asset.serial_number || '',
        brand: asset.brand,
        model: asset.model,
        status: newStatus,
        assigned_to_user: assignedUser,
        category: asset.category,
        specifications: typeof asset.specifications === 'object' ? JSON.stringify(asset.specifications) : (asset.specifications || ''),
        remarks: remarks,
        property_number: asset.property_number,
        date_acquired: asset.date_acquired,
        warranty_expiration: asset.warranty_expiration,
        acquisition_cost: asset.acquisition_cost,
        end_of_useful_life: asset.end_of_useful_life,
        asset_notes: asset.asset_notes,
        transfer_receipt_no: transferReceiptNo,
    };

    const btn = document.getElementById("transferSaveBtn");
    btn.disabled = true; btn.textContent = 'Processing...';

    try {
        const response = await fetch(`/inventory/${assetId}`, {
            credentials: "include",
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            closeTransferModal();
            await Swal.fire({
                icon: 'success',
                title: 'Custodian Updated!',
                text: result.message || 'Asset custodian has been updated. Lifecycle history recorded.',
                confirmButtonColor: '#0038A8',
                timer: 3000,
                timerProgressBar: true
            });
            loadInventory();
        } else {
            Swal.fire({ icon: 'error', title: 'Transfer Failed', text: result.message || 'Unknown error.', confirmButtonColor: '#d33' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Could not connect to server.', confirmButtonColor: '#d33' });
    } finally {
        btn.disabled = false; btn.textContent = 'Confirm Transfer';
    }
}

// ==========================================
// CSV IMPORT FUNCTIONALITY
// ==========================================
document.addEventListener("DOMContentLoaded", function () {
    const importBtn = document.getElementById("importCsvBtn");
    const csvInput = document.getElementById("inventoryCsvInput");

    if (importBtn && csvInput) {
        importBtn.addEventListener("click", function () {
            csvInput.click();
        });

        csvInput.addEventListener("change", function () {
            const file = this.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append("file", file);
            formData.append("_token", document.querySelector('meta[name="csrf-token"]').getAttribute("content"));

            Swal.fire({
                title: "Uploading CSV...",
                text: "Please wait while we analyze your file.",
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch("/inventory/import/preview", {
                method: "POST",
                credentials: "include",
                headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire({ icon: "error", title: "Import Failed", text: data.message || "Could not parse CSV." });
                    return;
                }

                currentInventoryImportToken = data.token;
                const s = data.summary;
                let html = `<div style="text-align:left;font-size:13px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;margin-bottom:12px;">
                        <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;">Total Rows</span><br><strong style="font-size:16px;">${s.total_rows}</strong></div>
                        <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;">Valid</span><br><strong style="font-size:16px;color:#16a34a;">${s.valid_rows}</strong></div>
                        <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;">Errors/Duplicates</span><br><strong style="font-size:16px;color:#dc2626;">${s.duplicate_rows}</strong></div>
                        <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;">Needs Review</span><br><strong style="font-size:16px;color:#d97706;">${s.needs_review_rows}</strong></div>
                        <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;">Matched Custodians</span><br><strong style="font-size:16px;">${s.matched_custodians}</strong></div>
                        <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;">Unmatched</span><br><strong style="font-size:16px;">${s.unmatched_custodians}</strong></div>
                        ${s.set_rows > 0 ? `<div style="grid-column:span 2;border-top:1px solid #e2e8f0;padding-top:8px;margin-top:4px;"><span style="font-size:11px;font-weight:700;color:#0e7490;background:#ecfeff;border:1px solid #a5f3fc;border-radius:4px;padding:2px 8px;"><i class="fa-solid fa-layer-group" style="font-size:10px;margin-right:4px;"></i>${s.set_rows} Complete Set row(s) → ${s.set_rows + s.component_rows} asset records (${s.component_rows} component(s) split out)</span></div>` : ''}
                    </div>`;

                if (data.items && data.items.length > 0) {
                    html += `<p style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Preview (first ${data.preview_limit} rows):</p>`;
                    data.items.forEach(item => {
                        const badge = item.status === "valid" ? "âœ…" : item.status === "needs_review" ? "âš ï¸" : "âŒ";
                        const errs = item.errors.length ? `<br><span style="color:#dc2626;font-size:11px;">${item.errors.join("; ")}</span>` : "";
                        const warns = item.warnings.length ? `<br><span style="color:#d97706;font-size:11px;">${item.warnings.join("; ")}</span>` : "";
                        // Use records[0] for item name (new format after service refactor)
                        const firstName = (item.records && item.records[0]) ? (item.records[0].item_name || "N/A") : "N/A";
                        const setTag = item.is_set
                            ? ` <span style="font-size:10px;font-weight:800;color:#0e7490;background:#ecfeff;border:1px solid #a5f3fc;border-radius:3px;padding:1px 6px;"><i class="fa-solid fa-layer-group" style="font-size:9px;"></i> Set (${item.records.length} records)</span>`
                            : '';
                        html += `<div style="padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:12px;">
                            ${badge} <strong>Row ${item.row_number}:</strong> ${firstName}${setTag} ${errs} ${warns}
                        </div>`;
                    });
                }
                html += `</div>`;

                Swal.fire({
                    title: "Import Preview",
                    html: html,
                    icon: "info",
                    showCancelButton: true,
                    confirmButtonText: `<i class="fa-solid fa-check"></i> Import ${s.valid_rows} Records`,
                    cancelButtonText: "Cancel",
                    confirmButtonColor: "#16a34a",
                    cancelButtonColor: "#64748b"
                }).then(result => {
                    if (result.isConfirmed) {
                        commitImport();
                    }
                });
            })
            .catch(err => {
                Swal.fire({ icon: "error", title: "Upload Error", text: "Could not connect to server." });
            });

            this.value = "";
        });
    }
});

function commitImport() {
    if (!currentInventoryImportToken) return;

    Swal.fire({
        title: "Importing Records...",
        text: "Please wait while records are being saved.",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch("/inventory/import/commit", {
        method: "POST",
        credentials: "include",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
            "X-Requested-With": "XMLHttpRequest"
        },
        body: JSON.stringify({ token: currentInventoryImportToken })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: "success",
                title: "Import Complete!",
                text: data.message || `Successfully imported records.`,
                confirmButtonColor: "#0038A8"
            }).then(() => {
                currentInventoryImportToken = null;
                loadInventory();
            });
        } else {
            Swal.fire({ icon: "error", title: "Import Failed", text: data.message || "Could not complete import." });
        }
    })
    .catch(err => {
        Swal.fire({ icon: "error", title: "Import Error", text: "Could not connect to server." });
    });
}

// Global modal close — click outside overlay to dismiss
document.addEventListener("click", function(event) {
    const assetModal = document.getElementById("assetModal");
    const historyModal = document.getElementById("assetHistoryModal");
    const transferModal = document.getElementById("transferModal");
    if (event.target === assetModal) closeAssetModal();
    if (event.target === historyModal) closeHistoryModal();
    if (event.target === transferModal) closeTransferModal();
});

// ── Expose to global scope for Vite ES module compatibility ──
window.getDivisionAbbr = getDivisionAbbr;
window.exportFilteredInventory = exportFilteredInventory;
window.updateInventorySummary = updateInventorySummary;
window.renderInventoryTable = renderInventoryTable;
window.toggleDropdown = toggleDropdown;
window.closeAllDropdowns = closeAllDropdowns;
window.confirmDeleteAsset = confirmDeleteAsset;
window.filterInventory = filterInventory;
window.onFilterChange = onFilterChange;
window.renderPagination = renderPagination;
window.goToPage = goToPage;
window.toggleSpecsForm = toggleSpecsForm;
window.itPartTypeChange = itPartTypeChange;
window.toggleNetworkDeviceSpecs = toggleNetworkDeviceSpecs;
window.setInputVal = setInputVal;
window.openAddAssetModal = openAddAssetModal;
window.closeAssetModal = closeAssetModal;
window.closeHistoryModal = closeHistoryModal;
window.openDisposalFromList = openDisposalFromList;
window.openTransferModal = openTransferModal;
window.closeTransferModal = closeTransferModal;
window.saveTransfer = saveTransfer;
window.commitImport = commitImport;
window.saveAsset = saveAsset;
window.loadInventory = loadInventory;
window.editAsset = editAsset;
window.viewAssetHistory = viewAssetHistory;
window.updateModalBranchDropdown = updateModalBranchDropdown;