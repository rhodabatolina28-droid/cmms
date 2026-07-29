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