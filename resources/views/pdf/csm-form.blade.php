<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Satisfaction Measurement (CSM) — Survey Copy</title>
    <style>
        @page { size: A4 portrait; margin: 7mm 9mm 7mm 9mm; }
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 8.5px; color: #1f2937; margin: 0; padding: 0; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        .header-table td { border: none; }
        .logo { width: 42px; height: 42px; vertical-align: middle; }
        .agency { font-size: 15px; font-weight: bold; color: #0f2a6b; }
        .form-title { font-size: 10px; font-weight: bold; letter-spacing: 0.5px; }
        .section-head { text-align: center; font-weight: bold; font-size: 9.5px; background: #f3f4f6; border: 1px solid #000; padding: 3px; text-transform: uppercase; margin: 5px 0 2px 0; }
        .intro { text-align: center; font-size: 9px; margin: 4px 0; }
        .consent-box { border: 1px solid #000; padding: 5px 7px; margin: 4px 0; font-size: 8px; text-align: justify; }
        .consent-head { font-weight: bold; margin-bottom: 2px; }
        .nd-line { border-bottom: 2px solid #000; display: inline-block; min-height: 13px; padding: 0 6px; font-size: 8.5px; }
        .profile-label { font-weight: bold; }
        .cc-q { margin: 6px 0 2px 0; font-weight: bold; }
        .cc-opt { margin: 1px 0 1px 10px; }
        .cb { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; margin-right: 4px; position: relative; top: 1px; text-align: center; line-height: 10px; font-size: 8px; }
        .cb.x:after { content: "X"; font-weight: bold; }
        .sqd-table { width: 100%; border-collapse: collapse; margin-top: 3px; }
        .sqd-table th, .sqd-table td { border: 1px solid #000; padding: 2px 3px; font-size: 7.5px; }
        .sqd-table th { background: #e8eef5; font-weight: bold; text-align: center; }
        .sqd-q { font-weight: bold; }
        .sqd-cell { text-align: center; width: 30px; font-weight: bold; font-size: 8px; }
        .sugg-box { border: 1px solid #000; min-height: 34px; padding: 4px 6px; margin-top: 3px; }
        .footer { text-align: center; font-weight: bold; font-size: 8px; margin-top: 6px; border-top: 1px solid #999; padding-top: 3px; }
        .subnote { text-align: center; font-size: 7px; color: #666; }
    </style>
</head>
<body>

    {{-- HEADER (logo + title; no request number per user decision) --}}
    <table class="header-table">
        <tr>
            <td style="width:48px;">
                @php $logo = public_path('images/ncmb-logo.svg'); @endphp
                @if(file_exists($logo))
                    <img src="{{ 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logo)) }}" class="logo">
                @endif
            </td>
            <td>
                <span class="agency">NCMB</span>
                <span class="form-title"> CLIENT SATISFACTION MEASUREMENT (CSM) SURVEY FORM</span>
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
        <div style="margin-top:4px;">
            <span class="cb x"></span> <strong>Yes, I agree</strong>
            &nbsp;&nbsp;&nbsp;
            <span class="cb"></span> No, I do not agree
        </div>
    </div>

    <div class="section-head">I. RESPONDENT PROFILE</div>
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
                <span class="profile-label">Office:</span> <span class="nd-line">{{ $survey->request?->user?->office ?? '—' }}</span>
            </td>
            <td>
                <span class="profile-label">Service:</span> <span class="nd-line">{{ $survey->request?->type ?? '—' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="profile-label">Date Availed:</span> <span class="nd-line">{{ $survey->request?->created_at?->format('m/d/Y') ?? '—' }}</span>
            </td>
            <td></td>
        </tr>
    </table>
<div class="section-head">II. CITIZEN'S CHARTER (CC) AWARENESS</div>
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

    <div class="section-head">III. SERVICE QUALITY DIMENSIONS (SQD)</div>
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
    @endphp
    <table class="sqd-table">
        <thead>
            <tr>
                <th style="text-align:left; width:38%;">SQD Question</th>
                @foreach($scale as $s)
                    <th>{{ $s }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($sqdQuestions as $key => $question)
                <tr>
                    <td class="sqd-q">{{ $question }}</td>
                    @foreach($scale as $s)
                        <td class="sqd-cell">{{ strtolower($survey->{$key}) === strtolower($s) ? 'X' : '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-head">IV. SUGGESTIONS / COMMENTS</div>
    <div class="sugg-box">{{ $survey->suggestions ?: '' }}</div>

    <div class="footer">
        &mdash;&mdash;&mdash; END OF FORM &mdash;&mdash;&mdash;<br>
        <span class="subnote">Official archival copy (storage only)</span>
    </div>

</body>
</html>