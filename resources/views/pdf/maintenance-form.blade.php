<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Preventive Maintenance Service Form</title>
    <style nonce="{{ $cspNonce }}">
        @page { size: legal portrait; margin: 6mm 8mm 6mm 8mm; }
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 9.5px; color: #1f2937; margin: 0; padding: 0; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        .f { border-bottom: 1.5px solid #000; display: inline-block; min-height: 14px; vertical-align: bottom; padding: 0 3px; font-size: 9px; }
        .fb { border-bottom: 1.5px solid #000; min-height: 14px; padding: 0 3px; overflow: hidden; word-wrap: break-word; font-size: 9px; }
        .cb { display: inline-block; width: 12px; height: 12px; border: 1.5px solid #000; margin-right: 2px; position: relative; top: 2px; text-align: center; line-height: 12px; font-size: 9px; }
        .cb.x:after { content: "X"; font-weight: bold; }
        .sec { text-align: center; font-weight: bold; font-size: 11px; margin: 2px 0 2px 0; text-transform: uppercase; letter-spacing: 0.5px; background: #f3f4f6; border: 1px solid #d1d5db; padding: 2px; }
        .sub { font-size: 7px; text-align: center; display: block; margin-top: 1px; text-transform: uppercase; color: #333; }
        .sig-name { font-weight: bold; font-size: 10px; text-transform: uppercase; min-height: 14px; }
        .sig-line { border-bottom: 1px solid #000; width: 80%; margin: 0 auto; min-height: 2px; }
        .hdr { font-size: 10px; font-weight: bold; text-align: center; background: #334155; color: #ffffff; padding: 3px 3px; border: 1.5px solid #000; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; margin-bottom: 1px; }
        .dt td, .dt th { border: 1px solid #000; padding: 3px 4px; font-size: 8.5px; }
        .dt th { background: #e8eef5; font-weight: bold; text-align: center; }
        .chk td { border: 1px solid #000; padding: 2px 3px; font-size: 7.5px; vertical-align: middle; }
        .chk th { border: 1px solid #000; padding: 2px 3px; font-size: 8px; font-weight: bold; text-align: center; background: #e8eef5; }
        .chk .eq { font-weight: bold; text-align: center; }
        .chk .no { text-align: center; font-weight: bold; }
        .chk .lb { display: inline-block; width: 72px; }
        .footer { text-align: center; font-size: 8px; color: #666; margin-top: 2px; border-top: 1px solid #ccc; padding-top: 1px; }
    
        .s16 { width: 55%; vertical-align: bottom; padding-right: 10px; }
        .s38 { width: 45px; }
        .s33 { margin-bottom: 1px; }
        .s17 { font-size: 8px; font-weight: bold; margin-bottom: 2px; }
        .s40 { width: 120px; }
        .s4 { width:40px; height:40px; vertical-align: middle; margin-right: 8px; }
        .s15 { width: 100%; margin-top: 2px; border-collapse: collapse; }
        .s43 { font-weight:bold; text-align:center; }
        .s27 { min-height: 36px; margin-top: 1px; }
        .s31 { text-align: center; font-size: 8px; font-weight: bold; margin-top: 2px; text-transform: uppercase; }
        .s30 { min-height: 14px; border-bottom: 1.5px solid #000; text-align: center; }
        .s39 { width: 45%; padding: 0; }
        .s41 { width: 25px; }
        .s28 { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        .s12 { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        .s5 { font-size: 20px; font-weight: bold; letter-spacing: 1px; vertical-align: middle; }
        .s23 { margin-top: 2px; font-weight: bold; }
        .s25 { width: 40%; padding: 4px 5px; }
        .s42 { width: 65px; }
        .s3 { width: 65%; vertical-align: middle; }
        .s21 { text-align: center; padding-right: 10px; }
        .s2 { margin-bottom: 3px; }
        .s29 { font-size: 7px; font-style: italic; margin-bottom: 1px; color: #555; line-height: 1.2; }
        .s13 { width: 75px; font-weight: bold; font-size: 9px; vertical-align: bottom; white-space: nowrap; padding-bottom: 2px; }
        .s6 { font-size: 16px; vertical-align: middle; }
        .s1 { max-width:100px;max-height:28px; }
        .s35 { margin: 2px 0 1px; font-weight: bold; }
        .s14 { border-bottom: 1.5px solid #000; font-size: 9px; vertical-align: bottom; padding: 0 4px 1px 4px; }
        .s11 { font-weight: bold; font-size: 9px; margin-bottom: 2px; text-decoration: underline; }
        .s36 { width: 55%; padding: 0; border-right: 1px solid #000; }
        .s9 { border: 1px solid #000; margin-bottom: 2px; }
        .s34 { min-height: 12px; margin-top: 1px; }
        .s18 { min-height: 16px; border-bottom: 1.5px solid #000; text-align: center; }
        .s19 { width: 45%; vertical-align: bottom; }
        .s10 { width: 60%; padding: 4px 5px; border-right: 1px solid #000; }
        .s20 { border-bottom: 1.5px solid #000; text-align: center; font-size: 8px; min-height: 14px; padding-bottom: 2px; }
        .s8 { width: 120px; text-align: center; font-weight: bold; }
        .s26 { font-weight: bold; }
        .s32 { margin-bottom: 1px; font-weight: bold; }
        .s7 { width: 35%; text-align: right; font-size: 11px; }
        .s22 { height: 10px; }
        .s24 { min-height: 12px; margin-top: 1px; }
        .s37 { width: 100px; }
    </style>
</head>
<body>
    @php
        $check = function($field) use ($tasks) {
            return (isset($tasks[$field]) && ($tasks[$field] === 'YES' || $tasks[$field] === 'on' || $tasks[$field] === '1' || $tasks[$field] == 1)) ? 'x' : '';
        };
        $intChk = function($label, $field, $value) use ($check) {
            return '<span class="lb">' . $label . '</span> <span class="cb ' . $check($field) . '"></span> ' . $value;
        };
        function pmSigImg($path) {
            if (!$path) return '';
            $full = storage_path('app/public/' . $path);
            $real = realpath($full);
            $allowed = realpath(storage_path('app/public/signatures'));
            if (!$real || !$allowed || strpos($real, $allowed) !== 0 || !file_exists($real)) {
                return '';
            }
            $ext = pathinfo($real, PATHINFO_EXTENSION);
            return '<img src="data:image/'.$ext.';base64,'.base64_encode(file_get_contents($real)).'" class="s1">';
        }
    @endphp

    {{-- HEADER --}}
    <table class="s2">
        <tr>
            <td class="s3">
                @php $logo = public_path('images/ncmb-logo.svg'); @endphp
                @if(file_exists($logo))
                    <img src="{{ 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logo)) }}" class="s4">
                @endif
                <span class="s5">NCMB</span>
                <span class="s6"> PREVENTIVE MAINTENANCE SERVICE FORM</span>
            </td>
            <td class="s7">
                No.: <span class="f s8">{{ $pm->form_no ?? $request->display_number ?? $request->request_number }}</span>
            </td>
        </tr>
    </table>

    {{-- SECTION 1: TECHNICIAN + ANALYSIS --}}
    <table class="s9">
        <tr>
            <td class="s10">
                <div class="s11">PREVENTIVE MAINTENANCE TECHNICIAN</div>
                <table class="s12">
                    <tr>
                        <td class="s13">FULL NAME:</td>
                        <td class="s14">{{ $pm->technician_name ?? '' }}</td>
                    </tr>
                </table>
                <table class="s15">
                    <tr>
                        <td class="s16">
                            <div class="s17">SIGNATURE:</div>
                            <div class="s18">
                                @if(!empty($pm->technician_signature)) {!! pmSigImg($pm->technician_signature) !!} @endif
                            </div>
                        </td>
                        <td class="s19">
                            <div class="s17">DATE:</div>
                            <div class="s20">
                                {{ $pm->technician_date ? \Carbon\Carbon::parse($pm->technician_date)->format('m/d/Y') : '' }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="s21">
                        </td>
                        <td>
                            <div class="s22"></div>
                        </td>
                    </tr>
                </table>
                <div class="s23">DEVICE PROBLEM & ISSUES ENCOUNTERED:</div>
                <div class="fb s24">{{ $pm->problem_description ?? '' }}</div>
            </td>
            <td class="s25">
                <div class="s11">TECHNICIAN ANALYSIS</div>
                <div class="s26">Diagnosis of the problem?</div>
                <div class="fb s27">{{ $pm->diagnosis ?? '' }}</div>
            </td>
        </tr>
    </table>

    {{-- SECTION 2: END USER + SUGGESTION --}}
    <table class="s9">
        <tr>
            <td class="s10">
                <div class="s11">END USER</div>
                <table class="s28">
                    <tr>
                        <td class="s13">FULL NAME:</td>
                        <td class="s14">{{ $pm->end_user_name ?: ($request->requestor_name ?: ($request->user->full_name ?? '')) }}</td>
                    </tr>
                </table>
                <table class="s12">
                    <tr>
                        <td class="s13">DIVISION:</td>
                        <td class="s14">{{ $pm->end_user_division ?: ($request->office ?: ($request->user->office ?? '')) }}</td>
                    </tr>
                </table>
                <div class="s29">Note: End-user must backup documents prior to PM. Technician is not liable for data loss.</div>
                <table class="s15">
                    <tr>
                        <td class="s16">
                            <div class="s17">SIGNATURE OVER PRINTED NAME:</div>
                            <div class="s30">
                                @if(!empty($pm->end_user_signature)) {!! pmSigImg($pm->end_user_signature) !!} @endif
                            </div>
                            <div class="s31">
                                {{ $pm->end_user_printed_name ?: ($pm->end_user_name ?: ($request->requestor_name ?: ($request->user->full_name ?? ''))) }}
                            </div>
                        </td>
                        <td class="s19">
                            <div class="s17">DATE SIGNED:</div>
                            <div class="s20">
                                {{ $pm->end_user_signature_date ? \Carbon\Carbon::parse($pm->end_user_signature_date)->format('m/d/Y') : '' }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="s25">
                <div class="s11">SUGGESTION / RECOMMENDATION</div>
                <div class="s32"><span class="cb {{ ($pm->for_disposal ?? '') == 'YES' ? 'x' : '' }}"></span> FOR DISPOSAL</div>
                <div class="s33">REASON: <div class="fb s34">{{ $pm->disposal_reason ?? '' }}</div></div>
                <div class="s35"><span class="cb {{ ($pm->for_repair ?? '') == 'YES' ? 'x' : '' }}"></span> FOR REPAIR</div>
                <div>PARTS FOR REPAIR/REPLACEMENT: <div class="fb s34">{{ $pm->repair_parts ?? '' }}</div></div>
            </td>
        </tr>
    </table>

    {{-- SECTION 3: DEVICE INFORMATION --}}
    <div class="hdr">DEVICE INFORMATION</div>
    <table class="s9">
        <tr>
            {{-- LEFT: Device List --}}
            <td class="s36">
                <table class="dt">
                    <tr><td class="s37">Desktop Brand:</td><td>{{ $pm->desktop_brand ?? '' }}</td><td class="s38">Model:</td><td>{{ $pm->desktop_model ?? '' }}</td></tr>
                    <tr><td>Desktop PNO:</td><td colspan="3">{{ $pm->desktop_pno ?? '' }}</td></tr>
                    <tr><td>Computer Name:</td><td colspan="3">{{ $pm->desktop_computer_name ?? '' }}</td></tr>
                    <tr><td>Monitor-1 PNO:</td><td colspan="3">{{ $pm->monitor1_pno ?? '' }}</td></tr>
                    <tr><td>Monitor Brand:</td><td>{{ $pm->monitor1_brand ?? '' }}</td><td>Model:</td><td>{{ $pm->monitor1_model ?? '' }}</td></tr>
                    @if($pm->monitor2_pno)
                    <tr><td>Monitor-2 PNO:</td><td colspan="3">{{ $pm->monitor2_pno ?? '' }}</td></tr>
                    <tr><td>Monitor Brand:</td><td>{{ $pm->monitor2_brand ?? '' }}</td><td>Model:</td><td>{{ $pm->monitor2_model ?? '' }}</td></tr>
                    @endif
                    <tr><td>Printer-1 PNO:</td><td colspan="3">{{ $pm->printer1_pno ?? '' }}</td></tr>
                    <tr><td>Printer Brand:</td><td>{{ $pm->printer1_brand ?? '' }}</td><td>Model:</td><td>{{ $pm->printer1_model ?? '' }}</td></tr>
                    @if($pm->printer2_pno)
                    <tr><td>Printer-2 PNO:</td><td colspan="3">{{ $pm->printer2_pno ?? '' }}</td></tr>
                    <tr><td>Printer Brand:</td><td>{{ $pm->printer2_brand ?? '' }}</td><td>Model:</td><td>{{ $pm->printer2_model ?? '' }}</td></tr>
                    @endif
                    <tr><td>UPS PNO:</td><td colspan="3">{{ $pm->ups_pno ?? '' }}</td></tr>
                    <tr><td>UPS Brand:</td><td>{{ $pm->ups_brand ?? '' }}</td><td>Model:</td><td>{{ $pm->ups_model ?? '' }}</td></tr>
                    <tr><td>Scanner PNO:</td><td colspan="3">{{ $pm->scanner_pno ?? '' }}</td></tr>
                    <tr><td>Scanner Brand:</td><td>{{ $pm->scanner_brand ?? '' }}</td><td>Model:</td><td>{{ $pm->scanner_model ?? '' }}</td></tr>
                    <tr><td>Laptop PNO:</td><td colspan="3">{{ $pm->laptop_pno ?? '' }}</td></tr>
                    <tr><td>Laptop Brand:</td><td>{{ $pm->laptop_brand ?? '' }}</td><td>Model:</td><td>{{ $pm->laptop_model ?? '' }}</td></tr>
                    <tr><td>Laptop Name:</td><td colspan="3">{{ $pm->laptop_computer_name ?? '' }}</td></tr>
                    <tr><td>Webcam Brand:</td><td>{{ $pm->webcam_brand ?? '' }}</td><td>Model:</td><td>{{ $pm->webcam_model ?? '' }}</td></tr>
                    <tr><td>Webcam PNO:</td><td colspan="3">{{ $pm->webcam_pno ?? '' }}</td></tr>
                    <tr><td>Speakers Brand:</td><td>{{ $pm->speakers_brand ?? '' }}</td><td>Model:</td><td>{{ $pm->speakers_model ?? '' }}</td></tr>
                    <tr><td>Speakers PNO:</td><td colspan="3">{{ $pm->speakers_pno ?? '' }}</td></tr>
                    <tr><td>Earphone Brand:</td><td>{{ $pm->earphone_brand ?? '' }}</td><td>Model:</td><td>{{ $pm->earphone_model ?? '' }}</td></tr>
                    <tr><td>Other Equip:</td><td>IP Phone</td><td>Brand:</td><td>GrandStream</td></tr>
                    <tr><td>Model / PNO:</td><td colspan="3">{{ $pm->other_equipment_model_pno ?? '' }}</td></tr>
                </table>
            </td>
            {{-- RIGHT: Specs --}}
            <td class="s39">
                <table class="dt">
                    <tr><th colspan="2">DESKTOP SPECS</th></tr>
                    <tr><td class="s40">CPU Capacity/Speed:</td><td>{{ $pm->desktop_cpu ?? '' }}</td></tr>
                    <tr><td>RAM Capacity:</td><td>{{ $pm->desktop_ram ?? '' }}</td></tr>
                    <tr><td>GPU Capacity:</td><td>{{ $pm->desktop_gpu ?? '' }}</td></tr>
                    <tr><td>OS Version:</td><td>{{ $pm->desktop_os ?? '' }}</td></tr>
                    <tr><td>HD-1 Type/Capacity:</td><td>{{ $pm->desktop_hd1 ?? '' }}</td></tr>
                    <tr><td>HD-2 Type/Capacity:</td><td>{{ $pm->desktop_hd2 ?? '' }}</td></tr>
                    <tr><td>MS Office Version:</td><td>{{ $pm->desktop_office ?? '' }}</td></tr>
                    <tr><td>Year Purchased:</td><td>{{ $pm->desktop_year_purchased ?? '' }}</td></tr>
                    <tr><th colspan="2">LAPTOP SPECS</th></tr>
                    <tr><td>CPU Capacity/Speed:</td><td>{{ $pm->laptop_cpu ?? '' }}</td></tr>
                    <tr><td>RAM Capacity:</td><td>{{ $pm->laptop_ram ?? '' }}</td></tr>
                    <tr><td>GPU Capacity:</td><td>{{ $pm->laptop_gpu ?? '' }}</td></tr>
                    <tr><td>OS Version:</td><td>{{ $pm->laptop_os ?? '' }}</td></tr>
                    <tr><td>HD-1 Type/Capacity:</td><td>{{ $pm->laptop_hd1 ?? '' }}</td></tr>
                    <tr><td>HD-2 Type/Capacity:</td><td>{{ $pm->laptop_hd2 ?? '' }}</td></tr>
                    <tr><td>MS Office Version:</td><td>{{ $pm->laptop_office ?? '' }}</td></tr>
                    <tr><th colspan="2">PRINTER SPECS</th></tr>
                    <tr><td>Printer-1 Type:</td><td>{{ $pm->printer1_type ?? '' }}</td></tr>
                    <tr><td>Printer-2 Type:</td><td>{{ $pm->printer2_type ?? '' }}</td></tr>
                    <tr><th colspan="2">EARPHONE SPECS</th></tr>
                    <tr><td>Brand/Model:</td><td>{{ $pm->earphone_brand_model ?? '' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- SECTION 4: MAINTENANCE TASK CHECKLIST --}}
    <div class="hdr">MAINTENANCE TASK CHECKLIST</div>
    <table class="chk">
        <thead>
            <tr>
                <th class="s41">NO.</th>
                <th class="s42">EQUIP</th>
                <th colspan="2">EXTERNAL TASK</th>
                <th colspan="2">INTERNAL TASK</th>
            </tr>
        </thead>
        <tbody>
            {{-- 1. DESKTOP --}}
            <tr>
                <td class="no" rowspan="6">1</td>
                <td class="eq" rowspan="6">DESKTOP</td>
                <td>CASE CLEAN-UP</td>
                <td><span class="cb {{ $check('desktopCaseCleanup') }}"></span> Yes</td>
                <td class="s43">DESKTOP</td>
                <td class="s43">UPS</td>
            </tr>
            <tr>
                <td>CABLE / PLUG CLEAN-UP</td>
                <td><span class="cb {{ $check('desktopCableCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('DATA BACK-UP:', 'desktopDataBackup', 'Yes') !!}</td>
                <td>{!! $intChk('CHARGING:', 'upsCharging', 'YES') !!}</td>
            </tr>
            <tr>
                <td>SYSTEM FAN CLEAN-UP</td>
                <td><span class="cb {{ $check('desktopSystemFanCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('RESTORE POINT:', 'desktopRestorePoint', 'Yes') !!}</td>
                <td>{!! $intChk('OVERLOAD:', 'upsOverload', 'NO') !!}</td>
            </tr>
            <tr>
                <td>CPU FAN CLEAN-UP</td>
                <td><span class="cb {{ $check('desktopCpuFanCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('WINDOWS UPDATE:', 'desktopWindowsUpdate', 'Yes') !!}</td>
                <td class="s43">IP PHONE</td>
            </tr>
            <tr>
                <td>MOTHER BOARD CLEAN-UP</td>
                <td><span class="cb {{ $check('desktopMotherboardCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('TEMP FILES:', 'desktopTempFiles', 'CLEAN') !!}</td>
                <td>{!! $intChk('UPDATED:', 'ipPhoneUpdated', 'YES') !!}</td>
            </tr>
            <tr>
                <td>PSU CLEAN-UP</td>
                <td><span class="cb {{ $check('desktopPsuCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('RECYCLE BIN:', 'desktopRecycleBin', 'CLEAN') !!}</td>
                <td></td>
            </tr>

            {{-- 2. MONITOR --}}
            <tr>
                <td class="no" rowspan="2">2</td>
                <td class="eq">MON-1</td>
                <td>SCREEN CLEAN-UP</td>
                <td><span class="cb {{ $check('monitorScreenCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('HDD DEFRAG:', 'desktopHddDefrag', 'Yes') !!}</td>
                <td></td>
            </tr>
            <tr>
                <td class="eq"></td>
                <td>CABLE / PLUG CLEAN-UP</td>
                <td><span class="cb {{ $check('monitorCableCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('HDD CHECK DISK:', 'desktopHddCheckDisk', 'Yes') !!}</td>
                <td></td>
            </tr>

            {{-- 3. PRINTER --}}
            <tr>
                <td class="no" rowspan="2">3</td>
                <td class="eq">PRINTER-1</td>
                <td>CASE CLEAN-UP</td>
                <td><span class="cb {{ $check('printerCaseCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('VIRUS SCAN:', 'desktopVirusScan', 'Yes') !!}</td>
                <td></td>
            </tr>
            <tr>
                <td class="eq"></td>
                <td>CABLE / PLUG CLEAN-UP</td>
                <td><span class="cb {{ $check('printerCableCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('START-UP FILE:', 'desktopStartupFile', 'CLEAN') !!}</td>
                <td></td>
            </tr>

            {{-- 4. KEYBOARD --}}
            <tr>
                <td class="no" rowspan="2">4</td>
                <td class="eq" rowspan="2">KEYBOARD</td>
                <td>KEY PAD CLEAN-UP</td>
                <td><span class="cb {{ $check('keyboardKeypadCleanup') }}"></span> Yes</td>
                <td class="s43">LAPTOP</td>
                <td></td>
            </tr>
            <tr>
                <td>CABLE / PLUG CLEAN-UP</td>
                <td><span class="cb {{ $check('keyboardCableCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('DATA BACK-UP:', 'laptopDataBackup', 'Yes') !!}</td>
                <td></td>
            </tr>

            {{-- 5. MOUSE --}}
            <tr>
                <td class="no">5</td>
                <td class="eq">MOUSE</td>
                <td>CLEAN-UP</td>
                <td><span class="cb {{ $check('mouseCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('RESTORE POINT:', 'laptopRestorePoint', 'Yes') !!}</td>
                <td></td>
            </tr>

            {{-- 6. UPS/AVR --}}
            <tr>
                <td class="no" rowspan="2">6</td>
                <td class="eq" rowspan="2">UPS/AVR</td>
                <td>CASE CLEAN-UP</td>
                <td><span class="cb {{ $check('upsCaseCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('WINDOWS UPDATE:', 'laptopWindowsUpdate', 'Yes') !!}</td>
                <td></td>
            </tr>
            <tr>
                <td>CABLE / PLUG CLEAN-UP</td>
                <td><span class="cb {{ $check('upsCableCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('TEMP FILES:', 'laptopTempFiles', 'CLEAN') !!}</td>
                <td></td>
            </tr>

            {{-- 7. SCANNER --}}
            <tr>
                <td class="no" rowspan="2">7</td>
                <td class="eq" rowspan="2">SCANNER</td>
                <td>CASE CLEAN-UP</td>
                <td><span class="cb {{ $check('scannerCaseCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('RECYCLE BIN:', 'laptopRecycleBin', 'CLEAN') !!}</td>
                <td></td>
            </tr>
            <tr>
                <td>CABLE / PLUG CLEAN-UP</td>
                <td><span class="cb {{ $check('scannerCableCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('HDD DEFRAG:', 'laptopHddDefrag', 'Yes') !!}</td>
                <td></td>
            </tr>

            {{-- 8. IP PHONE --}}
            <tr>
                <td class="no" rowspan="2">8</td>
                <td class="eq" rowspan="2">IP PHONE</td>
                <td>UNIT CLEAN-UP</td>
                <td><span class="cb {{ $check('ipPhoneUnitCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('HDD CHECK DISK:', 'laptopHddCheckDisk', 'Yes') !!}</td>
                <td class="s43">INKJET</td>
            </tr>
            <tr>
                <td>CABLE / PLUG CLEAN-UP</td>
                <td><span class="cb {{ $check('ipPhoneCableCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('SSD CHECK DISK:', 'laptopSsdCheckDisk', 'Yes') !!}</td>
                <td>{!! $intChk('INK LEVEL:', 'printerInkjetInkLevel', 'OK') !!}</td>
            </tr>

            {{-- 9. LAPTOP --}}
            <tr>
                <td class="no">9</td>
                <td class="eq">LAPTOP</td>
                <td>UNIT CLEAN-UP</td>
                <td><span class="cb {{ $check('laptopUnitCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('ENDPOINT SCAN:', 'laptopEndpointScan', 'Yes') !!}</td>
                <td>{!! $intChk('PRINT QUALITY:', 'printerInkjetPrintQuality', 'OK') !!}</td>
            </tr>

            {{-- 10. WEBCAM --}}
            <tr>
                <td class="no" rowspan="2">10</td>
                <td class="eq">WEBCAM</td>
                <td>UNIT CLEAN-UP</td>
                <td><span class="cb {{ $check('webcamUnitCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('VIRUS SCAN:', 'laptopVirusScan', 'Yes') !!}</td>
                <td class="s43">LASERJET</td>
            </tr>
            <tr>
                <td class="eq"></td>
                <td></td>
                <td></td>
                <td>{!! $intChk('START-UP FILE:', 'laptopStartupFile', 'CLEAN') !!}</td>
                <td>{!! $intChk('TONER:', 'printerLaserjetToner', 'OK') !!}</td>
            </tr>

            {{-- 11. SPEAKER --}}
            <tr>
                <td class="no">11</td>
                <td class="eq">SPEAKER</td>
                <td>UNIT CLEAN-UP</td>
                <td><span class="cb {{ $check('speakerUnitCleanup') }}"></span> Yes</td>
                <td>{!! $intChk('WIN DEFENDER:', 'laptopWindowsDefender', 'ON') !!}</td>
                <td>{!! $intChk('PRINT QUALITY:', 'printerLaserjetPrintQuality', 'OK') !!}</td>
            </td>
        </tbody>
    </table>

    <div class="footer">NCMB Information and Communications Technology Division | Preventive Maintenance Service Record</div>

</body>
</html>
