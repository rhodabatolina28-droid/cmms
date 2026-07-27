<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Preventive Maintenance Service Form | NCMB</title>
    <link rel="stylesheet" href="{{ asset('css/maint-form.css') }}?v={{ filemtime(public_path('css/maint-form.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-responsive.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .checkbox-group-mt { margin-top: 10px; }
        .col-pad-none { padding: 0; border: none; }
        .col-pad-vtop { padding: 0; vertical-align: top; border-left: 1.5px solid #334155; }
        .table-full { width: 100%; border-collapse: collapse; }
        .select-bold-blue { font-weight: 700; color: #2563eb; }
        .fw-bold-gray { font-weight: 700; color: #1e293b; }
        .pdf-download-link { display: inline-flex; align-items: center; gap: 6px; padding: 13px 28px; background: #1e293b; color: #fff; border-radius: 5px; text-decoration: none; font-weight: 800; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; }
    </style>
    <script nonce="{{ $cspNonce }}" src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
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
            <div id="technicianSection" class="{{ (!$isAdmin || $viewMode) ? 'disabled-section' : '' }}">
                <table class="grid-table">
                    <!-- TECHNICIAN SECTION -->
                    <tr>
                        <td class="left-col" width="65%">
                            <div class="section-label">PREVENTIVE MAINTENANCE TECHNICIAN</div>
                            <div class="input-group">
                                <label>FULL NAME</label>
                                <input type="text" name="technician_name" class="minimal-input" value="{{ $maintenance->technician_name ?? ($isAdmin ? Auth::user()->full_name : '') }}" placeholder="Enter Technician Name">
                            </div>
                            <div class="input-row">
                                <div class="input-group">
                                    <label>SIGNATURE</label>
                                    <div class="sig-container-minimal">
                                        <canvas id="technicianSignatureCanvas" class="signature-canvas" width="300" height="64"></canvas>
                                        <input type="hidden" id="technicianSignature" name="technicianSignature" value="{{ $maintenance->technician_signature ?? '' }}">
                                        <button type="button" class="btn-clear-sig-minimal" data-canvas="technicianSignatureCanvas" data-input="technicianSignature">Clear</button>
                                    </div>
                                    <div class="sig-underline"></div>
                                </div>
                                <div class="input-group">
                                    <label>DATE</label>
                                    <input type="date" name="technician_date" class="minimal-input" value="{{ isset($maintenance->technician_date) ? \Carbon\Carbon::parse($maintenance->technician_date)->format('Y-m-d') : date('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="input-group">
                                <label>DEVICE PROBLEM & ISSUES ENCOUNTERED</label>
                                <textarea name="problem_description" rows="4" class="minimal-input">{{ $maintenance->problem_description ?? '' }}</textarea>
                            </div>
                        </td>
                        <td class="right-col" width="35%">
                            <div id="analysisSection" class="{{ !$isAdmin ? 'disabled-section' : '' }}">
                                <div class="section-label">TECHNICIAN ANALYSIS</div>
                                <label class="inner-label">Diagnosis of the problem?</label>
                                <textarea name="diagnosis" class="full-height-area minimal-input">{{ $maintenance->diagnosis ?? '' }}</textarea>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- END USER + SUGGESTION/RECOMMENDATION SECTION -->
            <table class="grid-table grid-table-noborder">
                <tr>
                    <td class="left-col">
                        <div id="endUserSection" class="{{ $endUserSectionDisabled ? 'disabled-section' : '' }}">
                            <div class="section-label">END USER</div>
                            <div class="input-group">
                                <label>FULL NAME</label>
                                <input type="text" name="end_user_name" class="minimal-input" value="{{ $maintenance->end_user_name ?? Auth::user()->full_name }}" {{ $endUserFieldsReadonly ? 'disabled' : '' }}>
                            </div>
                            <div class="input-group">
                                <label>DIVISION</label>
                                <input type="text" name="end_user_division" class="minimal-input" value="{{ $maintenance->end_user_division ?? Auth::user()->office ?? '' }}" placeholder="Enter Division" {{ $endUserFieldsReadonly ? 'disabled' : '' }}>
                            </div>

                            @php
                                // Build assets map for JS auto-fill
                                $assetsMap = [];
                                foreach ($myAssets ?? [] as $a) {
                                    $assetsMap[$a->asset_id] = [
                                        'item_name'     => $a->item_name,
                                        'serial_number' => $a->serial_number,
                                        'category'      => strtolower($a->category ?? ''),
                                        'specs'         => $a->specifications ?? [],
                                    ];
                                }
                                $linkedPmAsset = $linkedPmAsset ?? null;
                            @endphp

                            <div class="note-text-minimal">
                                Note: End-user should have backed up their documents prior to the conduct of PM.
                                The technician will not be liable for any data loss or breach of data privacy.
                            </div>
                            <div class="input-row input-row-mt">
                                <div class="input-group input-group-flex">
                                    <label>END-USER SIGNATURE OVER PRINTED NAME</label>
                                    <div class="sig-container-minimal">
                                        @php $sigPath = !empty($maintenance->end_user_signature) ? (str_starts_with($maintenance->end_user_signature, 'http') ? $maintenance->end_user_signature : Storage::url($maintenance->end_user_signature)) : ''; @endphp
                                        @if(!empty($maintenance->end_user_signature) && empty($reSignMode))
                                            <img src="{{ $sigPath }}" alt="End-User Signature" class="signature-preview-img">
                                            <input type="hidden" id="endUserSignature" name="endUserSignature" value="{{ $maintenance->end_user_signature }}">
                                            @if(!$endUserFieldsReadonly)
                                                <button type="button" class="btn-clear-sig-minimal" id="resignBtn">Re-sign</button>
                                            @endif
                                        @else
                                            <canvas id="endUserSignatureCanvas" class="signature-canvas" width="350" height="64"></canvas>
                                            <input type="hidden" id="endUserSignature" name="endUserSignature" value="">
                                            @if(!$endUserFieldsReadonly)
                                                <button type="button" class="btn-clear-sig-minimal" data-canvas="endUserSignatureCanvas" data-input="endUserSignature">Clear</button>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="sig-underline"></div>
                                    <input type="text"
                                        name="end_user_printed_name"
                                        class="printed-name-input"
                                        placeholder="Printed Name"
                                        value="{{ $maintenance->end_user_printed_name ?? ($endUser->full_name ?? '') }}"
                                        {{ $endUserFieldsReadonly ? 'disabled' : '' }}>
                                    <span class="sig-caption">Signature over Printed Name</span>
                                </div>
                                <div class="input-group">
                                    <label>DATE SIGNED</label>
                                    <input type="date" name="end_user_signature_date" class="minimal-input" value="{{ isset($maintenance->end_user_signature_date) ? \Carbon\Carbon::parse($maintenance->end_user_signature_date)->format('Y-m-d') : date('Y-m-d') }}" {{ $endUserFieldsReadonly ? 'disabled' : '' }}>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="right-col">
                        <div id="suggestionSection" class="{{ (!$isAdmin || $viewMode) ? 'disabled-section' : '' }}">
                            <div class="section-label">SUGGESTION/RECOMMENDATION</div>
                            <div class="checkbox-group-minimal">
                                <label><input type="checkbox" name="for_disposal" id="forDisposalCheck" value="YES" {{ ($maintenance->for_disposal ?? '') == 'YES' ? 'checked' : '' }}> FOR DISPOSAL</label>
                            </div>
                            <div id="disposalAssetSelector" class="input-group" style="display: {{ ($maintenance->for_disposal ?? '') == 'YES' ? 'block' : 'none' }};">
                                <label>SELECT ASSET TO DISPOSE</label>
                                <select name="disposal_asset_id" class="minimal-input">
                                    <option value="">-- Select Asset --</option>
                                    @foreach($myAssets ?? [] as $asset)
                                        <option value="{{ $asset->asset_id }}" 
                                            data-name="{{ $asset->item_name }}" 
                                            data-sn="{{ $asset->serial_number }}"
                                            {{ ($maintenance->disposal_asset_id ?? '') == $asset->asset_id ? 'selected' : '' }}>
                                            {{ $asset->item_name }} ({{ $asset->serial_number }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="hint">Select the specific asset to be disposed from this user's assigned equipment.</div>
                            </div>
                            <div class="input-group">
                                <label>REASON FOR DISPOSAL</label>
                                <textarea name="disposal_reason" rows="3" class="minimal-input">{{ $maintenance->disposal_reason ?? '' }}</textarea>
                            </div>
                            <div class="checkbox-group-minimal checkbox-group-mt">
                                <label><input type="checkbox" name="for_repair" value="YES" {{ ($maintenance->for_repair ?? '') == 'YES' ? 'checked' : '' }}> FOR REPAIR</label>
                            </div>
                            <div class="input-group">
                                <label>PARTS FOR REPAIR/REPLACEMENT</label>
                                <textarea name="repair_parts" rows="3" class="minimal-input">{{ $maintenance->repair_parts ?? '' }}</textarea>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="section-bar-minimal">DEVICE INFORMATION</div>

            <div id="deviceInfoSection" class="{{ (!$isAdmin || $viewMode) ? 'disabled-section' : '' }}">
                <table class="device-info-grid">
                    <tr>
                        <!-- LEFT SIDE: DEVICE LIST -->
                        <td class="col-left col-pad-none">
                            <table class="table-full">
                                <tr>
                                    <td class="label-cell">Desktop Brand:</td>
                                    <td>
                                        <input type="text" name="desktopBrand" value="{{ $maintenance->desktop_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="desktopModel" value="{{ $maintenance->desktop_model ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">DESKTOP PNO:</td>
                                    <td colspan="3"><input type="text" name="desktopPno" value="{{ $maintenance->desktop_pno ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Computer Name:</td>
                                    <td colspan="3"><input type="text" name="computerName" value="{{ $maintenance->desktop_computer_name ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Number of Monitors:</td>
                                    <td colspan="3">
                                        <select id="monitorCountSelect" class="minimal-input select-bold-blue">
                                            <option value="1" {{ empty($maintenance->monitor2_brand) && empty($maintenance->monitor2_pno) ? 'selected' : '' }}>1 Monitor</option>
                                            <option value="2" {{ !empty($maintenance->monitor2_brand) || !empty($maintenance->monitor2_pno) ? 'selected' : '' }}>2 Monitors</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">MONITOR-1 PNO:</td>
                                    <td colspan="3"><input type="text" name="monitor1Pno" value="{{ $maintenance->monitor1_pno ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Monitor Brand:</td>
                                    <td>
                                        <input type="text" name="monitor1Brand" value="{{ $maintenance->monitor1_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="monitor1Model" value="{{ $maintenance->monitor1_model ?? '' }}"></td>
                                </tr>
                                <tr class="monitor-2-row">
                                    <td class="label-cell">MONITOR-2 PNO:</td>
                                    <td colspan="3"><input type="text" name="monitor2Pno" value="{{ $maintenance->monitor2_pno ?? '' }}"></td>
                                </tr>
                                <tr class="monitor-2-row">
                                    <td class="label-cell">Monitor Brand:</td>
                                    <td>
                                        <input type="text" name="monitor2Brand" value="{{ $maintenance->monitor2_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="monitor2Model" value="{{ $maintenance->monitor2_model ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Number of Printers:</td>
                                    <td colspan="3">
                                        <select id="printerCountSelect" class="minimal-input select-bold-blue">
                                            <option value="1" {{ empty($maintenance->printer2_brand) && empty($maintenance->printer2_pno) ? 'selected' : '' }}>1 Printer</option>
                                            <option value="2" {{ !empty($maintenance->printer2_brand) || !empty($maintenance->printer2_pno) ? 'selected' : '' }}>2 Printers</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">PRINTER-1 PNO:</td>
                                    <td colspan="3"><input type="text" name="printer1Pno" value="{{ $maintenance->printer1_pno ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Printer Brand:</td>
                                    <td>
                                        <input type="text" name="printer1Brand" value="{{ $maintenance->printer1_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="printer1Model" value="{{ $maintenance->printer1_model ?? '' }}"></td>
                                </tr>
                                <tr class="printer-2-row">
                                    <td class="label-cell">PRINTER-2 PNO:</td>
                                    <td colspan="3"><input type="text" name="printer2Pno" value="{{ $maintenance->printer2_pno ?? '' }}"></td>
                                </tr>
                                <tr class="printer-2-row">
                                    <td class="label-cell">Printer Brand:</td>
                                    <td>
                                        <input type="text" name="printer2Brand" value="{{ $maintenance->printer2_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="printer2Model" value="{{ $maintenance->printer2_model ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">UPS PNO:</td>
                                    <td colspan="3"><input type="text" name="upsPno" value="{{ $maintenance->ups_pno ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">UPS Brand:</td>
                                    <td>
                                        <input type="text" name="upsBrand" value="{{ $maintenance->ups_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="upsModel" value="{{ $maintenance->ups_model ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">SCANNER PNO:</td>
                                    <td colspan="3"><input type="text" name="scannerPno" value="{{ $maintenance->scanner_pno ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Scanner Brand:</td>
                                    <td>
                                        <input type="text" name="scannerBrand" value="{{ $maintenance->scanner_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="scannerModel" value="{{ $maintenance->scanner_model ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">LAPTOP PNO:</td>
                                    <td colspan="3"><input type="text" name="laptopPno" value="{{ $maintenance->laptop_pno ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Laptop Brand:</td>
                                    <td>
                                        <input type="text" name="laptopBrand" value="{{ $maintenance->laptop_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="laptopModel" value="{{ $maintenance->laptop_model ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Computer Name:</td>
                                    <td colspan="3"><input type="text" name="laptopComputerName" value="{{ $maintenance->laptop_computer_name ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">WebCam Brand:</td>
                                    <td>
                                        <input type="text" name="webcamBrand" value="{{ $maintenance->webcam_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="webcamModel" value="{{ $maintenance->webcam_model ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">WEBCAM PNO:</td>
                                    <td colspan="3"><input type="text" name="webcamPno" value="{{ $maintenance->webcam_pno ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Speakers Brand:</td>
                                    <td>
                                        <input type="text" name="speakersBrand" value="{{ $maintenance->speakers_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="speakersModel" value="{{ $maintenance->speakers_model ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">SPEAKERS PNO:</td>
                                    <td colspan="3"><input type="text" name="speakersPno" value="{{ $maintenance->speakers_pno ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Earphone Brand:</td>
                                    <td>
                                        <input type="text" name="earphoneBrand" value="{{ $maintenance->earphone_brand ?? '' }}">
                                    </td>
                                    <td class="label-cell">Model:</td>
                                    <td><input type="text" name="earphoneModel" value="{{ $maintenance->earphone_model ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Other Equipment:</td>
                                    <td colspan="3" class="fw-bold-gray">IP Phone</td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Brand:</td>
                                    <td colspan="3" class="fw-bold-gray">GrandStream</td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Model / PNO:</td>
                                    <td colspan="3"><input type="text" name="otherModelPno" value="{{ $maintenance->other_equipment_model_pno ?? '' }}"></td>
                                </tr>
                            </table>
                        </td>

                        <!-- RIGHT SIDE: SPECS -->
                        <td class="col-right col-pad-vtop">
                            <div id="specsSection" class="{{ (!$isAdmin || $viewMode) ? 'disabled-section' : '' }}">
                            <table class="table-full">
                                <!-- DESKTOP SPECS -->
                                <tr>
                                    <td colspan="2" class="specs-header">DESKTOP SPECS</td>
                                </tr>
                                <tr>
                                    <td class="label-cell">CPU Capacity/Speed:</td>
                                    <td>
                                        <input type="text" name="dtCpu" value="{{ $maintenance->desktop_cpu ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">RAM Capacity:</td>
                                    <td>
                                        <input type="text" name="dtRam" value="{{ $maintenance->desktop_ram ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">GPU Capacity:</td>
                                    <td>
                                        <input type="text" name="dtGpu" value="{{ $maintenance->desktop_gpu ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">OS Version:</td>
                                    <td>
                                        <input type="text" name="dtOs" value="{{ $maintenance->desktop_os ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">HD-1 Type/Capacity:</td>
                                    <td>
                                        <input type="text" name="dtHd1" value="{{ $maintenance->desktop_hd1 ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">HD-2 Type/Capacity:</td>
                                    <td>
                                        <input type="text" name="dtHd2" value="{{ $maintenance->desktop_hd2 ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">MS Office Version:</td>
                                    <td>
                                        <input type="text" name="dtOffice" value="{{ $maintenance->desktop_office ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Year Purchased:</td>
                                    <td><input type="text" name="dtYear" value="{{ $maintenance->desktop_year_purchased ?? '' }}"></td>
                                </tr>

                                <!-- LAPTOP SPECS -->
                                <tr>
                                    <td colspan="2" class="specs-header">LAPTOP SPECS</td>
                                </tr>
                                <tr>
                                    <td class="label-cell">CPU Capacity/Speed:</td>
                                    <td>
                                        <input type="text" name="ltCpu" value="{{ $maintenance->laptop_cpu ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">RAM Capacity:</td>
                                    <td>
                                        <input type="text" name="ltRam" value="{{ $maintenance->laptop_ram ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">GPU Capacity:</td>
                                    <td>
                                        <input type="text" name="ltGpu" value="{{ $maintenance->laptop_gpu ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">OS Version:</td>
                                    <td>
                                        <input type="text" name="ltOs" value="{{ $maintenance->laptop_os ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">HD-1 Type/Capacity:</td>
                                    <td>
                                        <input type="text" name="ltHd1" value="{{ $maintenance->laptop_hd1 ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">HD-2 Type/Capacity:</td>
                                    <td>
                                        <input type="text" name="ltHd2" value="{{ $maintenance->laptop_hd2 ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">MS Office Version:</td>
                                    <td>
                                        <input type="text" name="ltOffice" value="{{ $maintenance->laptop_office ?? '' }}">
                                    </td>
                                </tr>

                                <!-- PRINTER SPECS -->
                                <tr>
                                    <td colspan="2" class="specs-header">PRINTER SPECS</td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Printer-1 Type:</td>
                                    <td>
                                        <label class="checkbox-inline"><input type="checkbox" name="p1Inkjet"> inkjet</label>
                                        <label class="checkbox-inline"><input type="checkbox" name="p1Laserjet"> laserjet</label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Printer-2 Type:</td>
                                    <td>
                                        <label class="checkbox-inline"><input type="checkbox" name="p2Inkjet"> inkjet</label>
                                        <label class="checkbox-inline"><input type="checkbox" name="p2Laserjet"> laserjet</label>
                                    </td>
                                </tr>

                                <!-- EARPHONE SPECS -->
                                <tr>
                                    <td colspan="2" class="specs-header">EARPHONE SPECS</td>
                                </tr>
                                <tr>
                                    <td class="label-cell">BRAND/MODEL:</td>
                                    <td><input type="text" name="earphoneSpecs" value="{{ $maintenance->earphone_brand_model ?? '' }}"></td>
                                </tr>
                            </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="section-bar-minimal">MAINTENANCE TASK CHECKLIST</div>

            <div id="checklistSection" class="{{ (!$isAdmin || $viewMode) ? 'disabled-section' : '' }}">
                @php
                    $tasks = isset($maintenance->maintenance_tasks_json) ? json_decode($maintenance->maintenance_tasks_json, true) : [];
                    $check = function($field) use ($tasks) {
                        return (isset($tasks[$field]) && ($tasks[$field] === 'YES' || $tasks[$field] === 'on')) ? 'checked' : '';
                    };
                @endphp
                <table class="tasks-table">
                    <thead>
                        <tr>
                            <th class="no-col">NO.</th>
                            <th class="equip-col">EQUIPMENT</th>
                            <th colspan="2">EXTERNAL TASK</th>
                            <th colspan="2">INTERNAL TASK-DESKTOP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- ROW 1: DESKTOP -->
                        <tr>
                            <td class="no-col" rowspan="6">1</td>
                            <td class="equip-col" rowspan="6">DESKTOP</td>
                            <td class="task-name">CASE CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="desktopCaseCleanup" {{ $check('desktopCaseCleanup') }}> Yes</td>
                            <!-- INTERNAL TASK COLUMN 1 (DESKTOP) -->
                            <td class="sub-header">DESKTOP</td>
                            <td class="sub-header">UPS</td>
                        </tr>
                        <tr>
                            <td class="task-name">CABLE / PLUG CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="desktopCableCleanup" {{ $check('desktopCableCleanup') }}> Yes</td>
                            <td class="task-name">DATA BACK-UP: <input type="checkbox" name="desktopDataBackup" {{ $check('desktopDataBackup') }}> Yes</td>
                            <td class="task-name">CHARGING: <input type="checkbox" name="upsCharging" {{ $check('upsCharging') }}> YES</td>
                        </tr>
                        <tr>
                            <td class="task-name">SYSTEM FAN CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="desktopSystemFanCleanup" {{ $check('desktopSystemFanCleanup') }}> Yes</td>
                            <td class="task-name">RESTORE POINT: <input type="checkbox" name="desktopRestorePoint" {{ $check('desktopRestorePoint') }}> Yes</td>
                            <td class="task-name">OVERLOAD: <input type="checkbox" name="upsOverload" {{ $check('upsOverload') }}> NO</td>
                        </tr>
                        <tr>
                            <td class="task-name">CPU FAN CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="desktopCpuFanCleanup" {{ $check('desktopCpuFanCleanup') }}> Yes</td>
                            <td class="task-name">WINDOWS UPDATE: <input type="checkbox" name="desktopWindowsUpdate" {{ $check('desktopWindowsUpdate') }}> Yes</td>
                            <td class="sub-header">IP PHONE</td>
                        </tr>
                        <tr>
                            <td class="task-name">MOTHER BOARD CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="desktopMotherboardCleanup" {{ $check('desktopMotherboardCleanup') }}> Yes</td>
                            <td class="task-name">TEMP FILES: <input type="checkbox" name="desktopTempFiles" {{ $check('desktopTempFiles') }}> CLEAN</td>
                            <td class="task-name">UPDATED: <input type="checkbox" name="ipPhoneUpdated" {{ $check('ipPhoneUpdated') }}> YES</td>
                        </tr>
                        <tr>
                            <td class="task-name">PSU CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="desktopPsuCleanup" {{ $check('desktopPsuCleanup') }}> Yes</td>
                            <td class="task-name">RECYCLE BIN: <input type="checkbox" name="desktopRecycleBin" {{ $check('desktopRecycleBin') }}> CLEAN</td>
                            <td></td>
                        </tr>

                        <!-- ROW 2: MONITOR -->
                        <tr class="monitor-1-checklist-row">
                            <td class="no-col" rowspan="2" id="monNoCell">2</td>
                            <td class="equip-col">MONITOR-1</td>
                            <td class="task-name">SCREEN CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="monitorScreenCleanup" {{ $check('monitorScreenCleanup') }}> Yes</td>
                            <td class="task-name">HDD DEFRAG: <input type="checkbox" name="desktopHddDefrag" {{ $check('desktopHddDefrag') }}> Yes</td>
                            <td></td>
                        </tr>
                        <tr class="monitor-1-checklist-row">
                            <td class="equip-col"></td>
                            <td class="task-name">CABLE / PLUG CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="monitorCableCleanup" {{ $check('monitorCableCleanup') }}> Yes</td>
                            <td class="task-name">HDD CHECK DISK: <input type="checkbox" name="desktopHddCheckDisk" {{ $check('desktopHddCheckDisk') }}> Yes</td>
                            <td></td>
                        </tr>
                        <tr class="monitor-2-checklist-row">
                            <td class="no-col" rowspan="2"></td>
                            <td class="equip-col">MONITOR-2</td>
                            <td class="task-name">SCREEN CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="monitor2ScreenCleanup" {{ $check('monitor2ScreenCleanup') }}> Yes</td>
                            <td class="task-name">SSD CHECK DISK: <input type="checkbox" name="desktopSsdCheckDisk" {{ $check('desktopSsdCheckDisk') }}> Yes</td>
                            <td></td>
                        </tr>
                        <tr class="monitor-2-checklist-row">
                            <td class="equip-col"></td>
                            <td class="task-name">CABLE / PLUG CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="monitor2CableCleanup" {{ $check('monitor2CableCleanup') }}> Yes</td>
                            <td class="task-name">ENDPOINT SCAN: <input type="checkbox" name="desktopEndpointScan" {{ $check('desktopEndpointScan') }}> Yes</td>
                            <td></td>
                        </tr>

                        <!-- ROW 3: PRINTER -->
                        <tr class="printer-1-checklist-row">
                            <td class="no-col" rowspan="2" id="prnNoCell">3</td>
                            <td class="equip-col">PRINTER-1</td>
                            <td class="task-name">CASE CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="printerCaseCleanup" {{ $check('printerCaseCleanup') }}> Yes</td>
                            <td class="task-name">VIRUS SCAN: <input type="checkbox" name="desktopVirusScan" {{ $check('desktopVirusScan') }}> Yes</td>
                            <td></td>
                        </tr>
                        <tr class="printer-1-checklist-row">
                            <td class="equip-col"></td>
                            <td class="task-name">CABLE / PLUG CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="printerCableCleanup" {{ $check('printerCableCleanup') }}> Yes</td>
                            <td class="task-name">START-UP FILE: <input type="checkbox" name="desktopStartupFile" {{ $check('desktopStartupFile') }}> CLEAN</td>
                            <td></td>
                        </tr>
                        <tr class="printer-2-checklist-row">
                            <td class="no-col" rowspan="2"></td>
                            <td class="equip-col">PRINTER-2</td>
                            <td class="task-name">CASE CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="printer2CaseCleanup" {{ $check('printer2CaseCleanup') }}> Yes</td>
                            <td class="task-name">WINDOWS DEFENDER: <input type="checkbox" name="desktopWindowsDefender" {{ $check('desktopWindowsDefender') }}> ON</td>
                            <td></td>
                        </tr>
                        <tr class="printer-2-checklist-row">
                            <td class="equip-col"></td>
                            <td class="task-name">CABLE / PLUG CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="printer2CableCleanup" {{ $check('printer2CableCleanup') }}> Yes</td>
                            <td></td>
                            <td></td>
                        </tr>

                        <!-- ROW 4: KEYBOARD -->
                        <tr>
                            <td class="no-col" rowspan="2">4</td>
                            <td class="equip-col" rowspan="2">KEYBOARD</td>
                            <td class="task-name">KEY PAD CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="keyboardKeypadCleanup" {{ $check('keyboardKeypadCleanup') }}> Yes</td>
                            <td class="sub-header">LAPTOP</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="task-name">CABLE / PLUG CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="keyboardCableCleanup" {{ $check('keyboardCableCleanup') }}> Yes</td>
                            <td class="task-name">DATA BACK-UP: <input type="checkbox" name="laptopDataBackup" {{ $check('laptopDataBackup') }}> Yes</td>
                            <td></td>
                        </tr>

                        <!-- ROW 5: MOUSE -->
                        <tr>
                            <td class="no-col">5</td>
                            <td class="equip-col">MOUSE</td>
                            <td class="task-name">CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="mouseCleanup" {{ $check('mouseCleanup') }}> Yes</td>
                            <td class="task-name">RESTORE POINT: <input type="checkbox" name="laptopRestorePoint" {{ $check('laptopRestorePoint') }}> Yes</td>
                            <td></td>
                        </tr>

                        <!-- ROW 6: UPS / AVR -->
                        <tr>
                            <td class="no-col" rowspan="2">6</td>
                            <td class="equip-col" rowspan="2">UPS / AVR</td>
                            <td class="task-name">CASE CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="upsCaseCleanup" {{ $check('upsCaseCleanup') }}> Yes</td>
                            <td class="task-name">WINDOWS UPDATE: <input type="checkbox" name="laptopWindowsUpdate" {{ $check('laptopWindowsUpdate') }}> Yes</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="task-name">CABLE / PLUG CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="upsCableCleanup" {{ $check('upsCableCleanup') }}> Yes</td>
                            <td class="task-name">TEMP FILES: <input type="checkbox" name="laptopTempFiles" {{ $check('laptopTempFiles') }}> CLEAN</td>
                            <td></td>
                        </tr>

                        <!-- ROW 7: SCANNER -->
                        <tr>
                            <td class="no-col" rowspan="2">7</td>
                            <td class="equip-col" rowspan="2">SCANNER</td>
                            <td class="task-name">CASE CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="scannerCaseCleanup" {{ $check('scannerCaseCleanup') }}> Yes</td>
                            <td class="task-name">RECYCLE BIN: <input type="checkbox" name="laptopRecycleBin" {{ $check('laptopRecycleBin') }}> CLEAN</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="task-name">CABLE / PLUG CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="scannerCableCleanup" {{ $check('scannerCableCleanup') }}> Yes</td>
                            <td class="task-name">HDD DEFRAG: <input type="checkbox" name="laptopHddDefrag" {{ $check('laptopHddDefrag') }}> Yes</td>
                            <td></td>
                        </tr>

                        <!-- ROW 8: IP PHONE -->
                        <tr>
                            <td class="no-col" rowspan="2">8</td>
                            <td class="equip-col" rowspan="2">IP PHONE</td>
                            <td class="task-name">UNIT CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="ipPhoneUnitCleanup" {{ $check('ipPhoneUnitCleanup') }}> Yes</td>
                            <td class="task-name">HDD CHECK DISK: <input type="checkbox" name="laptopHddCheckDisk" {{ $check('laptopHddCheckDisk') }}> Yes</td>
                            <td class="sub-header">PRINTER-INKJET</td>
                        </tr>
                        <tr>
                            <td class="task-name">CABLE / PLUG CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="ipPhoneCableCleanup" {{ $check('ipPhoneCableCleanup') }}> Yes</td>
                            <td class="task-name">SSD CHECK DISK: <input type="checkbox" name="laptopSsdCheckDisk" {{ $check('laptopSsdCheckDisk') }}> Yes</td>
                            <td class="task-name">INK LEVEL: <input type="checkbox" name="printerInkjetInkLevel" {{ $check('printerInkjetInkLevel') }}> OK</td>
                        </tr>

                        <!-- ROW 9: LAPTOP -->
                        <tr>
                            <td class="no-col">9</td>
                            <td class="equip-col">LAPTOP</td>
                            <td class="task-name">UNIT CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="laptopUnitCleanup" {{ $check('laptopUnitCleanup') }}> Yes</td>
                            <td class="task-name">ENDPOINT SCAN: <input type="checkbox" name="laptopEndpointScan" {{ $check('laptopEndpointScan') }}> Yes</td>
                            <td class="task-name">PRINT QUALITY: <input type="checkbox" name="printerInkjetPrintQuality" {{ $check('printerInkjetPrintQuality') }}> OK</td>
                        </tr>

                        <!-- ROW 10: WEBCAM -->
                        <tr>
                            <td class="no-col" rowspan="2">10</td>
                            <td class="equip-col">WEBCAM</td>
                            <td class="task-name">UNIT CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="webcamUnitCleanup" {{ $check('webcamUnitCleanup') }}> Yes</td>
                            <td class="task-name">VIRUS SCAN: <input type="checkbox" name="laptopVirusScan" {{ $check('laptopVirusScan') }}> Yes</td>
                            <td class="sub-header">PRINTER-LASERJET</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="task-name">START-UP FILE: <input type="checkbox" name="laptopStartupFile" {{ $check('laptopStartupFile') }}> CLEAN</td>
                            <td class="task-name">TONER: <input type="checkbox" name="printerLaserjetToner" {{ $check('printerLaserjetToner') }}> OK</td>
                        </tr>

                        <!-- ROW 11: SPEAKER -->
                        <tr>
                            <td class="no-col">11</td>
                            <td class="equip-col">SPEAKER</td>
                            <td class="task-name">UNIT CLEAN-UP</td>
                            <td class="check-cell"><input type="checkbox" name="speakerUnitCleanup" {{ $check('speakerUnitCleanup') }}> Yes</td>
                            <td class="task-name">WINDOWS DEFENDER: <input type="checkbox" name="laptopWindowsDefender" {{ $check('laptopWindowsDefender') }}> ON</td>
                            <td class="task-name">PRINT QUALITY: <input type="checkbox" name="printerLaserjetPrintQuality" {{ $check('printerLaserjetPrintQuality') }}> OK</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="form-actions-minimal">
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

    <script nonce="{{ $cspNonce }}">
        // Signature and Admin Controls Logic
        function clearSignature(canvasId, hiddenInputId) {
            const canvas = document.getElementById(canvasId);
            const hiddenInput = document.getElementById(hiddenInputId);
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fafafa';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                hiddenInput.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize signatures
            const canvases = ['technicianSignatureCanvas', 'endUserSignatureCanvas'];
            canvases.forEach(id => {
                const canvas = document.getElementById(id);
                const hiddenInput = document.getElementById(id.replace('Canvas', ''));
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                let drawing = false;
                let lastX = 0, lastY = 0;
                ctx.strokeStyle = '#000'; ctx.lineWidth = 1.5;
                ctx.lineCap = 'round'; ctx.lineJoin = 'round'; // Smoother lines
                
                // Restore saved signature
                if (hiddenInput.value && hiddenInput.value.startsWith('data:image')) {
                    const img = new Image();
                    img.onload = () => ctx.drawImage(img, 0, 0);
                    img.src = hiddenInput.value;
                }

                const getPos = (e) => {
                    const rect = canvas.getBoundingClientRect();
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    return { x: clientX - rect.left, y: clientY - rect.top };
                };

                const startDraw = (e) => {
                    e.preventDefault();
                    drawing = true;
                    const pos = getPos(e);
                    lastX = pos.x; lastY = pos.y;
                };

                const doDraw = (e) => {
                    if (!drawing) return;
                    e.preventDefault();
                    const pos = getPos(e);
                    ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(pos.x, pos.y); ctx.stroke();
                    lastX = pos.x; lastY = pos.y;
                    hiddenInput.value = canvas.toDataURL();
                };

                const stopDraw = () => { drawing = false; };

                // Mouse events (desktop)
                canvas.addEventListener('mousedown', startDraw);
                canvas.addEventListener('mousemove', doDraw);
                window.addEventListener('mouseup', stopDraw);

                // Touch events (mobile)
                canvas.addEventListener('touchstart', startDraw, { passive: false });
                canvas.addEventListener('touchmove', doDraw, { passive: false });
                canvas.addEventListener('touchend', stopDraw);
                canvas.addEventListener('touchcancel', stopDraw);
                
                // Prevent scrolling while signing
                canvas.style.touchAction = 'none';
            });

            // FOR DISPOSAL checkbox toggle
            const forDisposalCheck = document.getElementById('forDisposalCheck');
            const disposalAssetSelector = document.getElementById('disposalAssetSelector');
            if (forDisposalCheck && disposalAssetSelector) {
                forDisposalCheck.addEventListener('change', function() {
                    disposalAssetSelector.style.display = this.checked ? 'block' : 'none';
                    if (!this.checked) {
                        document.querySelector('[name="disposal_asset_id"]').value = '';
                    }
                });
            }

            // AJAX Form Submission
            const form = document.getElementById('pmForm');
            if (form) {
                // Handle Monitor and Printer Count Display Logic
                const monitorCountSelect = document.getElementById('monitorCountSelect');
                if (monitorCountSelect) {
                    const toggleMonitorRows = () => {
                        const count = monitorCountSelect.value;
                        document.querySelectorAll('.monitor-2-row, .monitor-2-checklist-row').forEach(row => {
                            row.style.display = count === '2' ? '' : 'none';
                        });
                    };
                    monitorCountSelect.addEventListener('change', toggleMonitorRows);
                    toggleMonitorRows(); // trigger on load
                }

                const printerCountSelect = document.getElementById('printerCountSelect');
                if (printerCountSelect) {
                    const togglePrinterRows = () => {
                        const count = printerCountSelect.value;
                        document.querySelectorAll('.printer-2-row, .printer-2-checklist-row').forEach(row => {
                            row.style.display = count === '2' ? '' : 'none';
                        });
                    };
                    printerCountSelect.addEventListener('change', togglePrinterRows);
                    togglePrinterRows(); // trigger on load
                }

                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (!this.querySelector('input[name="_method"]')) {
                        const pmAssetSel = document.getElementById('pm_linked_asset_id');
                        if (pmAssetSel && pmAssetSel.tagName === 'SELECT' && !pmAssetSel.value) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Asset Required',
                                text: 'Please select the accountable device or asset for this maintenance request.',
                                confirmButtonColor: '#0038A8'
                            });
                            return;
                        }
                    }

                    // Manual Signature Validation
                    const techSig = document.getElementById('technicianSignature');
                    if (techSig && !techSig.disabled && !techSig.closest('.disabled-section') && !techSig.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Signature Required',
                            text: 'Please provide the Technician Signature before submitting.',
                            confirmButtonColor: '#0038A8'
                        });
                        return;
                    }
                    const endUserSig = document.getElementById('endUserSignature');
                    if (endUserSig && !endUserSig.closest('.disabled-section') && !endUserSig.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Signature Required',
                            text: 'Please provide your End-User Signature before submitting.',
                            confirmButtonColor: '#0038A8'
                        });
                        return;
                    }
                    const endUserPrinted = document.querySelector('input[name="end_user_printed_name"]');
                    if (endUserPrinted && !endUserPrinted.closest('.disabled-section') && !endUserPrinted.disabled && !endUserPrinted.value.trim()) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Printed Name Required',
                            text: 'Please enter your printed name below your signature.',
                            confirmButtonColor: '#0038A8'
                        });
                        return;
                    }
                    
                    unlockPmSpecFields();
                    const formData = new FormData(this);
                    const url = this.getAttribute('action');
                    const method = this.querySelector('input[name="_method"]')?.value || 'POST';

                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.textContent;
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Submitting...';

                    fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message,
                                confirmButtonColor: '#0038A8'
                            }).then(() => {
                                if (data.redirect) {
                                    window.location.href = data.redirect;
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.message,
                                confirmButtonColor: '#0038A8'
                            });
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalBtnText;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An unexpected error occurred.',
                            confirmButtonColor: '#0038A8'
                        });
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText;
                    });
                });
            }

            // Handle Admin Controls
            const enableEditBtn = document.getElementById('enableEditBtn');
            if (enableEditBtn) {
                enableEditBtn.addEventListener('click', function() {
                    const sections = ['technicianSection', 'deviceInfoSection', 'analysisSection', 'suggestionSection', 'checklistSection'];
                    const isEditing = this.textContent.includes("View Only");
                    sections.forEach(id => {
                        const sec = document.getElementById(id);
                        if (sec) {
                            if (isEditing) {
                                sec.classList.add('disabled-section');
                                sec.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
                            } else {
                                sec.classList.remove('disabled-section');
                                sec.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
                            }
                        }
                    });
                    this.textContent = isEditing ? "Enable Editing" : "Switch to View Only";
                    this.style.backgroundColor = isEditing ? "#28a745" : "#dc3545";
                });
            }

            const assignItBtn = document.getElementById('assignItBtn');
            if (assignItBtn) {
                assignItBtn.addEventListener('click', function () {
                    const select = document.getElementById('assignItSelect');
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    assignItBtn.disabled = true;
                    fetch('{{ $request ? route("maintenance.assign", $request->id) : "" }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ assigned_to: select.value || null }),
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Assigned', text: data.message, confirmButtonColor: '#0038A8' })
                                .then(() => {
                                    // Redirect to PM Work Orders page after assignment
                                    window.location.href = '{{ route("pm-schedules.orders") }}';
                                });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Assignment failed', confirmButtonColor: '#0038A8' });
                            assignItBtn.disabled = false;
                        }
                    })
                    .catch(() => {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Could not save assignment.', confirmButtonColor: '#0038A8' });
                        assignItBtn.disabled = false;
                    });
                });
            }

            // Super Admin self-assign via dropdown
            const assignItSelect = document.getElementById('assignItSelect');
            const currentUserId = '{{ Auth::user()->id }}';
            
            if (assignItSelect) {
                const toggleItSection = () => {
                    // For maintenance form, there's no IT section to toggle like ICT form
                    // This just ensures the dropdown change is registered
                };
                
                assignItSelect.addEventListener('change', toggleItSection);
            }


        });
    </script>

    {{-- ── PM Asset auto-fill data island + logic ────────────────────────── --}}
    <script nonce="{{ $cspNonce }}">
        const PM_ASSETS_MAP = @json($assetsMap ?? []);
        console.log('DEBUG: PM_ASSETS_MAP initialized:', PM_ASSETS_MAP);

        /**
         * List of auto-fillable device/specs field names.
         */
        const PM_SPEC_FIELD_NAMES = [
            'desktopBrand', 'desktopModel', 'desktopPno', 'computerName',
            'monitor1Pno', 'monitor1Brand', 'monitor1Model',
            'monitor2Pno', 'monitor2Brand', 'monitor2Model',
            'printer1Pno', 'printer1Brand', 'printer1Model',
            'printer2Pno', 'printer2Brand', 'printer2Model',
            'upsPno', 'upsBrand', 'upsModel',
            'scannerPno', 'scannerBrand', 'scannerModel',
            'laptopPno', 'laptopBrand', 'laptopModel', 'laptopComputerName',
            'webcamBrand', 'webcamModel', 'webcamPno',
            'speakersBrand', 'speakersModel', 'speakersPno',
            'earphoneBrand', 'earphoneModel',
            'dtCpu', 'dtRam', 'dtGpu', 'dtOs', 'dtHd1', 'dtHd2', 'dtOffice', 'dtYear',
            'ltCpu', 'ltRam', 'ltGpu', 'ltOs', 'ltHd1', 'ltHd2', 'ltOffice', 'ltYear'
        ];

        /**
         * Clears all auto-fillable device information inputs.
         */
        function clearPmDeviceFields() {
            PM_SPEC_FIELD_NAMES.forEach(name => {
                const el = document.querySelector(`[name="${name}"]`);
                if (el) {
                    el.value = '';
                    el.disabled = false;
                    el.readOnly = false;
                }
            });
        }

        /**
         * Lock auto-filled specs fields as read-only.
         */
        function lockPmSpecFields() {
            PM_SPEC_FIELD_NAMES.forEach(name => {
                const el = document.querySelector(`[name="${name}"]`);
                if (el && el.value) {
                    if (el.tagName === 'SELECT') {
                        el.disabled = true;
                    } else {
                        el.readOnly = true;
                    }
                }
            });
        }

        /**
         * Unlock specs fields before form submission so values are included.
         */
        function unlockPmSpecFields() {
            PM_SPEC_FIELD_NAMES.forEach(name => {
                const el = document.querySelector(`[name="${name}"]`);
                if (el) {
                    el.disabled = false;
                    el.readOnly = false;
                }
            });
        }

        /**
         * Given a selected asset_id, pre-fill device info fields based on the asset's
         * category and specifications JSON stored in inventory_assets.
         */
        function pmAutoFillFromAsset(assetId) {
            clearPmDeviceFields();
            if (!assetId || !PM_ASSETS_MAP[assetId]) return;

            const asset = PM_ASSETS_MAP[assetId];
            const cat   = (asset.category || '').toLowerCase();
            const specs = asset.specs || {};
            
            console.log('DEBUG: pmAutoFillFromAsset called with assetId:', assetId);
            console.log('DEBUG: asset category:', cat);
            console.log('DEBUG: asset specs:', specs);
            console.log('DEBUG: specs.ram:', specs.ram);
            console.log('DEBUG: specs.hd1:', specs.hd1);
            console.log('DEBUG: specs.desktop_ram:', specs.desktop_ram);
            console.log('DEBUG: specs.desktop_hd1:', specs.desktop_hd1);

            // Helper — set a form field's value by name
            const fill = (name, value) => {
                if (!value) return;
                const el = document.querySelector(`[name="${name}"]`);
                if (!el) return;
                console.log(`DEBUG: fill('${name}', '${value}')`);
                if (el.tagName === 'SELECT') {
                    const valStr = String(value).trim().toLowerCase();
                    const valClean = valStr.replace(/[^a-z0-9]/g, ''); // e.g. "16gb", "corei9"
                    const opts = Array.from(el.options);
                    console.log(`DEBUG: ${name} options:`, opts.map(o => o.value));
                    
                    // 1. Try exact match (case-insensitive)
                    let match = opts.find(o => o.value.toLowerCase() === valStr);
                    
                    // 2. Try normalized match (remove non-alphanumeric, like spaces/hyphens)
                    if (!match) {
                        match = opts.find(o => o.value.toLowerCase().replace(/[^a-z0-9]/g, '') === valClean);
                    }
                    
                    // 3. Smart fallbacks for specific specs
                    if (!match) {
                        if (name.includes('Cpu')) {
                            // Try to match CPU by brand and type
                            if (valStr.includes('i3') || valStr.includes('core i3')) match = opts.find(o => o.value.includes('i3'));
                            else if (valStr.includes('i5') || valStr.includes('core i5')) match = opts.find(o => o.value.includes('i5'));
                            else if (valStr.includes('i7') || valStr.includes('core i7')) match = opts.find(o => o.value.includes('i7'));
                            else if (valStr.includes('i9') || valStr.includes('core i9')) match = opts.find(o => o.value.includes('i9'));
                            else if (valStr.includes('ryzen 3') || valStr.includes('r3')) match = opts.find(o => o.value.includes('Ryzen 3'));
                            else if (valStr.includes('ryzen 5') || valStr.includes('r5')) match = opts.find(o => o.value.includes('Ryzen 5'));
                            else if (valStr.includes('ryzen 7') || valStr.includes('r7')) match = opts.find(o => o.value.includes('Ryzen 7'));
                            else if (valStr.includes('m1') || valStr.includes('m2') || valStr.includes('m3') || valStr.includes('apple') || valStr.includes('mac')) {
                                match = opts.find(o => o.value.includes('Apple'));
                            } else if (valStr.includes('ryzen')) {
                                // Generic ryzen match
                                match = opts.find(o => o.value.toLowerCase().includes('ryzen'));
                            } else if (valStr.includes('core') || valStr.includes('intel')) {
                                // Generic intel/core match
                                match = opts.find(o => o.value.toLowerCase().includes('core'));
                            }
                        } else if (name.includes('Ram')) {
                            // Extract FIRST number sequence only (e.g., "64GB DDR4" → "64", not "644")
                            const numMatch = valStr.match(/^(\d+)/);
                            const num = numMatch ? numMatch[1] : '';
                            console.log(`DEBUG: ${name} RAM matching - extracted num: '${num}' from '${valStr}'`);
                            if (num) {
                                // Try exact number match first: "8gb ddr4" → extracts "8" → matches "8 GB"
                                match = opts.find(o => {
                                    const optNum = o.value.replace(/[^0-9]/g, '');
                                    console.log(`  Comparing '${num}' with option '${o.value}' (extracted: '${optNum}')`);
                                    return optNum === num;
                                });
                                console.log(`DEBUG: ${name} after number extraction, match:`, match ? match.value : 'null');
                                // Fallback: try substring matching if exact doesn't work
                                if (!match) {
                                    match = opts.find(o => o.value.toLowerCase().includes(num + ' gb') || o.value.toLowerCase().includes(num + 'gb'));
                                    console.log(`DEBUG: ${name} after substring fallback, match:`, match ? match.value : 'null');
                                }
                            }
                        } else if (name.includes('Gpu')) {
                            if (valStr.includes('intel') || valStr.includes('integrated') || valStr.includes('uhd') || valStr.includes('iris') || valStr.includes('shared') || valStr.includes('m1') || valStr.includes('m2') || valStr.includes('m3')) {
                                match = opts.find(o => o.value === 'Integrated');
                            } else if (valStr.includes('rtx') || valStr.includes('gtx') || valStr.includes('nvidia') || valStr.includes('geforce') || valStr.includes('amd') || valStr.includes('dedicated') || valStr.includes('radeon')) {
                                // Extract LAST number sequence for VRAM (e.g., "RTX 4070 12GB" → "12", not "407012")
                                const gpuVramMatch = valStr.match(/(\d+)\s*gb\s*$/i);
                                if (gpuVramMatch && gpuVramMatch[1]) {
                                    const vram = parseInt(gpuVramMatch[1]);
                                    console.log(`DEBUG: ${name} GPU - extracted VRAM: '${vram}'`);
                                    if (vram >= 8) match = opts.find(o => o.value.includes('8GB+'));
                                    else if (vram >= 4) match = opts.find(o => o.value.includes('4GB'));
                                    else if (vram >= 2) match = opts.find(o => o.value.includes('2GB'));
                                } else {
                                    // No VRAM found, default to highest available
                                    match = opts.find(o => o.value.includes('8GB+')) || opts.find(o => o.value.includes('4GB'));
                                }
                            } else {
                                // Fallback: substring matching for unknown GPU types
                                match = opts.find(o => o.value.toLowerCase().includes(valStr) || valStr.includes(o.value.toLowerCase()));
                            }
                        } else if (name.includes('Os')) {
                            if (valStr.includes('11')) match = opts.find(o => o.value.includes('11'));
                            else if (valStr.includes('10')) match = opts.find(o => o.value.includes('10'));
                            else if (valStr.includes('mac')) match = opts.find(o => o.value.toLowerCase().includes('mac'));
                            else if (valStr.includes('linux')) match = opts.find(o => o.value.toLowerCase().includes('linux'));
                        } else if (name.includes('Hd') || name.includes('Storage')) {
                            const isSsd = valStr.includes('ssd') || valStr.includes('nvme');
                            const isHdd = valStr.includes('hdd');
                            const isNvme = valStr.includes('nvme') || valStr.includes('m.2');
                            const has2tb = valStr.includes('2tb') || valStr.includes('2 tb') || valStr.includes('2000gb') || valStr.includes('2048gb');
                            const has1 = valStr.includes('1tb') || valStr.includes('1 tb') || valStr.includes('1000gb') || valStr.includes('1024gb');
                            const has512 = valStr.includes('512') || valStr.includes('500');
                            const has256 = valStr.includes('256') || valStr.includes('240');
                            const has128 = valStr.includes('128');
                            
                            if (isNvme) {
                                // NVMe/M.2 detected — try exact match first, then fallback to SATA SSD
                                if (has2tb) match = opts.find(o => o.value === '2TB M.2 NVMe SSD') || opts.find(o => o.value === 'SSD - 2TB');
                                else if (has1) match = opts.find(o => o.value === '1TB M.2 NVMe SSD') || opts.find(o => o.value === 'SSD - 1TB');
                                else if (has512) match = opts.find(o => o.value === '512GB M.2 NVMe SSD') || opts.find(o => o.value === 'SSD - 512GB');
                                else if (has256) match = opts.find(o => o.value === '256GB M.2 NVMe SSD') || opts.find(o => o.value === 'SSD - 256GB');
                                else if (has128) match = opts.find(o => o.value === '128GB M.2 NVMe SSD');
                            } else if (isSsd) {
                                // Generic SSD (SATA)
                                if (has2tb) match = opts.find(o => o.value === 'SSD - 2TB');
                                else if (has1) match = opts.find(o => o.value === 'SSD - 1TB');
                                else if (has512) match = opts.find(o => o.value === 'SSD - 512GB');
                                else if (has256) match = opts.find(o => o.value === 'SSD - 256GB');
                            } else if (isHdd) {
                                if (has1) match = opts.find(o => o.value === 'HDD - 1TB');
                                else if (valStr.includes('500')) match = opts.find(o => o.value === 'HDD - 500GB');
                            } else {
                                match = opts.find(o => o.value.toLowerCase().includes(valStr) || valStr.includes(o.value.toLowerCase()));
                            }
                        } else if (name.includes('Office')) {
                            if (valStr.includes('365')) match = opts.find(o => o.value.includes('365'));
                            else if (valStr.includes('2021')) match = opts.find(o => o.value.includes('2021'));
                            else if (valStr.includes('2019')) match = opts.find(o => o.value.includes('2019'));
                            else if (valStr.includes('2016')) match = opts.find(o => o.value.includes('2016'));
                            else if (valStr.includes('office') || valStr.includes('microsoft')) {
                                // If contains Office/Microsoft but no version, try to find any Office option
                                match = opts.find(o => o.value && o.value.toLowerCase().includes('office'));
                            }
                        } else if (name.includes('Brand')) {
                            // Substring brand match (e.g. "Samsung Monitor" matches Samsung, "Epson Printer" matches Epson)
                            match = opts.find(o => valStr.includes(o.value.toLowerCase()) || o.value.toLowerCase().includes(valStr));
                        }
                    }
                    
                    if (match) {
                        el.value = match.value;
                        console.log(`DEBUG: ${name} matched to: '${match.value}'`);
                    } else {
                        // Last resort: try substring matching for any remaining values
                        if (!match) {
                            match = opts.find(o => o.value && (o.value.toLowerCase().includes(valStr.substring(0, 3)) || (valStr.length > 3 && o.value.toLowerCase().includes(valStr))));
                        }
                        
                        if (match) {
                            el.value = match.value;
                            console.log(`DEBUG: ${name} matched via substring to: '${match.value}'`);
                        } else {
                            const otherOpt = opts.find(o => o.value === 'Other' || o.value === 'Others');
                            if (otherOpt) {
                                el.value = otherOpt.value;
                                console.log(`DEBUG: ${name} NO MATCH - set to Other`);
                            } else {
                                el.value = '';
                                console.log(`DEBUG: ${name} NO MATCH - left empty`);
                            }
                        }
                    }
                } else {
                    el.value = value;
                }
            };

            // 2. Fill the selected primary asset's fields
            if (cat.includes('desktop') || cat.includes('computer')) {
                fill('desktopBrand',  specs.desktop_brand   || specs.brand || asset.item_name);
                fill('desktopModel',  specs.desktop_model   || specs.model || asset.item_name);
                fill('desktopPno',    asset.serial_number);
                fill('dtCpu',         specs.desktop_cpu     || specs.cpu);
                fill('dtRam',         specs.desktop_ram     || specs.ram);
                fill('dtGpu',         specs.desktop_gpu     || specs.gpu);
                fill('dtOs',          specs.desktop_os      || specs.os);
                fill('dtHd1',         specs.desktop_hd1     || specs.storage || specs.hd1);
                fill('dtHd2',         specs.desktop_hd2     || specs.hd2);
                fill('dtOffice',      specs.desktop_office  || specs.office);
                fill('dtYear',        specs.desktop_year_purchased || specs.year_purchased);
            } else if (cat.includes('laptop')) {
                fill('laptopBrand',   specs.laptop_brand    || specs.brand || asset.item_name);
                fill('laptopModel',   specs.laptop_model    || specs.model || asset.item_name);
                fill('laptopPno',     asset.serial_number);
                fill('ltCpu',         specs.laptop_cpu      || specs.cpu);
                fill('ltRam',         specs.laptop_ram      || specs.ram);
                fill('ltGpu',         specs.laptop_gpu      || specs.gpu);
                fill('ltOs',          specs.laptop_os       || specs.os);
                fill('ltHd1',         specs.laptop_hd1      || specs.storage || specs.hd1);
                fill('ltHd2',         specs.laptop_hd2      || specs.hd2);
                fill('ltOffice',      specs.laptop_office   || specs.office);
                fill('ltYear',        specs.laptop_year_purchased || specs.year_purchased);
            } else if (cat.includes('monitor')) {
                fill('monitor1Brand', specs.monitor_brand   || specs.brand || asset.item_name);
                fill('monitor1Model', specs.monitor_model   || specs.model || asset.item_name);
                fill('monitor1Pno',   asset.serial_number);
            } else if (cat.includes('printer')) {
                fill('printer1Brand', specs.printer_brand   || specs.brand || asset.item_name);
                fill('printer1Model', specs.printer_model   || specs.model || asset.item_name);
                fill('printer1Pno',   asset.serial_number);
            } else if (cat.includes('ups')) {
                fill('upsBrand',      specs.ups_brand       || specs.brand || asset.item_name);
                fill('upsModel',      specs.ups_model       || specs.model || asset.item_name);
                fill('upsPno',        asset.serial_number);
            } else if (cat.includes('scanner')) {
                fill('scannerBrand',  specs.scanner_brand   || specs.brand || asset.item_name);
                fill('scannerModel',  specs.scanner_model   || specs.model || asset.item_name);
                fill('scannerPno',    asset.serial_number);
            }

            // 3. Find and auto-fill OTHER matching category assets assigned to the same user (sub-components)
            for (const otherId in PM_ASSETS_MAP) {
                if (otherId === String(assetId)) continue;
                const otherAsset = PM_ASSETS_MAP[otherId];
                const otherCat = (otherAsset.category || '').toLowerCase();
                const otherSpecs = otherAsset.specs || {};

                if (otherCat.includes('monitor')) {
                    fill('monitor1Brand', otherSpecs.monitor_brand || otherSpecs.brand || otherAsset.item_name);
                    fill('monitor1Model', otherSpecs.monitor_model || otherSpecs.model || otherAsset.item_name);
                    fill('monitor1Pno',   otherAsset.serial_number);
                } else if (otherCat.includes('printer')) {
                    fill('printer1Brand', otherSpecs.printer_brand || otherSpecs.brand || otherAsset.item_name);
                    fill('printer1Model', otherSpecs.printer_model || otherSpecs.model || otherAsset.item_name);
                    fill('printer1Pno',   otherAsset.serial_number);
                } else if (otherCat.includes('ups')) {
                    fill('upsBrand',      otherSpecs.ups_brand       || otherSpecs.brand || otherAsset.item_name);
                    fill('upsModel',      otherSpecs.ups_model       || otherSpecs.model || otherAsset.item_name);
                    fill('upsPno',        otherAsset.serial_number);
                } else if (otherCat.includes('scanner')) {
                    fill('scannerBrand',  otherSpecs.scanner_brand   || otherSpecs.brand || otherAsset.item_name);
                    fill('scannerModel',  otherSpecs.scanner_model   || otherSpecs.model || otherAsset.item_name);
                    fill('scannerPno',    otherAsset.serial_number);
                } else if (otherCat.includes('webcam')) {
                    fill('webcamBrand',   otherSpecs.webcam_brand    || otherSpecs.brand || otherAsset.item_name);
                    fill('webcamModel',   otherSpecs.webcam_model    || otherSpecs.model || otherAsset.item_name);
                    fill('webcamPno',     otherAsset.serial_number);
                } else if (otherCat.includes('speaker') || otherCat.includes('audio')) {
                    fill('speakersBrand', otherSpecs.speakers_brand  || otherSpecs.brand || otherAsset.item_name);
                    fill('speakersModel', otherSpecs.speakers_model  || otherSpecs.model || otherAsset.item_name);
                    fill('speakersPno',   otherAsset.serial_number);
                } else if (otherCat.includes('earphone') || otherCat.includes('headset')) {
                    fill('earphoneBrand', otherSpecs.earphone_brand  || otherSpecs.brand || otherAsset.item_name);
                    fill('earphoneModel', otherSpecs.earphone_model  || otherSpecs.model || otherAsset.item_name);
                }
            }

            // Lock auto-filled specs fields as read-only
            lockPmSpecFields();
        }

        document.addEventListener('DOMContentLoaded', () => {
            const pmSelect = document.getElementById('pm_linked_asset_id');
            if (pmSelect) {
                // Auto-fill on page load if an asset is already pre-selected
                if (pmSelect.value) pmAutoFillFromAsset(pmSelect.value);

                pmSelect.addEventListener('change', function () {
                    pmAutoFillFromAsset(this.value);
                });
            }

            // Assign IT button
            const assignItBtn = document.getElementById('assignItBtn');
            if (assignItBtn) {
                assignItBtn.addEventListener('click', function() {
                    const select = document.getElementById('assignItSelect');
                    const assignedTo = select ? select.value : '';
                    const url = '{{ $request ? route("maintenance.assign", $request->id) : "" }}';

                    if (!url) return;

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ assigned_to: assignedTo })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Assigned!', text: data.message, confirmButtonColor: '#0038A8' })
                                .then(() => window.location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                        }
                    })
                    .catch(() => {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'An unexpected error occurred.' });
                    });
                });
            }
        });
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-canvas]');
            if (btn) {
                clearSignature(btn.dataset.canvas, btn.dataset.input);
            }
        });
        var resignBtn = document.getElementById('resignBtn');
        if (resignBtn) {
            resignBtn.addEventListener('click', function() {
                document.getElementById('endUserSignature').value = '';
                this.parentElement.innerHTML = '<canvas id="endUserSignatureCanvas" class="signature-canvas" width="350" height="64"></canvas><input type="hidden" id="endUserSignature" name="endUserSignature" value=""><button type="button" class="btn-clear-sig-minimal" data-canvas="endUserSignatureCanvas" data-input="endUserSignature">Clear</button>';
                initSignature('endUserSignatureCanvas', 'endUserSignature');
            });
        }
    </script>
</body>
</html>
