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
            // Flags from \App\Support\RequestHelpers::ictFormFlags() — isAdmin = can edit Sections 2–5 (admin or assigned IT)
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
                                <div class="signature-container" id="endUserSignatureContainer">
                                    @if(!empty($repairRequest->end_user_signature))
                                        <img src="/{{ $repairRequest->end_user_signature }}" class="signature-preview" alt="Signature" id="endUserSignatureImg">
                                        <input type="hidden" id="endUserSignature" name="endUserSignature" value="{{ $repairRequest->end_user_signature }}">
                                    @else
                                        <canvas id="endUserSignatureCanvas" class="signature-pad" width="220" height="48"></canvas>
                                        <input type="hidden" id="endUserSignature" name="endUserSignature">
                                    @endif
                                </div>

                                <div class="signature-controls {{ $isView ? 'hidden' : '' }}">
                                    @if(!empty($repairRequest->end_user_signature))
                                        <button type="button" class="btn-clear-signature" data-canvas="endUserSignatureCanvas" data-input="endUserSignature" data-action="resign">Re-sign</button>
                                    @else
                                        <button type="button" class="btn-clear-signature" data-canvas="endUserSignatureCanvas" data-input="endUserSignature">Clear</button>
                                    @endif
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


            @include('partials.ict._ict_form_sections')

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


    @include('partials.ict._ict_scripts')
</x-form-layout>