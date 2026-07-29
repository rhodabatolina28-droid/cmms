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