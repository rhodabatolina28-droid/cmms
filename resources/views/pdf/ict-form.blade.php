<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ICT Repair Request</title>
    <style nonce="{{ $cspNonce }}">
        @page { size: A4 portrait; margin: 8mm 10mm 8mm 10mm; }
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 0; }

        /* Underline field (inline) */
        .f {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-height: 13px;
            vertical-align: bottom;
            padding: 0 2px;
        }

        /* Underline field (block - for text areas that may wrap) */
        .fb {
            border-bottom: 1px solid #000;
            min-height: 13px;
            padding: 0 2px;
            overflow: hidden;
            word-wrap: break-word;
        }

        /* Checkbox */
        .cb {
            display: inline-block;
            width: 11px;
            height: 11px;
            border: 1.2px solid #000;
            margin-right: 2px;
            position: relative;
            top: 2px;
            text-align: center;
            line-height: 11px;
            font-size: 9px;
        }
        .cb.x:after { content: "X"; font-weight: bold; }

        /* Section header */
        .sec {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            margin: 14px 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            background-color: #f3f4f6;
            padding: 4px;
            border: 1px solid #d1d5db;
        }

        /* Sublabel */
        .sub {
            font-size: 7px;
            text-align: center;
            display: block;
            margin-top: 1px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Small italic */
        .it {
            font-size: 7.5px;
            font-style: italic;
            text-align: justify;
            line-height: 1.25;
        }

        /* Bordered box */
        .bx { border: 1.2px solid #000; padding: 4px; }

        /* Signature block */
        .sig-block { text-align: center; }
        .sig-name {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            min-height: 14px;
        }
        .sig-line {
            border-bottom: 1px solid #000;
            width: 75%;
            margin: 0 auto;
            min-height: 3px;
        }
        .sig-img { max-width: 120px; max-height: 32px; }

        /* Label style */
        .lbl { font-size: 9px; text-transform: uppercase; letter-spacing: 0.2px; }
    
        .s74 { width: 125px; padding-right: 10px; }
        .s56 { width: 45%; }
        .s14 { white-space: nowrap; padding: 0 6px 0 0; width: 110px; }
        .s39 { width: 85px; vertical-align: top; padding-top: 3px; }
        .s60 { padding-top: 5px; }
        .s78 { width: 100%; border-collapse: collapse; margin-top: 2px; }
        .s70 { margin-top: 2px; }
        .s28 { margin: 0; }
        .s54 { width: 55%; padding-right: 10px; }
        .s31 { font-size: 8px; }
        .s51 { margin-bottom: 3px; font-size: 9px; }
        .s49 { padding: 2px 0; }
        .s10 { font-weight: bold; padding: 2px 0; text-align: left; vertical-align: bottom; white-space: nowrap; }
        .s20 { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .s59 { white-space: nowrap; width: 55px; }
        .s25 { margin-bottom: 15px; }
        .s18 { text-align: center; padding: 0 3px; width: 50px; }
        .s45 { width: 50px; vertical-align: top; padding-top: 3px; }
        .s11 { margin-bottom: 5px; width: 100%; }
        .s80 { margin-top: 8px; text-align: left; padding-left: 20%; }
        .s26 { width: 56%; padding-right: 12px; }
        .s81 { width: 130px; text-align: center; }
        .s6 { width: 55%; text-align: right; padding-right: 0; font-size: 8.5px; vertical-align: top; }
        .s66 { width: 150px; vertical-align: top; padding-top: 3px; font-size: 9px; }
        .s34 { border: 1.2px solid #000; margin-bottom: 15px; }
        .s76 { margin-bottom: 20px; }
        .s61 { margin-bottom: 8px; }
        .s69 { border-top: 1.5px solid #000; margin-top: 4px; }
        .s43 { padding-bottom: 5px; width: 34%; }
        .s16 { width: 100%; }
        .s53 { margin-bottom: 8px; font-size: 9px; width: 100%; }
        .s13 { font-size: 9px; width: 60%; margin-bottom: 5px; border-collapse: collapse; }
        .s72 { width: 95px; padding-right: 10px; }
        .s68 { width: 140px; text-align: center; }
        .s35 { width: 55%; padding: 4px 6px 4px 4px; border-right: 1.2px solid #000; }
        .s85 { border-bottom: 1px solid #000; width: 220px; margin: 0 auto; }
        .s29 { width: 44%; text-align: center; vertical-align: bottom; }
        .s27 { margin: 0 0 2px 0; }
        .s4 { width:32px; height:32px; vertical-align: middle; margin-right: 6px; }
        .s8 { width: 105px; font-weight: bold; padding: 2px 0; text-align: left; vertical-align: bottom; white-space: nowrap; }
        .s2 { margin-bottom: 12px; width: 100%; }
        .s24 { border-bottom: 1px solid #000; height: 16px; font-size: 9px; vertical-align: bottom; padding: 0 4px; }
        .s19 { font-size: 9px; margin-bottom: 5px; }
        .s33 { width: 150px; text-align: center; }
        .s71 { margin-bottom: 10px; font-size: 9.5px; }
        .s84 { width: 58%; text-align: center; }
        .s37 { width: 62px; font-size: 8px; }
        .s32 { margin-top: 8px; text-align: left; padding-left: 12%; }
        .s41 { font-size: 8.5px; }
        .s21 { width: 150px; font-size: 9px; vertical-align: top; padding-top: 3px; }
        .s12 { width: 100%; padding-right: 0; }
        .s50 { padding: 2px 0; white-space: nowrap; }
        .s3 { width: 45%; vertical-align: top; }
        .s15 { text-align: center; padding: 0 3px; }
        .s17 { font-size: 6.5px; }
        .s82 { color: #C00000; font-size: 11px; }
        .s87 { border-bottom: 1px solid #000; width: 130px; margin: 0 auto; text-align: center; min-height: 13px; }
        .s57 { white-space: nowrap; width: 90px; }
        .s46 { width: 45%; padding: 4px; }
        .s48 { padding: 2px 0; white-space: nowrap; width: 115px; }
        .s36 { margin-bottom: 3px; }
        .s83 { text-align: center; font-style: italic; font-size: 10px; margin-bottom: 35px; }
        .s9 { border-bottom: 1px solid #000; padding: 2px 2px 0 2px; text-align: left; vertical-align: bottom; }
        .s75 { width: 100px; }
        .s64 { margin-bottom: 15px; width: 100%; }
        .s47 { font-size: 9px; width: 100%; }
        .s58 { padding-top: 5px; padding-right: 10px; }
        .s22 { padding: 0; }
        .s77 { width: 50%; padding-right: 10px; font-size: 9px; }
        .s63 { text-align: center; padding: 0 4px; }
        .s73 { width: 110px; padding-right: 10px; }
        .s62 { width: 110px; font-size: 8.5px; }
        .s23 { width: 100%; border-collapse: collapse; }
        .s55 { white-space: nowrap; width: 85px; }
        .s42 { padding-bottom: 5px; width: 33%; }
        .s44 { font-size: 9px; }
        .s67 { margin-top: 5px; text-align: left; padding-left: 15%; }
        .s65 { width: 56%; padding-right: 12px; font-size: 9px; vertical-align: top; }
        .s1 { max-width:120px;max-height:32px; }
        .s38 { margin-bottom: 4px; font-size: 9px; }
        .s30 { min-height: 18px; }
        .s40 { margin-bottom: 5px; }
        .s7 { width: 280px; margin-left: auto; font-size: 8.5px; border-collapse: collapse; }
        .s5 { font-size: 18px; font-weight: bold; letter-spacing: 1.2px; vertical-align: middle; }
        .s79 { width: 50%; text-align: center; vertical-align: bottom; }
        .s52 { width: 135px; }
        .s86 { width: 42%; text-align: center; vertical-align: bottom; }
    </style>
</head>
<body>

    @php
        $rr = $repairRequest;
        $types = json_decode($rr->repair_type ?? '[]', true) ?: [];
        function sigImg($path) {
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

    {{-- ═══════════════════════ HEADER ═══════════════════════ --}}
    <table class="s2">
        <tr>
            <td class="s3">
                @php $logo = public_path('images/ncmb-logo.svg'); @endphp
                @if(file_exists($logo))
                    <img src="{{ 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logo)) }}" class="s4">
                @endif
                <span class="s5">NCMB ICT REPAIR REQUEST</span>
            </td>
            <td class="s6">
                <table class="s7">
                    <tr>
                        <td class="s8">DIVISION/OFFICE:</td>
                        <td class="s9">{{ $request->office }}</td>
                    </tr>
                    <tr>
                        <td class="s10">EMAIL:</td>
                        <td class="s9">{{ $rr->end_user_email ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="s10">EMPLOYEE NO.:</td>
                        <td class="s9">{{ $rr->employee_no ?? '' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ═══════════════════════ SECTION 1: END USER ═══════════════════════ --}}
<table class="s11">
    <tr>
        <td class="s12">
            <table class="s13">
                <tr>
                    <td class="s14"><strong>NAME OF END-USER:</strong></td>
                    <td class="s15"><div class="f s16">{{ $rr->end_user_last_name ?? '' }}</div><span class="sub s17">Last Name</span></td>
                    <td class="s15"><div class="f s16">{{ $rr->end_user_first_name ?? '' }}</div><span class="sub s17">First Name</span></td>
                    <td class="s18"><div class="f s16">{{ $rr->end_user_middle_name ?? '' }}</div><span class="sub s17">Middle Name</span></td>
                </tr>
            </table>

            <div class="s19">
                <strong>SEX:</strong> &nbsp;
                <span class="cb {{ ($rr->end_user_sex ?? '') === 'MALE' ? 'x' : '' }}"></span> MALE
                &nbsp;&nbsp;&nbsp;&nbsp;
                <span class="cb {{ ($rr->end_user_sex ?? '') === 'FEMALE' ? 'x' : '' }}"></span> FEMALE
            </div>
        </td>
    </tr>
</table>

<table class="s20">
    <tr>
        <td class="s21"><strong>Description of Repair Request:</strong></td>
        <td class="s22">
            <table class="s23">
                @php
                    $desc_lines = explode("\n", wordwrap($rr->repair_description ?? '', 95, "\n"));
                    while(count($desc_lines) < 3) { $desc_lines[] = ''; }
                @endphp
                @foreach(array_slice($desc_lines, 0, 3) as $line)
                    <tr>
                        <td class="s24">
                            {{ $line }}
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

    {{-- PRIVACY NOTICE + END-USER SIGNATURE --}}
    <table class="s25">
        <tr>
            <td class="s26">
                <p class="it s27"><strong>Privacy Notice:</strong> By accomplishing this form, you acknowledge and give your consent to the collection of your personal data. All personal information provided will be used exclusively for official purposes and will be handled in accordance with Republic Act No. 10173 also known as the Data Privacy Act of 2012.</p>
                <p class="it s28">*Waiver: Upon submission of request, the Requester is expected to have performed a data back-up, and secured the work area. RID personnel or the service provider should not be held liable for any data loss, breach of data privacy, loss of property and/or damage to property.</p>
            </td>
            <td class="s29">
                <div class="s30">
                    @if(!empty($rr->end_user_signature)) {!! sigImg($rr->end_user_signature) !!} @endif
                </div>
                <div class="sig-name">{{ $rr->end_user_printed_name ?? strtoupper($rr->end_user_first_name . ' ' . $rr->end_user_last_name) }}</div>
                <div class="sig-line"></div>
                <span class="sub s31">End-User Signature over Printed Name</span>
                <div class="s32">
                    Date: <span class="f s33">{{ $rr->end_user_date ? \Carbon\Carbon::parse($rr->end_user_date)->format('m/d/Y') : '' }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- ═══════════════════════ SECTION 2 & 3: IT PERSONNEL ═══════════════════════ --}}
    <div class="sec">To Be Filled-Up By IT Personnel</div>

    <table class="s34">
        <tr>
            {{-- LEFT COLUMN --}}
            <td class="s35">

                {{-- Received By --}}
                <table class="s36">
                    <tr>
                        <td class="s37 lbl">Received by:</td>
                        <td class="s15"><div class="f s16">{{ $rr->it_received_last_name ?? '' }}</div><span class="sub">Last Name</span></td>
                        <td class="s15"><div class="f s16">{{ $rr->it_received_first_name ?? '' }}</div><span class="sub">First Name</span></td>
                        <td class="s15"><div class="f s16">{{ $rr->it_received_middle_name ?? '' }}</div><span class="sub">Middle Name</span></td>
                    </tr>
                </table>

                {{-- Initial Diagnosis --}}
                <div class="s38">
                    <table class="s23">
                        <tr>
                            <td class="s39">Initial Diagnosis:</td>
                            <td class="s22">
                                <table class="s23">
                                    @php
                                        $diag_lines = explode("\n", wordwrap($rr->initial_diagnosis ?? '', 48, "\n"));
                                        while(count($diag_lines) < 3) { $diag_lines[] = ''; }
                                    @endphp
                                    @foreach(array_slice($diag_lines, 0, 3) as $line)
                                        <tr>
                                            <td class="s24">
                                                {{ $line }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>

                {{-- Repair Type --}}
                <div class="s40">
                    <table class="s41">
                        <tr>
                            <td class="s42"><span class="cb {{ in_array('INTERNAL REPAIR', $types) ? 'x' : '' }}"></span> INTERNAL REPAIR</td>
                            <td class="s42"><span class="cb {{ in_array('EXTERNAL REPAIR', $types) ? 'x' : '' }}"></span> EXTERNAL REPAIR</td>
                            <td class="s43"><span class="cb {{ in_array('REFERRED TO SERVICE PROVIDER', $types) ? 'x' : '' }}"></span> REFERRED TO SERVICE PROVIDER</td>
                        </tr>
                        <tr>
                            <td><span class="cb {{ in_array('WITHIN WARRANTY', $types) ? 'x' : '' }}"></span> WITHIN WARRANTY</td>
                            <td colspan="2"><span class="cb {{ in_array('BEYOND WARRANTY', $types) ? 'x' : '' }}"></span> BEYOND WARRANTY</td>
                        </tr>
                    </table>
                </div>

                {{-- Remarks --}}
                <div class="s44">
                    <table class="s23">
                        <tr>
                            <td class="s45">Remarks:</td>
                            <td class="s22">
                                <table class="s23">
                                    @php
                                        $rem_lines = explode("\n", wordwrap($rr->it_remarks ?? '', 54, "\n"));
                                        while(count($rem_lines) < 3) { $rem_lines[] = ''; }
                                    @endphp
                                    @foreach(array_slice($rem_lines, 0, 3) as $line)
                                        <tr>
                                            <td class="s24">
                                                {{ $line }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>

            </td>

            {{-- RIGHT COLUMN --}}
            <td class="s46">
                <table class="s47">
                    <tr><td class="s48">SERVICE REQUEST NO:</td><td class="s49"><div class="f s16">{{ $rr->service_request_no ?? $request->display_number ?? $request->request_number }}</div></td></tr>
                    <tr><td class="s50">RID:</td><td class="s49"><div class="f s16">{{ $rr->rid ?? '' }}</div></td></tr>
                    <tr><td class="s50">DATE RECEIVED:</td><td class="s49"><div class="f s16">{{ $rr->date_received ? \Carbon\Carbon::parse($rr->date_received)->format('m/d/Y') : '' }}</div></td></tr>
                    <tr><td class="s50">SERVICE SCHEDULE DATE:</td><td class="s49"><div class="f s16">{{ $rr->service_schedule_date ? \Carbon\Carbon::parse($rr->service_schedule_date)->format('m/d/Y') : '' }}</div></td></tr>
                    <tr><td class="s50">PROPERTY NO:</td><td class="s49"><div class="f s16">{{ $rr->property_no ?? '' }}</div></td></tr>
                    <tr><td class="s50">ARTICLE / SERIAL NO.:</td><td class="s49"><div class="f s16">{{ $rr->article_serial_no ?? '' }}</div></td></tr>
                    <tr><td class="s50">OFFICE / DATE ACQUIRED:</td><td class="s49"><div class="f s16">{{ $rr->office_date_acquired ?? '' }}</div></td></tr>
                </table>
            </td>
        </tr>
    </table>


    {{-- ═══════════════════════ SECTION 4: SERVICE PROVIDER ═══════════════════════ --}}
    <div class="sec">To Be Filled-Up By Service Provider</div>

    <div class="s51">
        Service Date: <span class="f s52">{{ $rr->service_date ? \Carbon\Carbon::parse($rr->service_date)->format('m/d/Y') : '' }}</span>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        Pull-out Date: <span class="f s52">{{ $rr->pullout_date ? \Carbon\Carbon::parse($rr->pullout_date)->format('m/d/Y') : '' }}</span>
    </div>

    <table class="s53">
        <tr>
            <td class="s54">
                <table class="s16"><tr><td class="s55">COMPANY NAME:</td><td><div class="f s16">{{ $rr->company_name ?? '' }}</div></td></tr></table>
            </td>
            <td class="s56">
                <table class="s16"><tr><td class="s57">COMPANY PHONE:</td><td><div class="f s16">{{ $rr->company_phone ?? '' }}</div></td></tr></table>
            </td>
        </tr>
        <tr>
            <td class="s58">
                <table class="s16"><tr><td class="s59">ADDRESS:</td><td><div class="f s16">{{ $rr->company_address ?? '' }}</div></td></tr></table>
            </td>
            <td class="s60">
                <table class="s16"><tr><td class="s57">COMPANY EMAIL:</td><td><div class="f s16">{{ $rr->company_email ?? '' }}</div></td></tr></table>
            </td>
        </tr>
    </table>

    <table class="s61">
        <tr>
            <td class="s62 lbl">Name of Technician:</td>
            <td class="s63"><div class="f s16">{{ $rr->technician_last_name ?? '' }}</div><span class="sub">Last Name</span></td>
            <td class="s63"><div class="f s16">{{ $rr->technician_first_name ?? '' }}</div><span class="sub">First Name</span></td>
            <td class="s63"><div class="f s16">{{ $rr->technician_middle_name ?? '' }}</div><span class="sub">Middle Name</span></td>
        </tr>
    </table>

    <table class="s64">
        <tr>
            <td class="s65">
                <table class="s23">
                    <tr>
                        <td class="s66">Action Taken / Recommendation:</td>
                        <td class="s22">
                            <table class="s23">
                                @php
                                    $act_lines = explode("\n", wordwrap($rr->action_taken ?? '', 38, "\n"));
                                    while(count($act_lines) < 3) { $act_lines[] = ''; }
                                @endphp
                                @foreach(array_slice($act_lines, 0, 3) as $line)
                                    <tr>
                                        <td class="s24">
                                            {{ $line }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="s29">
                <div class="s30">
                    @if(!empty($rr->technician_signature)) {!! sigImg($rr->technician_signature) !!} @endif
                </div>
                <div class="sig-name">{{ $rr->technician_printed_name ?? '' }}</div>
                <div class="sig-line"></div>
                <span class="sub s31">Technician Signature over Printed Name</span>
                <div class="s67">
                    Date: <span class="f s68">{{ $rr->technician_date ? \Carbon\Carbon::parse($rr->technician_date)->format('m/d/Y') : '' }}</span>
                </div>
            </td>
        </tr>
    </table>


    {{-- ═══════════════════════ SECTION 5: AFTER REPAIR ═══════════════════════ --}}
    <div class="s69"></div>
    <div class="sec s70">To Be Filled-Up By IT Personnel</div>

    <table class="s71">
        <tr>
            <td class="s72"><strong>AFTER REPAIR</strong></td>
            <td class="s73"><span class="cb {{ ($rr->after_repair_status ?? '') === 'COMPLETED' ? 'x' : '' }}"></span> COMPLETED</td>
            <td class="s74"><span class="cb {{ ($rr->after_repair_status ?? '') === 'FOR DISPOSAL' ? 'x' : '' }}"></span> FOR DISPOSAL</td>
            <td>AFTER SERVICE DATE: <span class="f s75">{{ $rr->after_service_date ? \Carbon\Carbon::parse($rr->after_service_date)->format('m/d/Y') : '' }}</span></td>
        </tr>
    </table>

    <table class="s76">
        <tr>
            {{-- LEFT: Findings --}}
            <td class="s77">
                <div class="s36">FINDINGS / REMARKS:</div>
                <table class="s78">
                    @php
                        $find_lines = explode("\n", wordwrap($rr->findings_remarks ?? '', 58, "\n"));
                        while(count($find_lines) < 3) { $find_lines[] = ''; }
                    @endphp
                    @foreach(array_slice($find_lines, 0, 3) as $line)
                        <tr>
                            <td class="s24">
                                {{ $line }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>
            {{-- RIGHT: Signature --}}
            <td class="s79">
                <div class="s30">
                    @if(!empty($rr->it_personnel_signature)) {!! sigImg($rr->it_personnel_signature) !!} @endif
                </div>
                <div class="sig-name">{{ $rr->it_personnel_printed_name ?? '' }}</div>
                <div class="sig-line"></div>
                <span class="sub s31">IT Personnel Signature over Printed Name</span>
                <div class="s80">
                    Date: <span class="f s81">{{ $rr->it_personnel_date ? \Carbon\Carbon::parse($rr->it_personnel_date)->format('m/d/Y') : '' }}</span>
                </div>
            </td>
        </tr>
    </table>


    {{-- ═══════════════════════ END USER ACCEPTANCE ═══════════════════════ --}}
    <div class="sec s82">End User Service Acceptance</div>

    <div class="s83">
        I hereby acknowledge and agree that the service has been rendered successfully and to my satisfaction.
    </div>

    <table class="s16">
        <tr>
            <td class="s84">
                <div class="s30">
                    @if(!empty($rr->end_user_acceptance_signature)) {!! sigImg($rr->end_user_acceptance_signature) !!} @endif
                </div>
                <div class="sig-name">{{ $rr->end_user_acceptance_printed_name ?? '' }}</div>
                <div class="s85"></div>
                <span class="sub s31">End-User Signature over Printed Name</span>
            </td>
            <td class="s86">
                <div class="s87">
                    {{ $rr->end_user_acceptance_date ? \Carbon\Carbon::parse($rr->end_user_acceptance_date)->format('m/d/Y') : '' }}
                </div>
                <span class="sub s31">Date</span>
            </td>
        </tr>
    </table>

</body>
</html>
