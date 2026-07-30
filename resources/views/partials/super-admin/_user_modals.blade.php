<!-- EDIT USER MODAL -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h4 class="modal-title">Edit System Account</h4>
                <p style="margin: 3px 0 0; font-size: 12px; color: #64748b;">Update user information and role assignments</p>
            </div>
            <button type="button" class="close-btn" onclick="document.getElementById('editUserModal').style.display='none'">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="editUserForm">
            <div class="modal-body">
                <input type="hidden" name="user_id" id="editUserId">

                {{-- Personnel Information Section --}}
                <div style="margin-bottom: 18px;">
                    <div class="section-divider">Personnel Information</div>
                    <div class="form-group">
                        <label class="form-label-gov">Full Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="full_name" id="editFullName" class="form-input-gov" required placeholder="Enter complete name">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label-gov">Email Address <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="email" id="editEmail" class="form-input-gov" required placeholder="e.g. name@ncmb.gov.ph">
                    </div>
                </div>

                {{-- Role & Assignment Section --}}
                <div style="margin-bottom: 18px;">
                    <div class="section-divider">Role & Assignment</div>
                    <div class="form-group">
                        <label class="form-label-gov">System Role <span style="color:#ef4444;">*</span></label>
                        <select name="role" id="editUserRole" class="form-input-gov" required>
                            <option value="user">User</option>
                            <option value="admin">Division Admin</option>
                            <option value="supply_officer">Supply Officer (Administrative Div.)</option>
                            <option value="it">IT Personnel</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                        <p class="form-help">Supply Officer role is restricted to Administrative Division only.</p>
                    </div>
                    <div class="form-grid" style="margin-bottom:0;">
                        <div>
                            <label class="form-label-gov">Region</label>
                            <input type="text" name="region" id="editRegion" class="form-input-gov" readonly style="background:#f8fafc; color:#94a3b8;">
                        </div>
                        <div>
                            <label class="form-label-gov">Branch</label>
                            <input type="text" name="branch" id="editBranch" class="form-input-gov" readonly style="background:#f8fafc; color:#94a3b8;">
                        </div>
                    </div>
                </div>

                {{-- Department & Division Section --}}
                <div>
                    <div class="section-divider">Department & Division</div>
                    <div class="form-group">
                        <label class="form-label-gov">Department</label>
                        <select name="department" id="editUserDepartment" class="form-input-gov">
                            <option value="">None / Not Applicable</option>
                            <option value="INTERNAL SERVICES DEPARTMENT">Internal Services Dept.</option>
                            <option value="TECHNICAL SERVICES DEPARTMENT">Technical Services Dept.</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label-gov">Division / Office <span style="color:#ef4444;">*</span></label>
                        <select name="office" id="editUserOffice" class="form-input-gov" required>
                            <option value="">— Select Division / Office —</option>
                            <option value="RESEARCH AND INFORMATION DIVISION" data-dept="INTERNAL">Research & Information Div. (RID)</option>
                            <option value="ADMINISTRATIVE DIVISION" data-dept="INTERNAL">Administrative Division (AD)</option>
                            <option value="FINANCIAL AND MANAGEMENT DIVISION" data-dept="INTERNAL">Financial & Management Div. (FMD)</option>
                            <option value="COMMISSION ON AUDIT" data-dept="INTERNAL">Commission on Audit (COA)</option>
                            <option value="CONCILIATION AND MEDIATION DIVISION" data-dept="TECHNICAL">Conciliation & Mediation Div. (CMD)</option>
                            <option value="VOLUNTARY ARBITRATION DIVISION" data-dept="TECHNICAL">Voluntary Arbitration Div. (VAD)</option>
                            <option value="WORKPLACE RELATIONS ENHANCEMENT DIVISION" data-dept="TECHNICAL">Workplace Relations Enhancement Div. (WRED)</option>
                            <option value="OFFICE OF THE EXECUTIVE DIRECTOR" data-dept="TECHNICAL">Office of the Exec. Director (OED)</option>
                        </select>
                        <p class="form-help">Required — determines the user's scope in the system.</p>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-action-modern btn-cancel" onclick="document.getElementById('editUserModal').style.display='none'">
                    Discard
                </button>
                <button type="submit" class="btn-gov-primary btn-submit">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-card">
        <div class="modal-header">
            <h4 class="modal-title">Create New System Account</h4>
        </div>
        <form id="addUserForm">
            <div class="modal-body">
                {{-- Row 1: Full Name --}}
                <div class="form-group">
                    <label class="form-label-gov">Personnel Full Name</label>
                    <input type="text" name="full_name" class="form-input-gov" required placeholder="Enter complete name">
                </div>

                {{-- Row 2: Email --}}
                <div class="form-group">
                    <label class="form-label-gov">Official Email Address</label>
                    <input type="email" name="email" class="form-input-gov" required placeholder="e.g. name@ncmb.gov.ph">
                </div>

                {{-- Row 3: Role --}}
                <div class="form-group">
                    <label class="form-label-gov">System Role</label>
                    <select name="role" id="newUserRole" class="form-input-gov" required>
                        <option value="user">User</option>
                        <option value="admin">Division Admin</option>
                        <option value="supply_officer">Supply Officer / Admin (Administrative Div.)</option>
                        <option value="it">IT Personnel</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>

                {{-- Row 4: Region | Branch --}}
                <div class="form-grid">
                    <div>
                        <label class="form-label-gov">Region</label>
                        <input type="text" name="region" class="form-input-gov" placeholder="e.g. NCR, Region I, CAR" value="{{ Auth::user()->region }}" readonly>
                    </div>
                    <div>
                        <label class="form-label-gov">Branch</label>
                        <input type="text" name="branch" class="form-input-gov" placeholder="e.g. NCR Central Office" value="{{ Auth::user()->branch }}" readonly>
                    </div>
                </div>

                {{-- Row 5: Department | Division --}}
                <div class="form-grid">
                    <div>
                        <label class="form-label-gov">Department</label>
                        <select name="department" id="newUserDepartment" class="form-input-gov">
                            <option value="">None / Not Applicable</option>
                            <option value="INTERNAL SERVICES DEPARTMENT">Internal Services Dept.</option>
                            <option value="TECHNICAL SERVICES DEPARTMENT">Technical Services Dept.</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-gov">Division / Office <span style="color:#ef4444;">*</span></label>
                        <select name="office" id="newUserOffice" class="form-input-gov" required>
                            <option value="">— Select Division / Office —</option>
                            <option value="RESEARCH AND INFORMATION DIVISION" data-dept="INTERNAL">Research & Information Div. (RID)</option>
                            <option value="ADMINISTRATIVE DIVISION" data-dept="INTERNAL">Administrative Division (AD)</option>
                            <option value="FINANCIAL AND MANAGEMENT DIVISION" data-dept="INTERNAL">Financial & Management Div. (FMD)</option>
                            <option value="COMMISSION ON AUDIT" data-dept="INTERNAL">Commission on Audit (COA)</option>
                            <option value="CONCILIATION AND MEDIATION DIVISION" data-dept="TECHNICAL">Conciliation & Mediation Div. (CMD)</option>
                            <option value="VOLUNTARY ARBITRATION DIVISION" data-dept="TECHNICAL">Voluntary Arbitration Div. (VAD)</option>
                            <option value="WORKPLACE RELATIONS ENHANCEMENT DIVISION" data-dept="TECHNICAL">Workplace Relations Enhancement Div. (WRED)</option>
                            <option value="OFFICE OF THE EXECUTIVE DIRECTOR" data-dept="TECHNICAL">Office of the Exec. Director (OED)</option>
                        </select>
                        <p class="form-help">Required — determines the user's scope in the system.</p>
                    </div>
                </div>

                {{-- Row 6: Password --}}
                <div class="form-group-sm" style="position:relative;">
                    <label class="form-label-gov">Initial Access Password</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="password" name="password" id="newUserPassword" class="form-input-gov" required placeholder="Min. 8 characters" style="padding-right:40px;">
                        <button type="button" id="toggleNewUserPassword" class="btn-action-modern" style="position:absolute;right:22px;top:32px;border:none;background:none;padding:8px;cursor:pointer;font-size:16px;color:#64748b;" tabindex="-1">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <p class="form-help" style="margin-top:6px;">Password must be at least 8 characters, 1 uppercase letter, and 1 number.</p>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-action-modern close-modal-btn btn-cancel">Discard</button>
                <button type="submit" class="btn-gov-primary btn-submit">
                    <i class="fa-solid fa-save"></i> Save Account
                </button>
            </div>
        </form>
    </div>
</div>