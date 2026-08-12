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
                                        @php $techSigPath = !empty($maintenance->technician_signature) ? (str_starts_with($maintenance->technician_signature, 'http') ? $maintenance->technician_signature : Storage::url($maintenance->technician_signature)) : ''; @endphp
                                        @if(!empty($maintenance->technician_signature))
                                            <img src="{{ $techSigPath }}" alt="Technician Signature" class="signature-preview-img" id="technicianSignatureImg">
                                            <input type="hidden" id="technicianSignature" name="technicianSignature" value="{{ $maintenance->technician_signature }}">
                                            @if(!$viewMode && $isAdmin)
                                                <button type="button" class="btn-clear-sig-minimal" id="technicianResignBtn">Re-sign</button>
                                            @endif
                                        @else
                                            <canvas id="technicianSignatureCanvas" class="signature-canvas" width="300" height="64"></canvas>
                                            <input type="hidden" id="technicianSignature" name="technicianSignature" value="">
                                            <button type="button" class="btn-clear-sig-minimal" data-canvas="technicianSignatureCanvas" data-input="technicianSignature">Clear</button>
                                        @endif
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
