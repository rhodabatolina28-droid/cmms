<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Satisfaction Measurement (CSM) — Survey Copy</title>
    <style>
        @page { size: A4 portrait; margin: 2mm; }
        body { font-family: Arial, sans-serif; font-size: 9.5px; color: #333; line-height: 1.3; padding: 6px 8px; }
        .form-container { padding: 4px 6px; }
        .header-bar { border-bottom: 2px solid #0f2a6b; padding-bottom: 4px; margin-bottom: 6px; }
        .header-bar table { width: 100%; border-collapse: collapse; }
        .header-bar td { border: none; vertical-align: middle; padding: 0; }
        .logo { width: 34px; height: 34px; }
        .agency { font-size: 13px; font-weight: bold; color: #0f2a6b; }
        .form-title { font-size: 8px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
        .form-body h1 { font-size: 11px; margin-bottom: 3px; }
        .form-body p { font-size: 8.5px; line-height: 1.3; margin-bottom: 3px; text-align: justify; }
        .consent-text { font-size: 8px; line-height: 1.3; margin-bottom: 4px; text-align: justify; }
        .form-row { width: 100%; margin-bottom: 1px; }
        .profile-table { width: 100%; border-collapse: collapse; }
        .profile-table td { padding: 2px 3px; vertical-align: middle; }
        .profile-table .p-label { font-weight: bold; white-space: nowrap; width: 22%; }
        .profile-table .p-value { width: 28%; }
        .required { color: #e11d48; }
        .value-box { display: inline-block; border-bottom: 1px solid #000; min-height: 12px; padding: 0 6px; font-weight: bold; min-width: 105px; font-size: 9px; }
        .divider { border: none; border-top: 1px solid #ccc; margin: 5px 0; }
        .survey-section { padding: 3px 5px; margin-bottom: 5px; }
        .survey-header { font-size: 9px; margin-bottom: 2px; }
        .cc-tag { font-weight: bold; margin-right: 5px; min-width: 34px; display: inline-block; }
        .survey-options { font-size: 9px; }
        .survey-options .opt { margin: 0.5px 0; }
        .cb { display: inline-block; width: 9.5px; height: 9.5px; border: 1px solid #000; margin-right: 4px; position: relative; top: 1.5px; text-align: center; line-height: 9.5px; font-size: 8.5px; font-weight: bold; }
        .cb.x:after { content: "✓"; }
        .survey-row table { width: 100%; border-collapse: collapse; }
        .survey-row td { width: 50%; border: none; padding: 1px 0; }
        .survey-row td + td { padding-left: 18px; }
        .sqd-table { width: 100%; border-collapse: collapse; }
        .sqd-table th, .sqd-table td { padding: 2px 3px; border: 1px solid #cbd5e1; text-align: center; vertical-align: middle; }
        .sqd-table th { font-size: 7px; font-weight: bold; text-transform: uppercase; height: 42px; }
        .sqd-table td:first-child { text-align: left; color: #333; font-size: 8.5px; width: 34%; }
        .emoji { width: 18px; height: 18px; }
        .chk { font-size: 10.5px; font-weight: bold; }
        .subcell { display: block; font-size: 6px; font-weight: normal; color: #666; line-height: 1.15; }
        .suggestion-label { font-weight: bold; font-size: 9px; margin: 3px 0 2px 0; }
        .fill-line { border-bottom: 1px solid #000; height: 14px; margin-bottom: 2px; font-size: 9px; }
        .footer { text-align: center; font-weight: bold; font-size: 8.5px; margin-top: 4px; border-top: 1.5px solid #0f2a6b; padding-top: 3px; color: #0f2a6b; }
        .subnote { text-align: center; font-size: 6.5px; color: #666; font-weight: normal; margin-top: 1px; }
    </style>
</head>
<body>
    <div class="form-container">
{{-- HEADER: logo + NCMB + title (replaces the web banner image) --}}
        <div class="header-bar">
            <table>
                <tr>
                    <td style="width:56px; text-align:left;">
                        @php $logo = public_path('images/ncmb-logo.svg'); @endphp
                        @if(file_exists($logo))
                            <img src="{{ 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logo)) }}" class="logo">
                        @endif
                    </td>
                    <td style="text-align:left;">
                        <div class="agency">NCMB</div>
                        <div class="form-title">Client Satisfaction Measurement (CSM) Survey Form</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="form-body">
            <h1>THANK YOU FOR GIVING US THE OPPORTUNITY TO SERVE YOU!</h1>
            <p>This Client Satisfaction Measurement (CSM) survey assesses customer experience in government offices. Your feedback on your recent transaction with us will help us improve our services to the public.</p>

            <div class="consent-text">
                <strong>CONSENT NOTICE:</strong><br>
                Personal information shared shall be kept confidential. The respondents have the option not to answer this form. By CLICKING "YES", the respondent grants his/her voluntary and absolute consent to the collection and processing of his/her personal data (as defined) and other information or records given/shared by him/her or by his/her authorized agent/s to the National Conciliation and Mediation Board (NCMB) and/or any of its authorized agent/s or representative/s solely for purposes relating to program implementation and reporting in accordance with Republic Act (RA) 10173, otherwise known as the 'Data Privacy Act of 2012' and its implementing Rules and Regulations (IRR). Furthermore, the respondent agrees to hold the NCMB free from any liability arising from the lawful disclosure and use of the collected data and information in accordance with relevant privacy laws and policies. The NCMB shall maintain strict confidentiality of the collected data and information, and retain these until the purpose for which they were collected has been achieved.<br><br>
                <strong><span class="cb x"></span> Yes, I agree</strong>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <span class="cb"></span> No, I do not agree
            </div>

            <div class="form-row">
                <table class="profile-table">
                    <tr>
                        <td class="p-label">Email address (optional):</td>
                        <td class="p-value" colspan="3"><span class="value-box" style="min-width:60%;">&nbsp;</span></td>
                    </tr>
                    <tr>
                        <td class="p-label">Age<span class="required"> *</span>:</td>
                        <td class="p-value"><span class="value-box">{{ $survey->age }}</span></td>
                        <td class="p-label">Sex<span class="required"> *</span>:</td>
                        <td class="p-value"><span class="value-box">{{ $survey->sex }}</span></td>
                    </tr>
                    <tr>
                        <td class="p-label">Office<span class="required"> *</span>:</td>
                        <td class="p-value"><span class="value-box">{{ $survey->request?->user?->office ?? '—' }}</span></td>
                        <td class="p-label">Client Type<span class="required"> *</span>:</td>
                        <td class="p-value"><span class="value-box">Government</span></td>
                    </tr>
                    <tr>
                        <td class="p-label">Service Availed<span class="required"> *</span>:</td>
                        <td class="p-value"><span class="value-box">{{ $survey->request?->type ?? '—' }}</span></td>
                        <td class="p-label">Date Availed<span class="required"> *</span>:</td>
                        <td class="p-value"><span class="value-box">{{ $survey->request?->created_at?->format('Y-m-d') ?? '—' }}</span></td>
                    </tr>
                </table>
            </div>

            <hr class="divider">
            {{-- CC1 --}}
            <div class="survey-section">
                <div class="survey-header"><span class="cc-tag">CC1</span><span class="cc-q">Which of the following best describes your awareness of a CC? <span class="required">*</span></span></div>
                @php
                    $cc1Options = [
                        '1' => 'I know what a CC is and I saw this office\'s CC.',
                        '2' => 'I know what a CC is but I did NOT see this office\'s CC.',
                        '3' => 'I learned of the CC only when I saw this office\'s CC.',
                        '4' => 'I do not know what a CC is and I did not see one in this office.',
                    ];
                @endphp
                <div class="survey-options">
                    @foreach($cc1Options as $val => $label)
                        <div class="opt"><span class="cb {{ (string)$survey->cc1 === $val ? 'x' : '' }}"></span>{{ $val }}. {{ $label }}</div>
                    @endforeach
                </div>
            </div>

            {{-- CC2 --}}
            <div class="survey-section">
                <div class="survey-header"><span class="cc-tag">CC2</span><span class="cc-q">If aware of CC (answered 1-3 in CC1), would you say that the CC of this office was ...? <span class="required">*</span></span></div>
                @php
                    $cc2Options = ['1' => 'Easy to see', '2' => 'Somewhat easy to see', '3' => 'Difficult to see', '4' => 'Not visible at all', '5' => 'N/A'];
                    $cc2Chunks = array_chunk($cc2Options, 3, true);
                @endphp
                <div class="survey-row">
                    <table>
                        @foreach($cc2Chunks as $i => $chunk)
                            <tr>
                                @foreach($chunk as $val => $label)
                                    <td><span class="cb {{ (string)$survey->cc2 === (string)$val ? 'x' : '' }}"></span>{{ $val }}. {{ $label }}</td>
                                @endforeach
                                @if(count($chunk) === 1)<td></td><td></td>@elseif(count($chunk) === 2)<td></td>@endif
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>

            {{-- CC3 --}}
            <div class="survey-section">
                <div class="survey-header"><span class="cc-tag">CC3</span><span class="cc-q">If aware of CC (answered 1-3 in CC1), how much did the CC help you in your transaction? <span class="required">*</span></span></div>
                @php
                    $cc3Options = ['1' => 'Helped very much', '2' => 'Somewhat helped', '3' => 'Did not help', '4' => 'N/A'];
                    $cc3Chunks = array_chunk($cc3Options, 2, true);
                @endphp
                <div class="survey-row">
                    <table>
                        @foreach($cc3Chunks as $i => $chunk)
                            <tr>
                                @foreach($chunk as $val => $label)
                                    <td><span class="cb {{ (string)$survey->cc3 === (string)$val ? 'x' : '' }}"></span>{{ $val }}. {{ $label }}</td>
                                @endforeach
                                @if(count($chunk) === 1)<td></td>@endif
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>

            @php
                $sqdQuestions = [
                    'sqd1' => 'SDQ0. I am satisfied with the service that I availed.',
                    'sqd2' => 'SDQ1. I spent a reasonable amount of time for my transaction.',
                    'sqd3' => 'SDQ2. The office followed the transaction\'s requirements and steps based on the information provided.',
                    'sqd4' => 'SDQ3. The steps (including payment) I needed to do for my transaction were easy and simple.',
                    'sqd5' => 'SDQ4. I easily found information about my transaction from the office\'s website.',
                    'sqd6' => 'SDQ5. I paid a reasonable amount of fees for my transaction.',
                    'sqd7' => 'SDQ6. I am confident my online transaction was secure.',
                    'sqd8' => 'SDQ7. The office\'s online support was available, and (if asked questions) online support was quick to respond.',
                    'sqd9' => 'SDQ8. I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me.',
                ];
                $faces = [
                    'Strongly Disagree' => 'strongly disagree.png',
                    'Disagree' => 'disagree.png',
                    'Neither Agree Nor Disagree' => 'neither agree nor disagree.png',
                    'Agree' => 'agree.png',
                    'Strongly Agree' => 'strongly agree.png',
                ];
            @endphp
            <table class="sqd-table">
                <thead>
                    <tr>
                        <th style="text-align:left; width:34%;">SQD Question</th>
                        @foreach($faces as $label => $img)
                            @php $facePath = public_path('csm/' . $img); @endphp
                            <th>
                                @if(file_exists($facePath))
                                    <img src="{{ 'data:image/png;base64,' . base64_encode(file_get_contents($facePath)) }}" class="emoji">
                                @endif
                                <span class="subcell">{{ $label }}</span>
                            </th>
                        @endforeach
                        <th>N/A<span class="subcell">Not Applicable</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sqdQuestions as $key => $question)
                        <tr>
                            <td>{{ $question }}</td>
                            @foreach(array_merge(array_keys($faces), ['N/A']) as $s)
                                <td class="chk">{{ strtolower($survey->{$key}) === strtolower($s) ? '✓' : '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <hr class="divider">

            <div class="suggestion-label">Suggestions on how we can further improve our services (optional):</div>
            <div class="fill-line">{{ $survey->suggestions ?: '&nbsp;' }}</div>
            <div class="fill-line">&nbsp;</div>
            <div class="fill-line">&nbsp;</div>

            <div class="footer">
                &mdash;&mdash;&mdash; END OF FORM &mdash;&mdash;&mdash;<br>
                <span class="subnote">Official archival copy (storage only)</span>
            </div>
        </div>
    </div>
</body>
</html>