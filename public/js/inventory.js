 // Inventory Management Script for Laravel
let allAssets = [];
let allUsers = [];

// Division abbreviation lookup — maps full office/division names to short codes
function getDivisionAbbr(office) {
    if (!office) return '';
    const key = office.toLowerCase().trim();
    const map = {
        'research and information division': 'RID',
        'research and info division': 'RID',
        'rid': 'RID',
        'administrative division': 'AD',
        'administrative': 'AD', 'ad': 'AD',
        'financial and management division': 'FMD',
        'financial and management': 'FMD', 'fmd': 'FMD',
        'conciliation and mediation division': 'CMD',
        'conciliation-mediation': 'CMD',
        'conciliation and mediation': 'CMD', 'cmd': 'CMD',
        'commission on audit': 'COA', 'coa': 'COA',
        'technical services department': 'TSD',
        'technical services': 'TSD', 'tsd': 'TSD',
        'voluntary arbitration division': 'VAD',
        'voluntary arbitration program': 'VAD',
        'voluntary arbitration': 'VAD', 'vad': 'VAD',
        'office of the executive director': 'OED',
        'office of executive director': 'OED', 'oed': 'OED',
        'workplace relations enhancement division': 'WRED',
        'workplace relations and enhancement division': 'WRED',
        'workplace relations enhancement': 'WRED', 'wred': 'WRED',
        'internal services department': 'ISD',
        'internal services': 'ISD', 'isd': 'ISD',
    };
    return map[key] || '';
}

