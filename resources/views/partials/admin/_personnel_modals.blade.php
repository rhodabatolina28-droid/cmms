<!-- Add Personnel Modal -->
<div class="modal-overlay" id="addPersonnelModal">
    <div class="modal-card">
        <div class="modal-header">
            <h4 class="modal-title">Register New Personnel</h4>
        </div>
        <form id="addPersonnelForm">
            @csrf
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-grid-full">
                        <label class="form-label-gov">Full Legal Name</label>
                        <input type="text" name="full_name" required class="form-input-gov" placeholder="Juan Dela Cruz">
                    </div>
                    <div class="form-grid-full">
                        <label class="form-label-gov">Work Email Address</label>
                        <input type="email" name="email" required class="form-input-gov" placeholder="juan@ncmb.gov.ph">
                    </div>
                    <div>
                        <label class="form-label-gov">Position / Rank</label>
                        <input type="text" name="position" class="form-input-gov" placeholder="ICT Officer I">
                    </div>
                    @if(Auth::user()->office)
                    <div>
                        <label class="form-label-gov">Division / Office</label>
                        <input type="text" value="{{ Auth::user()->office }}" class="form-input-gov" disabled>
                        <input type="hidden" name="office" value="{{ Auth::user()->office }}">
                    </div>
                    @else
                    <div>
                        <label class="form-label-gov">Division / Office</label>
                        <select name="office" class="form-input-gov">
                            <option value="">None / Not Applicable</option>
                            <option value="RESEARCH AND INFORMATION DIVISION">RID</option>
                            <option value="ADMINISTRATIVE DIVISION">AD</option>
                            <option value="FINANCIAL AND MANAGEMENT DIVISION">FMD</option>
                            <option value="COMMISSION ON AUDIT">COA</option>
                            <option value="CONCILIATION AND MEDIATION DIVISION">CMD</option>
                            <option value="VOLUNTARY ARBITRATION DIVISION">VAD</option>
                            <option value="WORKPLACE RELATIONS ENHANCEMENT DIVISION">WRED</option>
                            <option value="OFFICE OF THE EXECUTIVE DIRECTOR">OED</option>
                        </select>
                    </div>
                    @endif
                    @if(Auth::user()->department)
                    <input type="hidden" name="department" value="{{ Auth::user()->department }}">
                    @else
                    <div>
                        <label class="form-label-gov">Department</label>
                        <select name="department" class="form-input-gov">
                            <option value="">None</option>
                            <option value="INTERNAL SERVICES DEPARTMENT">Internal Services</option>
                            <option value="TECHNICAL SERVICES DEPARTMENT">Technical Services</option>
                            <option value="COMMISSION ON AUDIT">COA</option>
                        </select>
                    </div>
                    @endif
                    <div>
                        <label class="form-label-gov">System Role</label>
                        <select name="role" required class="form-input-gov">
                            <option value="user">Regular User</option>
                            <option value="admin">Division Admin</option>
                            <option value="it">IT Personnel</option>
                        </select>
                    </div>
                    <div style="position:relative;">
                        <label class="form-label-gov">Temporary Password</label>
                        <div style="display:flex;align-items:center;">
                            <input type="password" name="password" id="personnelPassword" required class="form-input-gov" placeholder="Minimum 6 characters" minlength="6" style="padding-right:40px;width:100%;">
                            <button type="button" id="togglePersonnelPassword" style="position:absolute;right:10px;top:32px;border:none;background:none;padding:8px;cursor:pointer;font-size:16px;color:#64748b;" tabindex="-1">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-view-modern close-add-personnel-btn btn-cancel-lg">Cancel</button>
                <button type="submit" class="btn-save-solid">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Personnel Details Modal -->
<div class="modal-overlay" id="personnelModal">
    <div class="modal-card modal-card-lg">
        <div class="modal-header">
            <h4 class="modal-title">Personnel Profile & Activity</h4>
        </div>
        <div class="modal-body">
            <div id="modalLoading" class="loading-spinner">
                <i class="fa-solid fa-circle-notch fa-spin spinner-icon"></i>
                <p class="loading-text">Loading profile data...</p>
            </div>

            <div id="modalContent" class="d-none">
                <!-- Basic Info Grid -->
                <div class="det-grid">
                    <div>
                        <label class="form-label-gov">Full Name</label>
                        <p id="detName" class="det-value">-</p>
                    </div>
                    <div>
                        <label class="form-label-gov">Email Address</label>
                        <p id="detEmail" class="det-value-sm">-</p>
                    </div>
 
                    <div>
                        <label class="form-label-gov">Position</label>
                        <p id="detPosition" class="det-value-sm">-</p>
                    </div>
                    <div>
                        <label class="form-label-gov">Office</label>
                        <p id="detOffice" class="det-value-sm">-</p>
                    </div>
                    @if(Auth::user()->department)
                    <div class="det-hide">
                        <label class="form-label-gov">Department</label>
                        <select id="detDepartmentSelect" class="form-input-gov">
                            <option value="{{ Auth::user()->department }}">{{ Auth::user()->department }}</option>
                        </select>
                    </div>
                    @else
                    <div>
                        <label class="form-label-gov">Department</label>
                        <div class="det-flex-group">
                            <select id="detDepartmentSelect" class="form-input-gov dept-select" disabled>
                                <option value="">None</option>
                                <option value="INTERNAL SERVICES DEPARTMENT">INTERNAL SERVICES</option>
                                <option value="TECHNICAL SERVICES DEPARTMENT">TECHNICAL SERVICES</option>
                                <option value="COMMISSION ON AUDIT">COA</option>
                            </select>
                        </div>
                    </div>
                    @endif
                    <div>
                        <label class="form-label-gov">Account Status</label>
                        <div class="status-toggle-group">
                            <span id="detStatusBadge" class="status-pill">-</span>
                            <button id="btnToggleStatus" class="btn-view-modern toggle-btn-sm">Toggle</button>
                        </div>
                    </div>
                </div>

                <!-- Assigned Assets -->
                <div class="form-group-lg">
                    <h5 class="section-title">
                        <i class="fa-solid fa-laptop section-icon"></i> Currently Assigned Assets
                    </h5>
                    <div id="detAssets" class="assets-scroll">
                    </div>
                </div>

                <!-- Stats and History -->
                <div>
                    <h5 class="section-title">
                        <i class="fa-solid fa-clipboard-list section-icon"></i> ICT Request Overview
                    </h5>
                    <div id="detStats" class="stats-grid">
                    </div>
                    <div id="detRequests" class="requests-scroll">
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-view-modern close-view-modal-btn btn-close-lg">Close Window</button>
        </div>
    </div>
</div>
