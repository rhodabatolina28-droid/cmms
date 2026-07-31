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
                $spSectionActive = $isReferredToSp || \App\Support\RequestHelpers::isServiceProviderSectionInUse($repairRequest);
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