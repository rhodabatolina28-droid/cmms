<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Satisfaction Measurement (CSM) â€” Survey Copy</title>
    <style>
        @page { size: A4 portrait; margin: 8mm 9mm 8mm 9mm; }
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 9px; color: #1f2937; margin: 0; padding: 0; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        .header-table { border-bottom: 2.5px solid #0f2a6b; margin-bottom: 6px; }
        .header-table td { border: none; padding-bottom: 5px; }
        .logo { width: 46px; height: 46px; vertical-align: middle; }
        .agency { font-size: 17px; font-weight: bold; color: #0f2a6b; letter-spacing: 0.5px; }
        .form-title { font-size: 10px; font-weight: bold; letter-spacing: 0.8px; text-transform: uppercase; }
        .sec { text-align: center; font-weight: bold; font-size: 9.5px; background: #0f2a6b; color: #ffffff; padding: 3px; text-transform: uppercase; letter-spacing: 1px; margin: 6px 0 3px 0; border: 1px solid #0f2a6b; }
        .intro { text-align: center; font-size: 9px; margin: 4px 0; }
        .consent-box { border: 1px solid #000; background: #f8fafc; padding: 6px 9px; margin: 5px 0; font-size: 8.5px; text-align: justify; }
        .consent-head { font-weight: bold; margin-bottom: 3px; font-size: 9px; }
        .cb { display: inline-block; width: 11px; height: 11px; border: 1.5px solid #000; margin-right: 5px; position: relative; top: 1.5px; text-align: center; line-height: 11px; font-size: 8.5px; font-weight: bold; }
        .cb.x:after { content: "X"; }
        .profile-label { font-weight: bold; }
        .nd-line { border-bottom: 1.5px solid #000; display: inline-block; min-height: 13px; padding: 0 10px; font-size: 9px; font-weight: bold; }
        .profile-table td { border: 1px solid #000; padding: 4px 8px; font-size: 9px; }
        .profile-table .lb { font-weight: bold; width: 24%; background: #e8eef5; }
        .legend { font-size: 7.5px; color: #475569; margin-top: 2px; }
    </style>
</head>
<body>

    {{-- HEADER (logo sa gilid, katulad ng ICT/PM forms; walang request number) --}}
    <table class="header-table">
        <tr>
            <td style="width:52px;">
                @php $logo = public_path('images/ncmb-logo.svg'); @endphp
                @if(file_exists($logo))
                    <img src="{{ 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logo)) }}" class="logo">
                @endif
            </td>
            <td>
                <div class="agency">NCMB</div>
                <div class="form-title">Client Satisfaction Measurement (CSM) Survey Form</div>
            </td>
        </tr>
    </table>

    <div class="intro">
        <strong>THANK YOU FOR GIVING US THE OPPORTUNITY TO SERVE YOU!</strong>
    </div>
    <div class="intro">
        Please help us improve the quality of our services by taking a few minutes to answer this survey.<br>
        This Client Satisfaction Measurement (CSM) survey assesses customer experience in government offices.
    </div>

    {{-- CONSENT NOTICE --}}
    <div class="consent-box">
        <div class="consent-head">CONSENT NOTICE:</div>
        Personal information shared shall be kept confidential. The respondents have the option not to answer this form.
        By CLICKING &quot;YES&quot;, the respondent grants his/her voluntary and absolute consent to the collection and
        processing of his/her personal data (as defined) and other information or records given/shared by him/her or by
        his/her authorized agent/s to the National Conciliation and Mediation Board (NCMB) and/or any of its authorized
        agent/s or representative/s solely for purposes relating to program implementation and reporting in accordance
        with Republic Act (RA) 10173, otherwise known as the &quot;Data Privacy Act of 2012&quot; and its implementing
        Rules and Regulations (IRR). Furthermore, the respondent agrees to hold the NCMB free from any liability arising
        from the lawful disclosure and use of the collected data and information in accordance with relevant privacy laws
        and policies. The NCMB shall maintain strict confidentiality of the collected data and information, and retain
        these until the purpose for which they were collected has been achieved.
        <div style="margin-top:5px;">
            <span class="cb x"></span> <strong>Yes, I agree</strong>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <span class="cb"></span> No, I do not agree
        </div>
    </div>

    <div class="sec">I. Respondent Profile</div>
    TABLE_PROFILE_MARK
    <table>
        <tr>
            <td style="width:50%;">
                <span class="profile-label">Age:</span> <span class="nd-line">{{ $survey->age }}</span>
            </td>
            <td>
                <span class="profile-label">Sex:</span> <span class="nd-line">{{ $survey->sex }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="profile-label">Office:</span> <span class="nd-line">{{ $survey->request?->user?->office ?? 'â€”' }}</span>
            </td>
            <td>
                <span class="profile-label">Service:</span> <span class="nd-line">{{ $survey->request?->type ?? 'â€”' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="profile-label">Date Availed:</span> <span class="nd-line">{{ $survey->request?->created_at?->format('m/d/Y') ?? 'â€”' }}</span>
            </td>
            <td></td>
        </tr>
    </table>
<div class="sec">II. Citizen's Charter (CC) Awareness</div>
    @php
        $cc1Options = ['1' => 'I know what a CC is and I saw this office\'s CC.', '2' => 'I know what a CC is but I did NOT see this office\'s CC.', '3' => 'I learned of the CC only when I saw this office\'s CC.', '4' => 'I do not know what a CC is and I did not see one in this office.'];
        $cc2Options = ['1' => 'Easy to see', '2' => 'Somewhat easy to see', '3' => 'Difficult to see', '4' => 'Not visible at all', '5' => 'N/A'];
        $cc3Options = ['1' => 'Helped very much', '2' => 'Somewhat helped', '3' => 'Did not help', '4' => 'N/A'];
    @endphp

    <div class="cc-q">CC1. Which of the following best describes your awareness of a CC? *</div>
    @foreach($cc1Options as $val => $label)
        <div class="cc-opt"><span class="cb {{ (string)$survey->cc1 === $val ? 'x' : '' }}"></span> {{ $label }}</div>
    @endforeach

    <div class="cc-q">CC2. If aware of CC (answered 1-3 in CC1), would you say that the CC of this office was ...? *</div>
    <table>
        @foreach(array_chunk($cc2Options, 3, true) as $chunk)
            <tr>
                @foreach($chunk as $val => $label)
                    <td style="width:33%;"><span class="cb {{ (string)$survey->cc2 === (string)$val ? 'x' : '' }}"></span> {{ $label }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <div class="cc-q">CC3. If aware of CC (answered 1-3 in CC1), how much did the CC help you in your transaction? *</div>
    <table>
        @foreach(array_chunk($cc3Options, 2, true) as $chunk)
            <tr>
                @foreach($chunk as $val => $label)
                    <td style="width:50%;"><span class="cb {{ (string)$survey->cc3 === (string)$val ? 'x' : '' }}"></span> {{ $label }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <div class="sec">III. Service Quality Dimensions (SQD)</div>
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
        $scale = ['Strongly Disagree', 'Disagree', 'Neither Agree Nor Disagree', 'Agree', 'Strongly Agree', 'N/A'];
        $scaleAbbr = ['SD', 'D', 'NAD', 'A', 'SA', 'N/A'];
    @endphp
<table class="sqd-table">
        <thead>
            <tr>
                <th style="text-align:left; width:34%;">SQD Question</th>
                @php
                    $faces = [
                        'Strongly Disagree' => 'strongly disagree.png',
                        'Disagree' => 'disagree.png',
                        'Neither Agree Nor Disagree' => 'neither agree nor disagree.png',
                        'Agree' => 'agree.png',
                        'Strongly Agree' => 'strongly agree.png',
                    ];
                @endphp
                @foreach($faces as $label => $img)
                    @php $facePath = public_path('csm/' . $img); @endphp
                    <th style="padding:3px 2px;">
                        @if(file_exists($facePath))
                            <img src="{{ 'data:image/png;base64,' . base64_encode(file_get_contents($facePath)) }}" style="width:18px; height:18px; display:block; margin:0 auto 2px auto;">
                        @endif
                        <div style="font-size:7px; font-weight:bold; line-height:1.15;">{{ $label }}</div>
                    </th>
                @endforeach
                <th style="padding:3px 2px;">
                    <div style="font-size:7px; font-weight:bold; line-height:1.15; margin-top:8px;">N/A</div>
                    <div style="font-size:6px;">Not Applicable</div>
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach($sqdQuestions as $key => $question)
                <tr>
                    <td class="sqd-q">{{ $question }}</td>
                    @foreach(array_merge(array_keys($faces), ['N/A']) as $s)
                        <td class="sqd-cell">{{ strtolower($survey->{$key}) === strtolower($s) ? 'X' : '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="sec">IV. Suggestions / Comments</div>
    <div class="sugg-box">{{ $survey->suggestions ?: '' }}</div>

    <div class="footer">
        &mdash;&mdash;&mdash; END OF FORM &mdash;&mdash;&mdash;<br>
        <span class="subnote">Official archival copy (storage only)</span>
    </div>

</body>
</html>
