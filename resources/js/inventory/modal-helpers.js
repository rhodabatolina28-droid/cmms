import { INVENTORY_BRANCH_MAP } from './config.js';

/**
 * Populate the Branch dropdown in the modal based on selected region.
 * @param {string} region
 * @param {boolean} autoFetch - if true, also calls fetchFilteredUsers() after populating
 */
export function updateModalBranchDropdown(region, autoFetch = true) {
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
export async function fetchFilteredUsers() {
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