document.addEventListener("DOMContentLoaded", function () {
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

    function filterAndResetPage() { currentPage = 1; filterInventory(); }
    if (searchInput) searchInput.addEventListener("keyup", filterAndResetPage);
    if (regionFilter) regionFilter.addEventListener("change", filterAndResetPage);
    if (divFilter) divFilter.addEventListener("change", filterAndResetPage);
    if (deptFilter) deptFilter.addEventListener("change", filterAndResetPage);
    if (categoryFilter) categoryFilter.addEventListener("change", filterAndResetPage);
    if (statusFilter) statusFilter.addEventListener("change", filterAndResetPage);

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


// Branch options per region (for the inventory modal)
const INVENTORY_BRANCH_MAP = {
    'NCR':        ['NCMB Main Office', 'RCMB-NCR'],
    'CAR':        ['RCMB-CAR'],
    'Region I':   ['RCMB-I (Ilocos Region)'],
    'Region II':  ['RCMB-II (Cagayan Valley)'],
    'Region III': ['RCMB-III (Central Luzon)'],
    'Region IV-A':['RCMB-IV-A (CALABARZON)'],
    'Region IV-B':['RCMB-IV-B (MIMAROPA)'],
    'Region V':   ['RCMB-V (Bicol Region)'],
    'Region VI':  ['RCMB-VI (Western Visayas)'],
    'Region VII': ['RCMB-VII (Central Visayas)'],
    'Region VIII':['RCMB-VIII (Eastern Visayas)'],
    'Region IX':  ['RCMB-IX (Zamboanga Peninsula)'],
    'Region X':   ['RCMB-X (Northern Mindanao)'],
    'Region XI':  ['RCMB-XI (Davao Region)'],
    'Region XII': ['RCMB-XII (SOCCSKSARGEN)'],
    'Region XIII':['RCMB-XIII (Caraga)'],
    'BARMM':      ['RCMB-BARMM'],
};

/**
 * Populate the Branch dropdown in the modal based on selected region.
 * @param {string} region
 * @param {boolean} autoFetch - if true, also calls fetchFilteredUsers() after populating
 */
function updateModalBranchDropdown(region, autoFetch = true) {
    const branchSelect = document.getElementById('assetBranch');
    if (!branchSelect) return;
    const branches = INVENTORY_BRANCH_MAP[region] || [];
    branchSelect.innerHTML = '<option value="">-- All Branches --</option>';
    branches.forEach(b => {
        branchSelect.innerHTML += `<option value="${b}">${b}</option>`;
    });
    if (branches.length === 1) branchSelect.value = branches[0];
    if (autoFetch) fetchFilteredUsers();
}

/**
 * Fetch users for the Assign dropdown based on the current modal filter values.
 * Reads: assetRegion, assetBranch, assetDepartment, assetOffice.
 */
async function fetchFilteredUsers() {
    const select = document.getElementById("assetAssignedUser");
    if (!select) return;

    const params = new URLSearchParams();
    const regionEl = document.getElementById("assetRegion");
    const branchEl = document.getElementById("assetBranch");
    const deptEl   = document.getElementById("assetDepartment");
    const officeEl = document.getElementById("assetOffice");

    if (regionEl && regionEl.value)   params.set('region',     regionEl.value);
    if (branchEl && branchEl.value)   params.set('branch',     branchEl.value);
    if (deptEl   && deptEl.value)     params.set('department', deptEl.value);
    if (officeEl && officeEl.value)   params.set('office',     officeEl.value);

    try {
        const currentVal = select.value;
        const response = await fetch(`/inventory/users?${params.toString()}`, {
            credentials: "include",
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();

        if (result.success) {
            select.innerHTML = '<option value="">-- Not Assigned (Available in Stock) --</option>';
            result.users.forEach(u => {
                select.innerHTML += `<option value="${u.id}">${u.name}</option>`;
            });
            // Restore previously selected user if still in the list
            select.value = currentVal;
        }
    } catch (error) {
        console.error("Error fetching users:", error);
    }
}


let inventoryTotal = 0;
let currentPage = 1;
const perPage = 50;

async function loadInventory() {
    try {
        const params = new URLSearchParams();
        const searchInput = document.getElementById("searchInventoryInput");
        const catFilter = document.getElementById("filterAssetCategory");
        const statFilter = document.getElementById("filterAssetStatus");

        if (searchInput && searchInput.value) params.set('search', searchInput.value);
        if (catFilter && catFilter.value) params.set('category', catFilter.value);
        if (statFilter && statFilter.value) params.set('status', statFilter.value);

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
        
        allUsers = [];

        allAssets = result.success ? result.assets : [];
        inventoryTotal = result.total || allAssets.length;
        currentPage = 1;
        filterInventory();
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

function updateInventorySummary(assets) {
    if (!document.getElementById("statTotal")) return;

    const total = inventoryTotal || assets.length;
    const active = assets.filter(a => a.status === 'Active').length;
    const spare = assets.filter(a => a.status === 'Spare').length;
    const defective = assets.filter(a => ['Defective', 'For Repair', 'Scrapped'].includes(a.status)).length;

    document.getElementById("statTotal").textContent = total;
    document.getElementById("statActive").textContent = active;
    document.getElementById("statSpare").textContent = spare;
    document.getElementById("statDefective").textContent = defective;
}

function renderInventoryTable(assets) {
    const tbody = document.getElementById("inventoryTableBody");
    if (!tbody) return;

    if (assets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">No asset records found for the selected filters.</td></tr>';
        return;
    }

    // Sort: Active → Spare → For Repair → Defective → For Disposal → Scrapped
    const statusOrder = { 'Active': 0, 'Spare': 1, 'For Repair': 2, 'Defective': 3, 'For Disposal': 4, 'Scrapped': 5 };
    const sorted = [...assets].sort((a, b) => {
        const aOrder = statusOrder[a.status] ?? 99;
        const bOrder = statusOrder[b.status] ?? 99;
        if (aOrder !== bOrder) return aOrder - bOrder;
        return (a.item_name || '').localeCompare(b.item_name || '');
    });

    tbody.innerHTML = sorted.map(asset => {
        let statusClass = 'sp-active'; 
        if (asset.status === 'Spare') statusClass = 'sp-spare';
        if (['Defective', 'Scrapped'].includes(asset.status)) statusClass = 'sp-defective';
        if (asset.status === 'For Repair') statusClass = 'sp-repair';
        if (asset.status === 'For Disposal') statusClass = 'sp-disposal';
        if (asset.status === 'Scrapped') statusClass = 'sp-scrapped';

        let rowClass = '';
        if (asset.status === 'For Disposal') rowClass = 'row-disposal';

        // ── PAR No ──
        const parDisplay = asset.par_number
            ? `<span class="par-badge">${asset.par_number}</span>`
            : `<span class="par-badge na">N/A</span>`;

        // ── Property No ──
        const propDisplay = asset.property_number
            ? `<span class="prop-no">${asset.property_number}</span>`
            : `<span style="color:#94a3b8;">—</span>`;

        // ── Item Name ──
        const brandModel = (asset.brand || asset.model)
            ? `<br><span style="font-size: 11px; color: #64748b; font-weight: 400;">${[asset.brand, asset.model].filter(Boolean).join(' ')}</span>`
            : '';
        const itemNameDisplay = asset.is_depreciated 
            ? `${asset.item_name}${brandModel}<br><span style="background: #fee2e2; color: #dc2626; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 800; border: 1px solid #fca5a5;">DUE FOR REPLACEMENT</span>` 
            : `${asset.item_name}${brandModel}`;

        // ── Assigned To ──
        const userName = asset.assigned_user ? asset.assigned_user.full_name : '<span style="color:#94a3b8;font-style:italic;">Unassigned (Stock)</span>';
        const divAbbr = asset.assigned_user && asset.assigned_user.office
            ? getDivisionAbbr(asset.assigned_user.office) : '';
        const divBadge = divAbbr
            ? `<br><span style="display:inline-block;font-size:10px;font-weight:800;color:#475569;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;padding:1px 5px;letter-spacing:0.03em;font-family:monospace;">${divAbbr}</span>`
            : '';
        const custodianDisplay = asset.assigned_user ? `${userName}${divBadge}` : userName;

        // ── Actions Dropdown ──
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

// ── Dropdown Toggle ──
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

// ── Delete Asset ──
function confirmDeleteAsset(assetId) {
    closeAllDropdowns();
    const asset = allAssets.find(a => (a.asset_id || a.id) == assetId);
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
    const searchInput = document.getElementById("searchInventoryInput");
    const regFilter = document.getElementById("filterAssetRegion");
    const deptFilter = document.getElementById("filterAssetDepartment");
    const divFilter = document.getElementById("filterAssetDivision");
    const catFilter = document.getElementById("filterAssetCategory");
    const statFilter = document.getElementById("filterAssetStatus");

    const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
    const departmentFilterValue = deptFilter ? deptFilter.value.toUpperCase() : "";
    const divisionFilterValue = divFilter ? divFilter.value.toUpperCase() : "";
    const categoryFilterValue = catFilter ? catFilter.value : "";
    const statusFilterValue = statFilter ? statFilter.value : "";

    function normalizeOfficeDept(officeRaw) {
        if (!officeRaw) return "";
        let cleanRow = officeRaw.toUpperCase().replace(/[^A-Z0-9]/g, '').trim();
        
        if (cleanRow === 'RID' || cleanRow === 'RIDOFFICE' || cleanRow === 'RESEARCHANDINFORMATIONDIVISION' || cleanRow === 'RESEARCHANDINFODIVISION' || cleanRow === 'RESEARCHANDINFO' || cleanRow === 'RESEARCHINFO' || cleanRow === 'RESEARCHANDINFORMATION' || cleanRow === 'ICT' || cleanRow === 'ICTOFFICE' || cleanRow === 'RESEARCH' || cleanRow === 'RESEARCHDIVISION') return 'RESEARCHANDINFORMATIONDIVISION';
        if (cleanRow === 'AD' || cleanRow === 'ADMIN' || cleanRow === 'ADMINOFFICE' || cleanRow === 'ADMINISTRATIVEDIVISION' || cleanRow === 'ADMINISTRATIVE') return 'ADMINISTRATIVEDIVISION';
        if (cleanRow === 'CMD' || cleanRow === 'CMDOFFICE' || cleanRow === 'CONCILIATIONANDMEDIATIONDIVISION' || cleanRow === 'CONCILIATIONANDMEDIATION' || cleanRow === 'CONCILIATIONMEDIATION' || cleanRow === 'CONCILIATION' || cleanRow === 'CONCILIATIONDIVISION') return 'CONCILIATIONANDMEDIATIONDIVISION';
        if (cleanRow === 'OED' || cleanRow === 'OEDOFFICE' || cleanRow === 'OFFICEOFTHEEXECUTIVEDIRECTOR' || cleanRow === 'EXECUTIVEDIRECTOR' || cleanRow === 'EXECUTIVEDIRECTOROFFICE') return 'OFFICEOFTHEEXECUTIVEDIRECTOR';
        if (cleanRow === 'COA' || cleanRow === 'COAOFFICE' || cleanRow === 'COMMISSIONONAUDIT' || cleanRow === 'AUDIT') return 'COMMISSIONONAUDIT';
        if (cleanRow === 'TSD' || cleanRow === 'TSDOFFICE' || cleanRow === 'TECHNICALSERVICESDIVISION' || cleanRow === 'TECHNICALSERVICES' || cleanRow === 'TECHNICALSERVICESDEPARTMENT' || cleanRow === 'TECHNICALSERVICESDIV' || cleanRow === 'TECHNICAL' || cleanRow === 'TECHNICALSERVICESDEPT' || cleanRow === 'TECHNICALDEPT') return 'TECHNICALSERVICESDEPARTMENT';
        if (cleanRow === 'ISD' || cleanRow === 'ISDOFFICE' || cleanRow === 'INTERNALSERVICESDIVISION' || cleanRow === 'INTERNALSERVICES' || cleanRow === 'INTERNALSERVICESDEPARTMENT' || cleanRow === 'INTERNALSERVICESDIV' || cleanRow === 'INTERNAL' || cleanRow === 'INTERNALSERVICESDEPT' || cleanRow === 'INTERNALDEPT') return 'INTERNALSERVICESDEPARTMENT';
        if (cleanRow === 'FMD' || cleanRow === 'FMDOFFICE' || cleanRow === 'FINANCIALANDMANAGEMENTDIVISION' || cleanRow === 'FINANCIALANDMANAGEMENT' || cleanRow === 'FINANCIALMANAGEMENT' || cleanRow === 'FINANCIAL' || cleanRow === 'FINANCE' || cleanRow === 'FINANCEDIVISION' || cleanRow === 'FINANCIALDIVISION' || cleanRow === 'FINANCEMODULE') return 'FINANCIALANDMANAGEMENTDIVISION';
        if (cleanRow === 'VAD' || cleanRow === 'VADOFFICE' || cleanRow === 'VOLUNTARYARBITRATIONDIVISION' || cleanRow === 'VOLUNTARYARBITRATION' || cleanRow === 'VOLUNTARY') return 'VOLUNTARYARBITRATIONDIVISION';
        if (cleanRow === 'WRED' || cleanRow === 'WREDOFFICE' || cleanRow === 'WORKPLACERELATIONSENHANCEMENTDIVISION' || cleanRow === 'WORKPLACERELATIONSENHANCEMENT' || cleanRow === 'WORKPLACERELATIONS' || cleanRow === 'WORKPLACE') return 'WORKPLACERELATIONSENHANCEMENTDIVISION';
        
        return cleanRow;
    }

    const filtered = allAssets.filter(a => {
        const custodian = a.assigned_user ? a.assigned_user : null;
        const userName = custodian ? custodian.full_name : "";
        const custodianOffice = custodian ? custodian.office : ""; // This is Division in DB
        const custodianDeptRaw = custodian ? custodian.department : ""; // This is Department in DB

        const matchesSearch = (a.item_name || "").toLowerCase().includes(searchTerm) || 
                              (a.serial_number || "").toLowerCase().includes(searchTerm) ||
                              userName.toLowerCase().includes(searchTerm);

        const normDivision = normalizeOfficeDept(custodianOffice);
        const normDepartment = normalizeOfficeDept(custodianDeptRaw);

        const internalOffices = ['ADMINISTRATIVEDIVISION', 'INTERNALSERVICESDEPARTMENT', 'COMMISSIONONAUDIT', 'FINANCIALANDMANAGEMENTDIVISION', 'RESEARCHANDINFORMATIONDIVISION'];
        const technicalOffices = ['CONCILIATIONANDMEDIATIONDIVISION', 'TECHNICALSERVICESDEPARTMENT', 'OFFICEOFTHEEXECUTIVEDIRECTOR', 'VOLUNTARYARBITRATIONDIVISION', 'WORKPLACERELATIONSENHANCEMENTDIVISION'];

        // KEY FIX: Unassigned/spare assets (no custodian) always pass dept/division filters.
        // Spare assets belong to the region pool, not to any specific division.
        let matchesDept = departmentFilterValue === "" || !custodian;
        if (departmentFilterValue !== "" && custodian) {
            const cleanSearch = departmentFilterValue.replace(/[^A-Z0-9]/g, '').trim();
            if (cleanSearch === 'INTERNALSERVICESDEPARTMENT') {
                matchesDept = (normDepartment === 'INTERNALSERVICESDEPARTMENT') || internalOffices.includes(normDivision);
            } else if (cleanSearch === 'TECHNICALSERVICESDEPARTMENT') {
                matchesDept = (normDepartment === 'TECHNICALSERVICESDEPARTMENT') || technicalOffices.includes(normDivision);
            } else {
                matchesDept = (normDepartment === cleanSearch) || normDepartment.includes(cleanSearch);
            }
        }

        let matchesDiv = divisionFilterValue === "" || !custodian;
        if (divisionFilterValue !== "" && custodian) {
            const cleanSearch = divisionFilterValue.replace(/[^A-Z0-9]/g, '').trim();
            matchesDiv = (normDivision === cleanSearch) || normDivision.includes(cleanSearch);
        }

        const matchesCategory = categoryFilterValue === "" || a.category === categoryFilterValue;
        const matchesStatus = statusFilterValue === "" || a.status === statusFilterValue;

        // Supply admin sees ALL assets — bypass division/department filtering
        if (window.CMMS_IS_SUPPLY_ADMIN) {
            return matchesSearch && matchesCategory && matchesStatus;
        }

        return matchesSearch && matchesDiv && matchesDept && matchesCategory && matchesStatus;
    });

    // Client-side pagination
    currentPage = Math.min(currentPage, Math.ceil(filtered.length / perPage) || 1);
    const start = (currentPage - 1) * perPage;
    const pageItems = filtered.slice(start, start + perPage);

    renderInventoryTable(pageItems);
    renderPagination(filtered.length);
    updateInventorySummary(filtered);
}

function renderPagination(totalFiltered) {
    const container = document.getElementById("inventoryPagination");
    if (!container) return;

    const totalPages = Math.ceil(totalFiltered / perPage) || 1;
    if (totalPages <= 1) {
        container.innerHTML = "";
        return;
    }

    let html = '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;">';
    html += `<span style="font-size:12px;color:#64748b;">Showing ${Math.min((currentPage-1)*perPage+1, totalFiltered)}–${Math.min(currentPage*perPage, totalFiltered)} of ${totalFiltered}</span>`;
    html += '<div style="display:flex;gap:4px;">';

    // Prev button
    html += `<button onclick="goToPage(${currentPage - 1})" style="padding:5px 10px;border:1px solid #cbd5e1;border-radius:4px;background:${currentPage <= 1 ? '#f1f5f9' : 'white'};color:${currentPage <= 1 ? '#94a3b8' : '#1e293b'};cursor:${currentPage <= 1 ? 'default' : 'pointer'};font-size:12px;font-weight:700;" ${currentPage <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button>`;

    // Page numbers
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);
    if (startPage > 1) {
        html += `<button onclick="goToPage(1)" style="padding:5px 10px;border:1px solid #cbd5e1;border-radius:4px;background:white;color:#1e293b;cursor:pointer;font-size:12px;font-weight:700;">1</button>`;
        if (startPage > 2) html += '<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>';
    }
    for (let i = startPage; i <= endPage; i++) {
        const active = i === currentPage;
        html += `<button onclick="goToPage(${i})" style="padding:5px 10px;border:1px solid ${active ? '#0038A8' : '#cbd5e1'};border-radius:4px;background:${active ? '#0038A8' : 'white'};color:${active ? 'white' : '#1e293b'};cursor:pointer;font-size:12px;font-weight:700;">${i}</button>`;
    }
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += '<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>';
        html += `<button onclick="goToPage(${totalPages})" style="padding:5px 10px;border:1px solid #cbd5e1;border-radius:4px;background:white;color:#1e293b;cursor:pointer;font-size:12px;font-weight:700;">${totalPages}</button>`;
    }

    // Next button
    html += `<button onclick="goToPage(${currentPage + 1})" style="padding:5px 10px;border:1px solid #cbd5e1;border-radius:4px;background:${currentPage >= totalPages ? '#f1f5f9' : 'white'};color:${currentPage >= totalPages ? '#94a3b8' : '#1e293b'};cursor:${currentPage >= totalPages ? 'default' : 'pointer'};font-size:12px;font-weight:700;" ${currentPage >= totalPages ? 'disabled' : ''}>Next &rsaquo;</button>`;

    html += '</div></div>';
    container.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    filterInventory();
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
    if (el) el.value = (value !== undefined && value !== null) ? value : defaultVal;
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
    const asset = allAssets.find(a => (a.asset_id || a.id) == id);
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
                confirmButtonText: 'OK',
                showConfirmButton: true,
                showCancelButton: false
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

function openDisposalFromList(assetId) {
    const prefix = window.CMMS_INVENTORY_DETAIL_PREFIX || '/inventory';
    window.location.href = `${prefix}/${assetId}/detail#disposal`;
}

function openTransferModal(assetId) {
    console.log("Opening transfer modal for asset ID: ", assetId);
    const asset = allAssets.find(a => (a.asset_id || a.id) == assetId);
    if (!asset) {
        console.error("Asset not found in allAssets array!");
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

    const asset = allAssets.find(a => a.asset_id == assetId);
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

// Global modal close — click outside overlay to dismiss
document.addEventListener("click", function(event) {
    const assetModal = document.getElementById("assetModal");
    const historyModal = document.getElementById("assetHistoryModal");
    const transferModal = document.getElementById("transferModal");
    if (event.target === assetModal) closeAssetModal();
    if (event.target === historyModal) closeHistoryModal();
    if (event.target === transferModal) closeTransferModal();
});
