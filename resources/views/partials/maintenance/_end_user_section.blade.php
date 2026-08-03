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
                                                <button type="button" class="btn-clear-sig-minimal" data-canvas="endUserSignatureCanvas" data-input="endUserSignature" onclick="clearSignature('endUserSignatureCanvas', 'endUserSignature'); return false;">Clear</button>
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
