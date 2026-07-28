@php
    /** Format a raw DB date/datetime to yyyy-MM-dd for HTML date inputs */
    function fmtDate($val): string {
        if (empty($val)) return '';
        try { return \Carbon\Carbon::parse($val)->format('Y-m-d'); } catch (\Throwable $e) { return ''; }
    }
@endphp

<x-form-layout
    title="ICT Service Request Form"
    cssFile="ict-form.css"
    bannerImage="ict-banner.png"
    cspNonce="{{ $cspNonce }}"
>
    @slot('extraHead')
        @include('partials.ict._form-styles')
    @endslot

        @php
            // Flags from RequestAuthorization::ictFormFlags() — isAdmin = can edit Sections 2–5 (admin or assigned IT)
            $isView = !empty($viewMode);
            $user = Auth::user();
            $isSuperAdmin = $user->role === 'super_admin';
            $isAdmin = $isAdmin ?? false;
            $canEditEndUser = $canEditEndUser ?? false;
            $isUser = $user->role === 'user';
            $isUpdate = isset($request) && $request;
            $hasAssignedAssets = $hasAssignedAssets ?? (!empty($myAssets) && count($myAssets) > 0);
            $isNewUserRequest = $isUser && !$isUpdate && !$isView;
            $canSubmitNewRequest = $isNewUserRequest && $hasAssignedAssets;
            $canSignAcceptance = !empty($canSignAcceptance);
            $acceptanceBlockReason = $acceptanceBlockReason ?? null;
            
            // Do not force super_admin into view-only here; view flags come from controller.

            // Asset data JSON for IT auto-fill (pre-built in controller)
            $linkedAssetData = $linkedAssetData ?? null;

            // IT user name parsing for auto-fill
            $itUserParts = [];
            if ($user->role === 'it') {
                $parts = explode(' ', trim($user->full_name));
                $itLastName  = count($parts) > 1 ? array_pop($parts) : $user->full_name;
                $itMiddleName = '';
                if (count($parts) > 1) {
                    $mw = array_pop($parts);
                    $itMiddleName = substr($mw, 0, 1) . '.';
                }
                $itFirstName = count($parts) > 0 ? implode(' ', $parts) : '';
                $itUserParts = [
                    'first_name'  => $itFirstName,
                    'last_name'   => $itLastName,
                    'middle_name' => $itMiddleName,
                ];
            }

            // Build ICT assets map for auto-fill on asset selection
            // If controller already passed one, use it (controller version includes property_number + par_number)
            if (empty($ictAssetsMap) && !empty($myAssets)) {
                $ictAssetsMap = [];
                foreach ($myAssets as $asset) {
                    $specs = is_string($asset->specifications)
                        ? json_decode($asset->specifications, true) ?? []
                        : ($asset->specifications ?? []);
                    
                    $ictAssetsMap[(int)$asset->asset_id] = [
                        'item_name'       => $asset->item_name,
                        'category'        => $asset->category,
                        'serial_number'   => $asset->serial_number,
                        'property_number' => $asset->property_number,
                        'par_number'      => $asset->par_number,
                        'date_acquired'   => $asset->date_acquired
                            ? \Carbon\Carbon::parse($asset->date_acquired)->format('Y-m-d')
                            : null,
                        'specs'           => $specs,
                    ];
                }
            }
        @endphp

        @if(!empty($canAssignIt) && $isUpdate && Auth::user()->role === 'super_admin' && (!$request->assigned_to || (int)$request->assigned_to !== (int)Auth::user()->id))
        <div id="assignItPanel" class="ict-assign-panel">
            <div class="ict-assign-inner">
                <div class="ict-assign-left">
                    <div class="ict-assign-label">
                        <i class="fa-solid fa-user-gear"></i> Assign IT Personnel
                    </div>
                    <p class="ict-assign-text">
                        Super Admin: Select IT staff to perform technical work on this ticket.
                    </p>
                    <select id="assignItSelect" class="minimal-input ict-assign-select">
                        <option value="">— Unassigned —</option>
                        @foreach($itPersonnel ?? [] as $it)
                            <option value="{{ $it->id }}" {{ (int) $request->assigned_to === (int) $it->id ? 'selected' : '' }}>
                                {{ $it->full_name }}@if($it->office) ({{ $it->office }})@endif
                            </option>
                        @endforeach
                        @if(!empty($canSelfAssign))
                            <option value="{{ Auth::user()->id }}" {{ (int) $request->assigned_to === (int) Auth::user()->id ? 'selected' : '' }}>
                                [SELF] I will handle this myself
                            </option>
                        @endif
                    </select>
                    @if(empty($itPersonnel) || count($itPersonnel) === 0)
                        <p class="ict-assign-notice">No active IT accounts in your scope. Create an <code>it</code> role user in Personnel first.</p>
                    @endif
                </div>
                <div class="ict-assign-right">
                    @if($request->assignedTo)
                        <div class="ict-assign-current-label">Currently assigned:</div>
                        <div class="ict-assign-current-name">{{ $request->assignedTo->full_name }}</div>
                    @else
                        <span class="ict-assign-unassigned">UNASSIGNED</span>
                    @endif
                    <button type="button" id="assignItBtn" class="ict-assign-btn">
                        Save Assignment
                    </button>
                </div>
            </div>
        </div>
        @endif

        @if(!empty($canReviewAsDivisionAdmin) && $isUpdate)
        <div id="divisionAdminReviewPanel" class="ict-div-review-panel">
            <div class="ict-div-review-label">
                <i class="fa-solid fa-clipboard-check"></i> Division Admin Review
            </div>
            <p class="ict-div-review-text">
                Please review this request from your division before it is forwarded to the Super Admin (IT) for assignment.
            </p>
            <div class="ict-div-review-body">
                <textarea id="divisionAdminNotes" class="minimal-input ict-div-review-textarea" rows="2" placeholder="Optional notes or remarks regarding this request..."></textarea>
                <div class="ict-div-review-actions">
                    <button type="button" class="btn-secondary division-review-btn ict-div-review-btn-reject" data-status="Rejected">
                        <i class="fa-solid fa-xmark"></i> Reject
                    </button>
                    <button type="button" class="btn-secondary division-review-btn ict-div-review-btn-approve" data-status="Approved">
                        <i class="fa-solid fa-check"></i> Approve & Forward
                    </button>
                </div>
            </div>
        </div>
        @endif

        @if($isUpdate && $request->division_admin_review_status)
        <div class="ict-review-status-box">
            <div class="ict-review-status-label">Division Admin Review Status</div>
            <div class="ict-review-status-row">
                @if($request->division_admin_review_status === 'Approved')
                    <span class="ict-review-approved"><i class="fa-solid fa-check-circle"></i> APPROVED</span>
                @elseif($request->division_admin_review_status === 'Rejected')
                    <span class="ict-review-rejected"><i class="fa-solid fa-xmark-circle"></i> REJECTED</span>
                @endif
                <span class="ict-review-date">on {{ \Carbon\Carbon::parse($request->reviewed_at)->format('M d, Y h:i A') }}</span>
            </div>
            @if($request->division_admin_notes)
                <div class="ict-review-notes">
                    <strong>Notes:</strong> {{ $request->division_admin_notes }}
                </div>
            @endif
        </div>
        @endif

        {{-- Resubmit notice for rejected requests (user only) --}}
        @if($isUpdate && isset($request) && $request && $request->status === 'Rejected' && $isUser)
        <div role="alert" class="ict-alert-rejected">
            <strong class="ict-alert-rejected-title">
                <i class="fa-solid fa-triangle-exclamation"></i> Request was Rejected
            </strong>
            <p class="ict-alert-rejected-text">
                You can update the information in Section 1 below and click <strong>Resubmit Request</strong> to send it again for review.
            </p>
        </div>
        @endif

        @if($isNewUserRequest && !$hasAssignedAssets)
            <div role="alert" class="ict-alert-no-asset">
                <strong class="ict-alert-no-asset-title">
                    <i class="fa-solid fa-circle-xmark"></i> Cannot submit a repair request yet
                </strong>
                <p class="ict-alert-no-asset-text">
                    Walang naka-assign na accountable equipment sa iyong account. Pakiusap makipag-ugnayan sa <strong>Administrative supply admin</strong> para ma-assign ang device bago mag-file ng ICT request.
                </p>
            </div>
        @endif

        <form id="repairRequestForm" action="{{ $isUpdate ? route('ict.update', $request->id) : route('ict.store') }}" method="POST">
            @csrf
            @if($isUpdate) 
                @method('PUT') 
                <input type="hidden" name="last_updated_at" value="{{ $request->updated_at }}">
            @endif

            @include('requests.ict.partials._privacy_notice')


            <!-- SECTION HEADER -->
            <div class="section-header" id="endUserSectionHeader">
                <h3>END-USER INFORMATION</h3>
            </div>

            <div id="endUserSection" class="{{ ($isView || !$canEditEndUser) ? 'disabled-section' : '' }}">

                @php
                    $userParts = explode(' ', trim(Auth::user()->full_name));
                    $defaultLastName = count($userParts) > 1 ? array_pop($userParts) : Auth::user()->full_name;
                    
                    $defaultMiddleName = '';
                    if (count($userParts) > 1) {
                        $middleWord = array_pop($userParts);
                        $defaultMiddleName = substr($middleWord, 0, 1) . '.';
                    }
                    
                    $defaultFirstName = count($userParts) > 0 ? implode(' ', $userParts) : '';
                @endphp
                <!-- NAME -->
                <div class="form-group compact">
                    <label class="required">NAME OF END-USER:</label>
                    <div class="form-row compact-row">
                        <div class="form-col">
                            <label class="inline-label">LAST NAME</label>
                            <input type="text" id="endUserLastName" name="endUserLastName" value="{{ $repairRequest->end_user_last_name ?? $defaultLastName }}" required {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>
                        </div>
                        <div class="form-col">
                            <label class="inline-label">FIRST NAME</label>
                            <input type="text" id="endUserFirstName" name="endUserFirstName" value="{{ $repairRequest->end_user_first_name ?? $defaultFirstName }}" required {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>
                        </div>
                        <div class="form-col">
                            <label class="inline-label">MIDDLE NAME</label>
                            <input type="text" id="endUserMiddleName" name="endUserMiddleName" value="{{ $repairRequest->end_user_middle_name ?? $defaultMiddleName }}" {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>

                <!-- SEX + DIVISION + EMAIL + EMPLOYEE NO -->
                <div class="form-row compact-row">
                    <div class="form-col">
                        <div class="form-group compact">
                            <label class="required">SEX:</label>
                            <div class="radio-group compact-radio">
                                <label class="radio-label {{ ($repairRequest->end_user_sex ?? '') == 'MALE' ? 'radio-checked' : '' }}">
                                    <input type="radio" name="endUserSex" value="MALE" {{ ($repairRequest->end_user_sex ?? '') == 'MALE' ? 'checked' : '' }} required {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>
                                    <span>MALE</span>
                                </label>
                                <label class="radio-label {{ ($repairRequest->end_user_sex ?? '') == 'FEMALE' ? 'radio-checked' : '' }}">
                                    <input type="radio" name="endUserSex" value="FEMALE" {{ ($repairRequest->end_user_sex ?? '') == 'FEMALE' ? 'checked' : '' }} {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>
                                    <span>FEMALE</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="divisionOffice" class="required">DIVISION / OFFICE:</label>
                            <input type="text" id="divisionOffice" name="divisionOffice" value="{{ $repairRequest->division_office ?? Auth::user()->office ?? '' }}" required {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="endUserEmail" class="required">EMAIL:</label>
                            <input type="email" id="endUserEmail" name="endUserEmail" value="{{ $repairRequest->end_user_email ?? Auth::user()->email ?? '' }}" required {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="employeeNo" class="required">EMPLOYEE NO.:</label>
                            <input type="text" id="employeeNo" name="employeeNo" value="{{ $repairRequest->employee_no ?? '' }}" required {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>

                <!-- DEVICE / ASSET SELECTION -->
                <div class="form-group compact ict-field-mt">
                    <label for="linked_asset_id" class="required">DEVICE / ASSET TO BE REPAIRED:</label>
                    @if($isView || !$canEditEndUser)
                        <input type="text" value="{{ $request && $request->linkedAsset ? $request->linkedAsset->item_name . ' (SN: ' . ($request->linkedAsset->serial_number ?? 'N/A') . ')' : 'No asset linked' }}" disabled class="ict-disabled-bg">
                        @if($request && $request->linked_asset_id)
                            <input type="hidden" name="linked_asset_id" value="{{ $request->linked_asset_id }}">
                        @endif
                    @else
                        <div class="ict-asset-row">
                            <div class="ict-asset-select-wrap">
                                <select id="linked_asset_id" name="linked_asset_id" required class="ict-asset-select">
                                    <option value="">— Select your asset —</option>
                                    @foreach($myAssets as $asset)
                                        <option value="{{ $asset->asset_id }}" {{ ($request->linked_asset_id ?? '') == $asset->asset_id ? 'selected' : '' }}>
                                            {{ $asset->item_name }} (SN: {{ $asset->serial_number ?: 'N/A' }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="ict-asset-hint">
                                    Asset status will automatically update when the ticket progresses to <strong>Ongoing</strong> or <strong>Completed</strong>.
                                </p>
                            </div>
                            <button type="button" id="scanQrBtn" class="ict-scan-btn">
                                <i class="fa-solid fa-camera"></i> Scan
                            </button>
                        </div>
                    @endif
                </div>

                <!-- DESCRIPTION -->
                <div class="form-group compact">
                    <label for="repairDescription" class="required">
                        INITIAL DIAGNOSIS:
                    </label>
                    <textarea id="repairDescription" name="repairDescription" rows="3" required {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>{{ $repairRequest->repair_description ?? '' }}</textarea>
                </div>

                <!-- SIGNATURE + PRINTED NAME + DATE -->
                <div class="form-row compact-row">
                    <div class="form-col">
                        <div class="form-group compact">
                            <label class="required">
                                End-User Signature over Printed Name:
                            </label>

                            <div class="signature-wrapper">
                                <div class="signature-container">
                                    @if(!empty($repairRequest->end_user_signature))
                                        <img src="/{{ $repairRequest->end_user_signature }}" class="signature-preview" alt="Signature">
                                        <input type="hidden" id="endUserSignature" name="endUserSignature" value="{{ $repairRequest->end_user_signature }}">
                                    @else
                                        <canvas id="endUserSignatureCanvas" class="signature-pad" width="220" height="48"></canvas>
                                        <input type="hidden" id="endUserSignature" name="endUserSignature">
                                    @endif
                                </div>

                                <div class="signature-controls {{ ($isView || $isUpdate || !$canEditEndUser) ? 'hidden' : '' }}">
                                    <button type="button" class="btn-clear-signature" data-canvas="endUserSignatureCanvas" data-input="endUserSignature">
                                        Clear
                                    </button>
                                </div>

                                <div class="signature-line"></div>

                                <input type="text" id="endUserPrintedName" name="endUserPrintedName"
                                    placeholder="Printed Name" class="signature-name-input" value="{{ $repairRequest->end_user_printed_name ?? Auth::user()->full_name ?? '' }}" {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>
                            </div>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="endUserDate" class="required">Date:</label>
                            <input type="date" id="endUserDate" name="endUserDate" value="{{ fmtDate($repairRequest->end_user_date ?? null) ?: date('Y-m-d') }}" required {{ ($isView || !$canEditEndUser) ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>
            </div> <!-- End of endUserSection -->


            <!-- SECTION 2 & 3: IT PERSONNEL -->
            <div class="section-header" id="itPersonnelSectionHeader">
                <h3>TO BE FILLED-UP BY IT PERSONNEL</h3>
            </div>

            <div id="itPersonnelSection" class="{{ (!$isAdmin || $isView) ? 'disabled-section' : '' }}">

                <div class="form-row compact-row">
                    <div class="form-col">
                        <div class="form-group compact">
                            <label>RECEIVED by:</label>
                                @php
                                    $autoItName = '';
                                    $autoItLastName = '';
                                    $autoItFirstName = '';
                                    $autoItMiddleName = '';
                                    if ($canEditTechnician && empty($repairRequest->it_received_last_name)) {
                                        $autoItName = Auth::user()->full_name;
                                        $nameParts = explode(' ', trim($autoItName));
                                        $autoItLastName = count($nameParts) > 1 ? array_pop($nameParts) : $autoItName;
                                        $autoItMiddleName = '';
                                        if (count($nameParts) > 1) {
                                            $middleWord = array_pop($nameParts);
                                            $autoItMiddleName = $middleWord;
                                        }
                                        $autoItFirstName = count($nameParts) > 0 ? implode(' ', $nameParts) : '';
                                    }
                                @endphp
                                <div class="form-row compact-row">
                                    <div class="form-col">
                                        <label class="inline-label">LAST NAME</label>
                                        <input type="text" id="itReceivedLastName" name="itReceivedLastName" value="{{ $repairRequest->it_received_last_name ?? $autoItLastName }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                                    </div>
                                    <div class="form-col">
                                        <label class="inline-label">FIRST NAME</label>
                                        <input type="text" id="itReceivedFirstName" name="itReceivedFirstName" value="{{ $repairRequest->it_received_first_name ?? $autoItFirstName }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                                    </div>
                                </div>
                                <div class="form-group compact ict-mt-8">
                                    <input type="text" id="itReceivedMiddleName" name="itReceivedMiddleName"
                                        placeholder="MIDDLE NAME" value="{{ $repairRequest->it_received_middle_name ?? $autoItMiddleName }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                                </div>
                        </div>

                        <div class="form-group compact">
                            <label for="initialDiagnosis">Initial Diagnosis:</label>
                            <textarea id="initialDiagnosis" name="initialDiagnosis" rows="3" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>{{ $repairRequest->initial_diagnosis ?? '' }}</textarea>
                        </div>

                        <div class="form-group compact">
                            <label>Repair Type:</label>
                            @php
                                $repairTypes = json_decode($repairRequest->repair_type ?? '[]', true) ?: [];
                            @endphp
                            <div class="checkbox-group compact-checkbox">
                                @foreach(['INTERNAL REPAIR', 'EXTERNAL REPAIR', 'REFERRED TO SERVICE PROVIDER', 'WITHIN WARRANTY', 'BEYOND WARRANTY'] as $type)
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="repairType[]" value="{{ $type }}" class="repair-type-cb" data-triggers-sp="{{ $type === 'REFERRED TO SERVICE PROVIDER' ? '1' : '0' }}" {{ in_array($type, $repairTypes) ? 'checked' : '' }} {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                                        <span>{{ $type }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p id="referredSpBanner" class="ict-sp-banner {{ ($isUpdate && in_array('REFERRED TO SERVICE PROVIDER', $repairTypes ?? [])) ? 'ict-sp-banner-visible' : '' }}">
                                Referred to external <strong>Service Provider (SP)</strong>. Fill <strong>Section 4</strong> below, then <strong>Save / Update</strong> the ticket.
                            </p>
                        </div>

                        <div class="form-group compact">
                            <label for="itRemarks">ACTION TAKEN / RECOMMENDATION:</label>
                            <textarea id="itRemarks" name="itRemarks" rows="3" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>{{ $repairRequest->it_remarks ?? '' }}</textarea>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="serviceRequestNo">SERVICE REQUEST NO:</label>
                            <input type="text" id="serviceRequestNo" name="serviceRequestNo" value="{{ $repairRequest->service_request_no ?? ($request->display_number ?? $request->request_number ?? '') }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                        </div>

                        <div class="form-group compact">
                            <label for="rid">RID:</label>
                            <input type="text" id="rid" name="rid" value="{{ $repairRequest->rid ?? '' }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                        </div>

                        <div class="form-group compact">
                            <label for="dateReceived">DATE RECEIVED:</label>
                            <input type="date" id="dateReceived" name="dateReceived" value="{{ fmtDate($repairRequest->date_received ?? null) }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                        </div>

                        <div class="form-group compact">
                            <label for="serviceScheduleDate">SERVICE SCHEDULE DATE:</label>
                            <input type="date" id="serviceScheduleDate" name="serviceScheduleDate" value="{{ fmtDate($repairRequest->service_schedule_date ?? null) }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                        </div>

                        <div class="form-group compact">
                            <label for="propertyNo">PROPERTY NO:</label>
                            <input type="text" id="propertyNo" name="propertyNo" value="{{ $repairRequest->property_no ?? '' }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                        </div>

                        <div class="form-group compact">
                            <label for="articleSerialNo">ARTICLE / SERIAL NO.:</label>
                            <input type="text" id="articleSerialNo" name="articleSerialNo" value="{{ $repairRequest->article_serial_no ?? '' }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                        </div>

                        <div class="form-group compact">
                            <label for="officeDateAcquired">OFFICE / DATE ACQUIRED:</label>
                            <input type="text" id="officeDateAcquired" name="officeDateAcquired" value="{{ $repairRequest->office_date_acquired ?? '' }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>
            </div> <!-- End of itPersonnelSection -->

            @if($isUpdate)
            @php
                $isReferredToSp = in_array('REFERRED TO SERVICE PROVIDER', $repairTypes ?? []);
                $spSectionActive = $isReferredToSp || \App\Support\RequestAuthorization::isServiceProviderSectionInUse($repairRequest);
            @endphp
            <div class="section-header" id="serviceProviderSectionHeader">
                <h3>TO BE FILLED-UP BY SERVICE PROVIDER</h3>
                @if($isUser)
              
                @endif
            </div>

            <div id="serviceProviderSection" data-keep-active="{{ ($spSectionActive && !$isReferredToSp) ? '1' : '0' }}" class="{{ (!$spSectionActive || !$isAdmin || $isView) ? 'disabled-section' : '' }}">

                <div class="form-row compact-row">
                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="serviceDate">SERVICE DATE:</label>
                            <input type="date" id="serviceDate" name="serviceDate" value="{{ fmtDate($repairRequest->service_date ?? null) }}" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="pulloutDate">PULL-OUT DATE:</label>
                            <input type="date" id="pulloutDate" name="pulloutDate" value="{{ fmtDate($repairRequest->pullout_date ?? null) }}" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>

                <div class="form-row compact-row">
                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="companyName">COMPANY NAME:</label>
                            <input type="text" id="companyName" name="companyName" value="{{ $repairRequest->company_name ?? '' }}" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="companyPhone">COMPANY PHONE:</label>
                            <input type="text" id="companyPhone" name="companyPhone" value="{{ $repairRequest->company_phone ?? '' }}" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>

                <div class="form-row compact-row">
                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="companyAddress">ADDRESS:</label>
                            <textarea id="companyAddress" name="companyAddress" rows="2" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>{{ $repairRequest->company_address ?? '' }}</textarea>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="companyEmail">COMPANY EMAIL:</label>
                            <input type="email" id="companyEmail" name="companyEmail" value="{{ $repairRequest->company_email ?? '' }}" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>

                <div class="form-group compact">
                    <label>NAME OF TECHNICIAN:</label>
                    <div class="form-row compact-row">
                        <div class="form-col">
                            <label class="inline-label">LAST NAME</label>
                            <input type="text" id="technicianLastName" name="technicianLastName" value="{{ $repairRequest->technician_last_name ?? '' }}" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>
                        </div>
                        <div class="form-col">
                            <label class="inline-label">FIRST NAME</label>
                            <input type="text" id="technicianFirstName" name="technicianFirstName" value="{{ $repairRequest->technician_first_name ?? '' }}" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>
                        </div>
                        <div class="form-col">
                            <label class="inline-label">MIDDLE NAME</label>
                            <input type="text" id="technicianMiddleName" name="technicianMiddleName" value="{{ $repairRequest->technician_middle_name ?? '' }}" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>

                <div class="form-row compact-row">
                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="actionTaken">ACTION TAKEN / RECOMMENDATION:</label>
                            <textarea id="actionTaken" name="actionTaken" rows="3" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>{{ $repairRequest->action_taken ?? '' }}</textarea>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group compact">
                            <label>Technician Signature over Printed Name:</label>
                            <div class="signature-wrapper">
                                <div class="signature-container">
                                    @if(!empty($repairRequest->technician_signature))
                                        <img src="/{{ $repairRequest->technician_signature }}" class="signature-preview" alt="Technician Signature">
                                        <input type="hidden" id="technicianSignature" name="technicianSignature" value="{{ $repairRequest->technician_signature }}">
                                    @else
                                        <canvas id="technicianSignatureCanvas" class="signature-pad" width="220" height="48"></canvas>
                                        <input type="hidden" id="technicianSignature" name="technicianSignature">
                                    @endif
                                </div>
                                <div class="signature-controls {{ (!$isAdmin || $isView || !$spSectionActive) ? 'hidden' : '' }}">
                                    <button type="button" class="btn-clear-signature" data-canvas="technicianSignatureCanvas" data-input="technicianSignature">Clear</button>
                                </div>
                                <div class="signature-line"></div>
                                <input type="text" id="technicianPrintedName" name="technicianPrintedName"
                                    placeholder="Printed Name" class="signature-name-input" value="{{ $repairRequest->technician_printed_name ?? '' }}" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>
                            </div>
                        </div>
                        <div class="form-group compact">
                            <label for="technicianDate">Date:</label>
                            <input type="date" id="technicianDate" name="technicianDate" value="{{ fmtDate($repairRequest->technician_date ?? null) }}" {{ (!$isAdmin || $isView || !$spSectionActive) ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>
            </div> <!-- End of serviceProviderSection -->
            @endif

            <!-- SECTION 5: TO BE FILLED-UP BY IT PERSONNEL (AFTER REPAIR) -->
            <div class="section-header" id="itPersonnelAfterRepairHeader">
                <h3>TO BE FILLED-UP BY IT PERSONNEL</h3>
            </div>

            <div id="itPersonnelAfterRepairSection" class="{{ (!$isAdmin || $isView) ? 'disabled-section' : '' }}">


                <div class="form-row compact-row">
                    <div class="form-col">
                        <div class="form-group compact">
                            <label>AFTER REPAIR:</label>
                            <div class="radio-group compact-radio">
                                <label class="radio-label {{ ($repairRequest->after_repair_status ?? '') == 'COMPLETED' ? 'radio-checked' : '' }}">
                                    <input type="radio" name="afterRepairStatus" value="COMPLETED" {{ ($repairRequest->after_repair_status ?? '') == 'COMPLETED' ? 'checked' : '' }} {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                                    <span>COMPLETED</span>
                                </label>
                                <label class="radio-label {{ ($repairRequest->after_repair_status ?? '') == 'FOR DISPOSAL' ? 'radio-checked' : '' }}">
                                    <input type="radio" name="afterRepairStatus" value="FOR DISPOSAL" {{ ($repairRequest->after_repair_status ?? '') == 'FOR DISPOSAL' ? 'checked' : '' }} {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                                    <span>FOR DISPOSAL</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="afterServiceDate">AFTER SERVICE DATE:</label>
                            <input type="date" id="afterServiceDate" name="afterServiceDate" value="{{ fmtDate($repairRequest->after_service_date ?? null) }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="repairCost">REPAIR COST (₱):</label>
                            <input type="number" id="repairCost" name="repairCost" step="0.01" min="0" placeholder="0.00" value="{{ old('repairCost', $repairRequest->cost ?? '') }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }} class="ict-cost-input">
                        </div>
                    </div>
                </div>

                <!-- FINDINGS / REMARKS (MOVED UP, FULL WIDTH) -->
                <div class="form-group compact">
                    <label for="findingsRemarks">FINDINGS / REMARKS:</label>
                    <textarea id="findingsRemarks" name="findingsRemarks" rows="3" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>{{ $repairRequest->findings_remarks ?? '' }}</textarea>
                </div>

                <!-- SIGNATURE + DATE (BOTTOM PART) -->
                <div class="form-row compact-row">
                    <div class="form-col">
                        <div class="form-group compact">
                            <label>IT Personnel Signature over Printed Name:</label>
                            <div class="signature-wrapper">
                                <div class="signature-container">
                                    @if(!empty($repairRequest->it_personnel_signature))
                                        <img src="/{{ $repairRequest->it_personnel_signature }}" class="signature-preview" alt="Signature">
                                        <input type="hidden" id="itPersonnelSignature" name="itPersonnelSignature" value="{{ $repairRequest->it_personnel_signature }}">
                                    @else
                                        <canvas id="itPersonnelSignatureCanvas" class="signature-pad" width="220" height="48"></canvas>
                                        <input type="hidden" id="itPersonnelSignature" name="itPersonnelSignature">
                                    @endif
                                </div>
                                <div class="signature-controls {{ (!$isAdmin || $isView) ? 'hidden' : '' }}">
                                    <button type="button" class="btn-clear-signature" data-canvas="itPersonnelSignatureCanvas" data-input="itPersonnelSignature">
                                        Clear
                                    </button>
                                </div>
                                <div class="signature-line"></div>
                                <input type="text" id="itPersonnelPrintedName" name="itPersonnelPrintedName"
                                    placeholder="Printed Name" class="signature-name-input" value="{{ $repairRequest->it_personnel_printed_name ?? ($canEditTechnician ? Auth::user()->full_name : '') }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                            </div>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="itPersonnelDate">Date:</label>
                            <input type="date" id="itPersonnelDate" name="itPersonnelDate" value="{{ fmtDate($repairRequest->it_personnel_date ?? null) }}" {{ (!$isAdmin || $isView) ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>
            </div> <!-- End of itPersonnelAfterRepairSection -->

            <!-- SECTION 6: END USER SERVICE ACCEPTANCE (enabled after IT signature in Section 5) -->
            <div class="section-header" id="endUserAcceptanceHeader">
                <h3>END-USER SERVICE ACCEPTANCE</h3>
                @if($isUser && $isUpdate)
                <p class="ict-section-header-note">
               
                </p>
                @endif
            </div>

            <div id="endUserAcceptanceSection" class="{{ !$canSignAcceptance ? 'disabled-section' : '' }}">

                @if($isUser && $isUpdate && !$canSignAcceptance && $acceptanceBlockReason)
                <div class="notice-box compact-notice ict-acceptance-block">
                    <p class="ict-acceptance-block-text"><i class="fa-solid fa-clock"></i> {{ $acceptanceBlockReason }}</p>
                </div>
                @endif

                <div class="notice-box compact-notice">
                    <p>I hereby acknowledge and agree that the service has been rendered successfully and to my
                        satisfaction.</p>
                </div>

                <div class="form-row compact-row">
                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="endUserAcceptanceSignature">End-User Signature over Printed Name:</label>
                            <div class="signature-wrapper">
                                <div class="signature-container">
                                    @if(!empty($repairRequest->end_user_acceptance_signature))
                                        <img src="/{{ $repairRequest->end_user_acceptance_signature }}" class="signature-preview" alt="Signature">
                                        <input type="hidden" id="endUserAcceptanceSignature" name="endUserAcceptanceSignature" value="{{ $repairRequest->end_user_acceptance_signature }}">
                                    @else
                                        <canvas id="endUserAcceptanceSignatureCanvas" class="signature-pad" width="220" height="48"></canvas>
                                        <input type="hidden" id="endUserAcceptanceSignature" name="endUserAcceptanceSignature">
                                    @endif
                                </div>
                                <div class="signature-controls {{ !$canSignAcceptance ? 'hidden' : '' }}">
                                    <button type="button" class="btn-clear-signature" data-canvas="endUserAcceptanceSignatureCanvas" data-input="endUserAcceptanceSignature">Clear</button>
                                </div>
                                <div class="signature-line"></div>
                                <input type="text" id="endUserAcceptancePrintedName" name="endUserAcceptancePrintedName"
                                    placeholder="Printed Name" class="signature-name-input" value="{{ $repairRequest->end_user_acceptance_printed_name ?? ($canSignAcceptance ? $user->full_name : '') }}" {{ !$canSignAcceptance ? 'disabled' : '' }}>
                            </div>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group compact">
                            <label for="endUserAcceptanceDate">Date:</label>
                            <input type="date" id="endUserAcceptanceDate" name="endUserAcceptanceDate" value="{{ fmtDate($repairRequest->end_user_acceptance_date ?? null) ?: date('Y-m-d') }}" {{ !$canSignAcceptance ? 'disabled' : '' }}>
                        </div>
                    </div>
                </div>

            </div>

            <div class="btn-group ict-btn-group">
                @php
                    $isRejectedResubmit = $isUpdate && isset($request) && $request && $request->status === 'Rejected' && $isUser;
                @endphp

                @if($isRejectedResubmit)
                    <button type="submit" id="submitBtn" class="btn-primary ict-btn-resubmit">
                        <i class="fa-solid fa-rotate-right"></i> Resubmit Request
                    </button>
                @elseif(!$isView && ($isAdmin || $canSignAcceptance || !$isUpdate))
                    <button type="submit" id="submitBtn" class="btn-primary"
                        @if($isNewUserRequest && !$hasAssignedAssets) disabled title="No accountable equipment assigned" @endif>
                        {{ $isUpdate ? 'Update Request' : 'Submit Request' }}
                    </button>
                @endif

                @if(Auth::user()->canProcessSupply())
                    @php
                        $prevUrl = url()->previous();
                        $backRoute = str_contains($prevUrl, 'requisitions')
                            ? route('requisitions.index', ['view' => 'tickets'])
                            : route('ict.index');
                    @endphp
                    <a href="{{ $backRoute }}" class="btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Back to List
                    </a>
                @else
                <a href="{{ route('ict.index') }}" class="btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Back to List
                </a>
                @endif

                {{-- Print PDF only when request is fully Completed --}}
                @if($isUpdate && $request->status === 'Completed')
                    <a href="{{ route('ict.pdf', $request->id) }}" target="_blank" class="btn-secondary ict-btn-pdf">
                        <i class="fa-solid fa-file-pdf"></i> Print / Download PDF
                    </a>
                @endif

                {{-- Print Disposal Tag: IT and Super Admin only when asset is For Disposal --}}
                @if($isUpdate && $request->linkedAsset && $request->linkedAsset->status === 'For Disposal' && in_array(Auth::user()->role, ['it', 'super_admin']))
                    <a href="{{ route('ict.disposal-tag', $request->id) }}" target="_blank" class="btn-secondary ict-btn-disposal">
                        <i class="fa-solid fa-tag"></i> Print Disposal Tag
                    </a>
                @endif
            </div>

        </form>
    </div>

    @include('requests.ict.partials._modal_qr_scanner')


    {{-- ── Data islands for JS auto-fill ──────────────────────────────────── --}}
    <script nonce="{{ $cspNonce }}">
        // IT user parsed name (only populated when role === 'it')
        const IT_USER_PARTS = @json($itUserParts);

        // Linked asset data (only populated when ticket has a linked asset)
        const LINKED_ASSET = @json($linkedAssetData);
        
        // ICT Assets map for auto-fill on asset selection
        const ICT_ASSETS_MAP = @json($ictAssetsMap ?? []);
        
        const HAS_ASSIGNED_ASSETS = @json($hasAssignedAssets ?? true);
        const IS_NEW_USER_REQUEST = @json($isNewUserRequest ?? false);
        const IS_SUPER_ADMIN = @json($isSuperAdmin ?? false);
        const PRESELECTED_ASSET_ID = @json($preselectedAssetId ?? null);
    </script>

    <script nonce="{{ $cspNonce }}">
        const signaturePads = {};

        function initSignaturePad(canvasId, hiddenInputId) {
            const canvas = document.getElementById(canvasId);
            const hiddenInput = document.getElementById(hiddenInputId);

            if (!canvas || !hiddenInput) return;
            if (canvas.closest('.disabled-section')) return;

            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;

            ctx.strokeStyle = '#000';
            ctx.lineWidth = 1.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            ctx.fillStyle = '#fafafa';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const getPos = (e) => {
                const rect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            };

            const startDrawing = (e) => {
                isDrawing = true;
                const pos = getPos(e);
                lastX = pos.x;
                lastY = pos.y;
            };

            const draw = (e) => {
                if (!isDrawing) return;
                e.preventDefault();
                const pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                lastX = pos.x;
                lastY = pos.y;
                hiddenInput.value = canvas.toDataURL('image/png');
            };

            const stopDrawing = () => {
                isDrawing = false;
            };

            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            window.addEventListener('mouseup', stopDrawing);

            canvas.addEventListener('touchstart', startDrawing, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', stopDrawing);
            canvas.addEventListener('touchcancel', stopDrawing);
            
            // Prevent scrolling while signing
            canvas.style.touchAction = 'none';
        }

        function clearSignature(canvasId, hiddenInputId) {
            const canvas = document.getElementById(canvasId);
            const hiddenInput = document.getElementById(hiddenInputId);
            if (canvas && hiddenInput) {
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fafafa';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                hiddenInput.value = '';
            }
        }

        // ── IT Auto-fill logic ───────────────────────────────────────────────
        function autoFillItSection() {
            // Fill IT personnel name only when the fields are empty (first open, not on re-edit)
            if (IT_USER_PARTS && IT_USER_PARTS.last_name) {
                const lastEl   = document.getElementById('itReceivedLastName');
                const firstEl  = document.getElementById('itReceivedFirstName');
                const middleEl = document.getElementById('itReceivedMiddleName');

                if (lastEl  && !lastEl.value)  lastEl.value  = IT_USER_PARTS.last_name;
                if (firstEl && !firstEl.value) firstEl.value = IT_USER_PARTS.first_name;
                if (middleEl && !middleEl.value) middleEl.value = IT_USER_PARTS.middle_name;
            }

            if (LINKED_ASSET) {
                const cat   = (LINKED_ASSET.category || '').toLowerCase();
                const specs = LINKED_ASSET.specifications || {};

                // ARTICLE / SERIAL NO (serial_number only)
                const snEl = document.getElementById('articleSerialNo');
                if (snEl && !snEl.value && LINKED_ASSET.serial_number) {
                    snEl.value = LINKED_ASSET.serial_number;
                }

                // PROPERTY NO (property_number only)
                const propEl = document.getElementById('propertyNo');
                if (propEl && !propEl.value && LINKED_ASSET.property_number) {
                    propEl.value = LINKED_ASSET.property_number;
                }

                // OFFICE / DATE ACQUIRED
                const dateEl = document.getElementById('officeDateAcquired');
                if (dateEl && !dateEl.value && LINKED_ASSET.date_acquired) {
                    dateEl.value = LINKED_ASSET.date_acquired;
                }
            }
        }

        /**
         * Auto-fill ICT form fields when user selects an asset from the dropdown.
         * Fills: articleSerialNo, propertyNo, officeDateAcquired
         */
        function ictAutoFillFromAsset(assetId) {
            if (!assetId || !ICT_ASSETS_MAP[assetId]) return;

            const asset = ICT_ASSETS_MAP[assetId];
            console.log('DEBUG: ictAutoFillFromAsset called with assetId:', assetId);
            console.log('DEBUG: asset:', asset);

            // Auto-fill ARTICLE / SERIAL NO (from serial_number only)
            const snEl = document.getElementById('articleSerialNo');
            if (snEl && asset.serial_number) {
                snEl.value = asset.serial_number;
                console.log('DEBUG: Filled articleSerialNo with:', asset.serial_number);
            }

            // Auto-fill PROPERTY NO (from property_number only)
            const propEl = document.getElementById('propertyNo');
            if (propEl && asset.property_number) {
                propEl.value = asset.property_number;
                console.log('DEBUG: Filled propertyNo with:', asset.property_number);
            }

            // Auto-fill OFFICE / DATE ACQUIRED
            const dateEl = document.getElementById('officeDateAcquired');
            if (dateEl && asset.date_acquired) {
                dateEl.value = asset.date_acquired;
                console.log('DEBUG: Filled officeDateAcquired with:', asset.date_acquired);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initSignaturePad('endUserSignatureCanvas', 'endUserSignature');
            initSignaturePad('technicianSignatureCanvas', 'technicianSignature');
            initSignaturePad('itPersonnelSignatureCanvas', 'itPersonnelSignature');
            initSignaturePad('endUserAcceptanceSignatureCanvas', 'endUserAcceptanceSignature');

            // Auto-fill IT section on load
            autoFillItSection();

            // Asset dropdown auto-fill
            const assetSelect = document.getElementById('linked_asset_id');
            if (assetSelect) {
                assetSelect.addEventListener('change', function () {
                    ictAutoFillFromAsset(this.value);
                });
            }

            // Radio styling
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const group = this.name;
                    document.querySelectorAll(`input[name="${group}"]`).forEach(r => {
                        r.closest('.radio-label').classList.remove('radio-checked');
                    });
                    this.closest('.radio-label').classList.add('radio-checked');
                });
            });

            // Service Provider checkbox wiring
            document.querySelectorAll('.repair-type-cb').forEach(cb => {
                cb.addEventListener('change', function () {
                    const spBanner = document.getElementById('referredSpBanner');
                    const spSection = document.getElementById('serviceProviderSection');
                    const anySpChecked = document.querySelector('.repair-type-cb[data-triggers-sp="1"]:checked');
                    if (spBanner) spBanner.style.display = anySpChecked ? 'block' : 'none';
                    if (spSection) {
                        const keepActive = spSection.dataset.keepActive === '1';
                        if (anySpChecked || keepActive) {
                            spSection.classList.remove('disabled-section');
                            spSection.querySelectorAll('input, textarea, select').forEach(el => el.removeAttribute('disabled'));
                        } else {
                            spSection.classList.add('disabled-section');
                            spSection.querySelectorAll('input, textarea, select').forEach(el => el.setAttribute('disabled', 'disabled'));
                        }
                    }
                });
            });

            // Super Admin self-assign via dropdown
            const assignItSelect = document.getElementById('assignItSelect');
            const currentUserId = '{{ Auth::user()->id }}';
            
            if (assignItSelect) {
                const toggleItSection = () => {
                    const itSection = document.getElementById('itPersonnelAfterRepairSection');
                    const isSelfAssigned = assignItSelect.value === currentUserId;
                    
                    // Only the assigned IT/Admin personnel can edit Section 5
                    // Super Admin must assign themselves first before they can edit
                    if (isSelfAssigned) {
                        // Enable IT section when self-assigned
                        itSection.classList.remove('disabled-section');
                        itSection.querySelectorAll('input, textarea, select').forEach(el => el.removeAttribute('disabled'));
                        itSection.querySelectorAll('.signature-controls').forEach(el => el.classList.remove('hidden'));
                    } else {
                        // Disable IT section for all other cases (must be assigned first)
                        itSection.classList.add('disabled-section');
                        itSection.querySelectorAll('input, textarea, select').forEach(el => el.setAttribute('disabled', 'disabled'));
                        itSection.querySelectorAll('.signature-controls').forEach(el => el.classList.add('hidden'));
                    }
                };
                
                assignItSelect.addEventListener('change', toggleItSection);
                toggleItSection(); // Initialize on load
            }

            // Form submission via AJAX
            const form = document.getElementById('repairRequestForm');
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const isNewRequest = !form.querySelector('input[name="_method"]');
                if (IS_NEW_USER_REQUEST && !HAS_ASSIGNED_ASSETS) {
                    Swal.fire({
                        icon: 'error',
                        title: 'No Assigned Equipment',
                        text: 'You cannot submit until the Administrative supply admin assigns accountable equipment to your account.',
                        confirmButtonColor: '#0038A8'
                    });
                    return;
                }
                if (isNewRequest) {
                    const assetSel = document.getElementById('linked_asset_id');
                    if (assetSel && !assetSel.disabled && !assetSel.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Asset Required',
                            text: 'Please select the device or asset to be repaired.',
                            confirmButtonColor: '#0038A8'
                        });
                        return;
                    }
                }

                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Submitting...';
                }

                const formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            confirmButtonColor: '#0038A8'
                        }).then(() => {
                            if (data.print_url) {
                                window.open(data.print_url, '_blank');
                            }
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            }
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = form.querySelector('[name="_method"]') ? 'Update Request' : 'Submit Request';
                        }
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'An unexpected error occurred.' });
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = form.querySelector('[name="_method"]') ? 'Update Request' : 'Submit Request';
                    }
                });
            });

            // Assign IT button
            const assignItBtn = document.getElementById('assignItBtn');
            if (assignItBtn) {
                assignItBtn.addEventListener('click', function() {
                    const select = document.getElementById('assignItSelect');
                    const assignedTo = select ? select.value : '';
                    const url = '{{ $isUpdate ? route("ict.assign-it", $request->id) : "" }}';

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
                    });
                });
            }
            // Division Admin Review Buttons
            const divisionReviewBtns = document.querySelectorAll('.division-review-btn');
            if (divisionReviewBtns.length > 0) {
                divisionReviewBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        const status = this.getAttribute('data-status');
                        const notes = document.getElementById('divisionAdminNotes').value;
                        const url = '{{ $isUpdate ? route("ict.review", $request->id) : "" }}';

                        if (!url) return;

                        let confirmMessage = status === 'Approved' 
                            ? 'Approve and forward this request to Super Admin?' 
                            : 'Reject this request?';

                        Swal.fire({
                            title: 'Confirm Review',
                            text: confirmMessage,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: status === 'Approved' ? '#10b981' : '#ef4444',
                            confirmButtonText: 'Yes, ' + status
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch(url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    },
                                    body: JSON.stringify({
                                        status: status,
                                        notes: notes
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire({
                                            title: 'Success!',
                                            text: data.message,
                                            icon: 'success'
                                        }).then(() => {
                                            window.location.reload();
                                        });
                                    } else {
                                        Swal.fire('Error', data.message || 'Something went wrong.', 'error');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    Swal.fire('Error', 'Failed to submit review.', 'error');
                                });
                            }
                        });
                    });
                });
            }

        });
    </script>

    {{-- ── QR Scanner libraries & logic ──────────────────────────────────── --}}
    <script nonce="{{ $cspNonce }}" src="{{ asset('js/html5-qrcode.min.js') }}"></script>
    <script nonce="{{ $cspNonce }}" src="{{ asset('js/qr-scanner.js') }}?v={{ time() }}"></script>
    <script nonce="{{ $cspNonce }}">
    (function() {
        // ── URL param auto-select ────────────────────────────────────────
        const urlParams = new URLSearchParams(window.location.search);
        const assetIdFromUrl = urlParams.get('asset_id');

        // If redirect from scan, use param; otherwise use PRESELECTED_ASSET_ID from server
        const preselectedId = assetIdFromUrl || (typeof PRESELECTED_ASSET_ID !== 'undefined' ? PRESELECTED_ASSET_ID : null);

        if (preselectedId) {
            const select = document.getElementById('linked_asset_id');
            if (select) {
                const option = select.querySelector('option[value="' + preselectedId + '"]');
                if (option) {
                    select.value = preselectedId;
                    // ictAutoFillFromAsset is defined globally in the script block above.
                    // We call it directly here because this IIFE runs before DOMContentLoaded
                    // fires, so the 'change' event listener hasn't been attached yet.
                    if (typeof ictAutoFillFromAsset === 'function') {
                        ictAutoFillFromAsset(parseInt(preselectedId, 10));
                    }
                    // Also dispatch the event so any other listeners can react
                    const event = new Event('change', { bubbles: true });
                    select.dispatchEvent(event);
                } else {
                    // Asset not in the list (status filtered out) — show friendly warning
                    console.warn('Pre-selected asset ID ' + preselectedId + ' not found in asset dropdown. It may be For Repair or already linked.');
                }
            }
        }

        // ── Scan button → camera modal ───────────────────────────────────
        const scanBtn = document.getElementById('scanQrBtn');
        const modal = document.getElementById('qrScannerModal');
        const cancelBtn = document.getElementById('cancelScanBtn');
        const readerEl = document.getElementById('scannerReader');
        const statusEl = document.getElementById('scannerStatus');

        if (!scanBtn || !modal) return;

        let scanner = null;
        let isScanning = false;

        const assetScanner = new AssetScanner({
            onScan: function(assetId) {
                handleScanResult(assetId);
            },
            onError: function(err) {
                statusEl.textContent = 'Camera error: ' + (err.message || err);
            }
        });

        function openScanner() {
            modal.style.display = 'flex';
            statusEl.textContent = 'Initializing camera...';

            if (!assetScanner.isCameraAvailable()) {
                statusEl.textContent = 'Camera not available on this device/browser. Try using the dropdown.';
                return;
            }

            isScanning = true;
            setTimeout(async () => {
                try {
                    await assetScanner.startCamera('scannerReader');
                    statusEl.textContent = 'Point your camera at the QR code...';
                } catch (err) {
                    statusEl.textContent = 'Failed to start camera: ' + (err.message || err);
                }
            }, 300);
        }

        function closeScanner() {
            if (isScanning) {
                assetScanner.stopCamera();
                isScanning = false;
            }
            modal.style.display = 'none';
        }

        function handleScanResult(assetId) {
            closeScanner();

            const select = document.getElementById('linked_asset_id');
            if (!select) return;

            const option = select.querySelector('option[value="' + assetId + '"]');
            if (option) {
                select.value = assetId;
                const event = new Event('change', { bubbles: true });
                select.dispatchEvent(event);
                Swal.fire({
                    icon: 'success',
                    title: 'Asset Found!',
                    text: select.options[select.selectedIndex].text,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Asset Not Found',
                    text: 'The scanned asset (ID: ' + assetId + ') is not in your assigned assets list.'
                });
            }
        }

        scanBtn.addEventListener('click', openScanner);
        cancelBtn.addEventListener('click', closeScanner);

        // Close on backdrop click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeScanner();
        });

        // Stop camera when modal closes
        window.addEventListener('beforeunload', function() {
            if (isScanning) assetScanner.stopCamera();
        });
    })();
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-canvas]');
        if (btn) {
            clearSignature(btn.dataset.canvas, btn.dataset.input);
        }
    });
    </script>
</x-form-layout>
