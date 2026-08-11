<x-form-layout
    title="Preventive Maintenance Service Form"
    cssFile="maint-form.css"
    cspNonce="{{ $cspNonce }}"
>
@slot('extraHead')
    <style nonce="{{ $cspNonce }}">
        @media (max-width: 767px) {
            #pmForm .grid-table, #pmForm table.grid-table, #pmForm .grid-table tr,
            #pmForm .grid-table tbody, #pmForm .grid-table td,
            #pmForm table[class*="grid"] { display: block; width: 100%; box-sizing: border-box; }
            #pmForm .left-col, #pmForm .right-col,
            #pmForm td.left-col, #pmForm td.right-col {
                display: block; width: 100% !important; padding: 12px 4px; box-sizing: border-box;
            }
            #pmForm .input-row { display: flex; flex-direction: column; gap: 14px; }
            #pmForm .input-row .input-group { width: 100%; }
            #pmForm .header-no-row { flex-direction: column; align-items: flex-start; gap: 12px; }
            #pmForm .sig-container-minimal canvas, #pmForm .signature-canvas {
                width: 100% !important; max-width: 100%; height: auto; aspect-ratio: 350/64;
            }
            
            /* UX Improvements: Touch Targets and Font Size */
            #pmForm .minimal-input, #pmForm input.minimal-input,
            #pmForm select.minimal-input, #pmForm textarea.minimal-input,
            .assign-select, .no-input { 
                width: 100%; 
                box-sizing: border-box; 
                min-height: 48px !important; 
                font-size: 15px !important; 
                padding: 12px !important; 
            }
            #pmForm .device-info-grid input[type="text"], #pmForm .device-info-grid select {
                width: 100%;
                min-height: 44px !important;
                font-size: 15px !important;
                padding: 8px !important;
                border: 1px solid #c7d4ea !important;
                border-radius: 4px !important;
                margin-top: 6px;
            }
            
            #pmForm .checkbox-group-minimal { flex-wrap: wrap; gap: 10px; }
            #pmForm .checkbox-group-minimal label { 
                flex: 1 1 100%; 
                min-height: 44px;
                padding: 8px 0;
                font-size: 14px !important;
            }
            #pmForm input[type="checkbox"] {
                transform: scale(1.3);
                margin-right: 12px;
            }

            #pmForm .sig-underline { width: 100%; margin-top: 15px; }
            
            #pmForm button[type="submit"], #pmForm button[type="button"],
            .assign-btn, .btn-clear-sig-minimal, .toggle-edit-btn, .pdf-link, .pdf-download-link, .btn-back-link { 
                width: 100% !important; 
                justify-content: center; 
                text-align: center;
                min-height: 48px !important;
                font-size: 15px !important;
                display: flex;
                align-items: center;
                margin-top: 6px;
            }
            
            #endUserSection .input-row, #suggestionSection { display: block; width: 100%; }
            #pmForm .printed-name-input { width: 100%; box-sizing: border-box; min-height: 48px; font-size: 15px !important; }
            #pmForm .label-cell { white-space: normal; padding-bottom: 4px; border-bottom: none !important; font-size: 12px !important; }
            #pmForm .device-info-grid td { display: block; width: 100%; border: none !important; border-bottom: 1px solid #e2e8f0 !important; padding: 12px 8px !important; height: auto !important; }
            
            #pmForm #adminControls { flex-direction: column; align-items: stretch; gap: 12px; width: 100%; margin-top: 10px; }
            #pmForm #adminControls a, #pmForm #adminControls button { width: 100%; justify-content: center; text-align: center; }
            .bond-paper { padding: 16px 12px; }
            .form-header-img img { max-width: 100%; height: auto; }
            
            .assign-flex { flex-direction: column; align-items: stretch; gap: 10px; }
        }
        .assign-panel { background: #eff6ff; border: 2px solid #93c5fd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .assign-panel-label { font-size: 11px; font-weight: 800; color: #1e40af; text-transform: uppercase; margin-bottom: 6px; }
        .assign-panel-text { margin: 0 0 12px; font-size: 13px; color: #334155; }
        .assign-flex { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .assign-select { flex: 1; min-width: 260px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
        .assign-btn { background: #0038A8; color: white; border: none; padding: 10px 20px; border-radius: 4px; font-weight: 700; cursor: pointer; }
        .assign-current { margin: 12px 0 0; font-size: 13px; }
        .admin-controls { align-items: center; gap: 10px; }
        .pdf-link { padding: 7px 16px; background: #059669; color: #fff; border-radius: 4px; font-size: 0.72rem; font-weight: 700; text-decoration: none; text-transform: uppercase; letter-spacing: 0.05em; }
        .toggle-edit-btn { background: #dc3545; color: white; }
        .grid-table-noborder { border-top: none; }
        .input-row-mt { margin-top: 15px; }
        .input-group-flex { flex: 1.2; }
        .btn-group.sticky-action-bar { margin-top: 30px; }
        .checkbox-group-mt { margin-top: 10px; }
        .col-pad-none { padding: 0; border: none; }
        .col-pad-vtop { padding: 0; vertical-align: top; border-left: 1.5px solid #334155; }
        .table-full { width: 100%; border-collapse: collapse; }
        .select-bold-blue { font-weight: 700; color: #2563eb; }
        .fw-bold-gray { font-weight: 700; color: #1e293b; }
        .pdf-download-link { display: inline-flex; align-items: center; gap: 6px; padding: 13px 28px; background: #1e293b; color: #fff; border-radius: 5px; text-decoration: none; font-weight: 800; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; }
    </style>
    <script nonce="{{ $cspNonce }}" src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</slot>

<div class="bond-paper">
    <!-- HEADER IMAGE -->
    <div class="form-header-img">
        <img src="{{ asset('images/pmsf-banner.png') }}" alt="NCMB Preventive Maintenance Banner" class="banner-full">
    </div>

        @if(!empty($canAssignIt) && $request && Auth::user()->role === 'super_admin' && (!$request->assigned_to || (int)$request->assigned_to !== (int)Auth::user()->id))
        <div id="assignItPanel" class="assign-panel">
            <div class="assign-panel-label">
                <i class="fa-solid fa-user-gear"></i> Assign IT Personnel (PM)
            </div>
            <p class="assign-panel-text">
                Super Admin: Assign IT to perform preventive maintenance work.
            </p>
            <div class="assign-flex">
                <select id="assignItSelect" class="assign-select">
                    <option value="">— Unassigned —</option>
                    @foreach($itPersonnel ?? [] as $it)
                        <option value="{{ $it->id }}" {{ (int) $request->assigned_to === (int) $it->id ? 'selected' : '' }}>{{ $it->full_name }}</option>
                    @endforeach
                    @if(!empty($canSelfAssign))
                        <option value="{{ Auth::user()->id }}" {{ (int) $request->assigned_to === (int) Auth::user()->id ? 'selected' : '' }}>
                            [SELF] I will handle this myself
                        </option>
                    @endif
                </select>
                <button type="button" id="assignItBtn" class="assign-btn">Save Assignment</button>
            </div>
            @if($request->assignedTo)
                <p class="assign-current">Currently assigned: <strong>{{ $request->assignedTo->full_name }}</strong></p>
            @endif
        </div>
        @endif





        <form id="pmForm" action="{{ $request ? route('maintenance.update', $request->id) : route('maintenance.store') }}" method="POST" novalidate>
            @csrf
            @if($request) 
                @method('PUT') 
                <input type="hidden" name="last_updated_at" value="{{ $request->updated_at }}">
            @endif

            @php
                $user = Auth::user();
                $isSuperAdmin = $user->role === 'super_admin';
                $isIt = $user->role === 'it';
                $isAdmin = $user->isAdmin() || $isSuperAdmin || $isIt; // Fix: IT and SuperAdmin can edit technician parts
                $isUser = !$isAdmin && Auth::user()->role === 'user';
                $canEditEndUser = $canEditEndUser ?? false;
                $endUserSectionDisabled = !$canEditEndUser;
                $endUserFieldsReadonly = !$canEditEndUser;
            @endphp

            <div class="header-no-row">
                <span class="form-no-label">Form No.: <input type="text" name="form_no" class="no-input" value="{{ $maintenance->form_no ?? '' }}" placeholder="0000" readonly></span>
                <div id="adminControls" class="admin-controls {{ ($request && (Auth::user()->isAdmin() || Auth::user()->isIt())) ? '' : 'hidden' }}">
                    @if($isAdmin)
                    <button type="button" id="enableEditBtn" class="toggle-edit-btn">Switch to View Only</button>
                    @endif
                </div>
            </div>

            <!-- COMPACT & PANTAY GRID LAYOUT -->
            @include('partials.maintenance._technician_section')

            @include('partials.maintenance._end_user_section')

            @include('partials.maintenance._pm_device_info')

            @include('partials.maintenance._pm_checklist')

            <div class="form-actions-minimal sticky-action-bar">
                @if(!$viewMode)
                    <button type="submit" class="btn-submit-minimal" id="pmSubmitBtn">
                        {{ $request ? ' Update Maintenance Record' : ' Submit Maintenance Form' }}
                    </button>
                @endif
                @if($request && $request->status === 'Completed')
                    <a href="{{ route('maintenance.pdf', $request->id) }}" target="_blank"
                       class="pdf-download-link">
                        🖨️ Print / Download PDF
                    </a>
                @endif
                @if($request && $request->status === 'Completed' && ($maintenance->for_disposal ?? '') === 'YES')
                    <a href="{{ route('maintenance.disposal-tag', $request->id) }}" target="_blank"
                       class="pdf-download-link" style="background:#dc2626; margin-left:10px;">
                        🗑️ Print Disposal Tag
                    </a>
                @endif
            </div>
            @php
                $fromAssetId = request()->query('from_asset');
                $prevUrl = (string) url()->previous();
                $fromInventory = $fromAssetId || ($prevUrl !== '' && str_contains($prevUrl, 'inventory'));
            @endphp
            @if($fromAssetId)
                @php
                    $detailRoute = Auth::user()->canProcessSupply()
                        ? route('inventory.detail', $fromAssetId)
                        : route('super_admin.inventory.detail', $fromAssetId);
                @endphp
                <a href="{{ $detailRoute }}" class="btn-back-link">← Back to Asset Profile</a>
            @elseif($fromInventory && isset($request) && $request && $request->linked_asset_id)
                @php
                    $assetId = $request->linked_asset_id;
                    $detailRoute = Auth::user()->canProcessSupply()
                        ? route('inventory.detail', $assetId)
                        : route('super_admin.inventory.detail', $assetId);
                @endphp
                <a href="{{ $detailRoute }}" class="btn-back-link">← Back to Asset Profile</a>
            @elseif(isset($request) && $request && $request->is_auto_generated)
                @if(Auth::user()->role === 'it')
                <a href="{{ route('pm.tasks') }}" class="btn-back-link">← Back to PM Tasks</a>
                @else
                <a href="{{ route('pm-schedules.orders') }}" class="btn-back-link">← Back to PM Work Orders</a>
                @endif
            @else
            <a href="{{ route('maintenance.index') }}" class="btn-back-link">← Back to Maintenance List</a>
            @endif

        </form>
    </div>

    @include('partials.maintenance._pm_scripts')
</x-form-layout>
