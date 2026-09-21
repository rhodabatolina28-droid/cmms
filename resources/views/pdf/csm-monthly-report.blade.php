<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSM Monthly Summary Report — {{ $monthLabel }}</title>
    <style>
        /* Minimalist Executive Government Style
           - Exact A4 portrait page layout (no right-side clipping)
           - Official NCMB Navy accents (#0f2a6b) & Slate neutrals
           - High-readability typography & structured cards */
        @page {
            size: A4 portrait;
            margin: 9mm 11mm 8mm 11mm;
        }
        * { box-sizing: border-box; }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.38;
            margin: 0;
            padding: 0;
        }

        /* Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f2a6b;
            padding-bottom: 8px;
            margin-bottom: 11px;
        }
        .header-table td { border: none; vertical-align: middle; padding: 0; }
        .logo { width: 50px; height: 50px; }
        .agency { font-size: 16.5px; font-weight: bold; color: #0f2a6b; letter-spacing: 0.5px; line-height: 1.15; }
        .form-title { font-size: 10.8px; font-weight: bold; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }

        /* Meta Bar */
        .meta-strip {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 5.5px 10px;
            margin-bottom: 12px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
            font-size: 9px;
            color: #475569;
            white-space: nowrap;
        }
        .meta-table b { color: #0f172a; }

        /* Section Headings */
        .sec-head {
            font-size: 10.8px;
            font-weight: bold;
            color: #0f2a6b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 13px 0 7px 0;
        }

        /* KPI Metric Summary Strip (Full 4-sided border, clean interior) */
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            table-layout: fixed;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }
        .kpi-card {
            border: none;
            padding: 10px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .kpi-val {
            font-size: 16px;
            font-weight: bold;
            color: #0f2a6b;
            line-height: 1.2;
        }
        .kpi-label {
            font-size: 8.2px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-top: 3px;
        }
        .kpi-badge {
            display: inline-block;
            background: #e0e7ff;
            color: #0f2a6b;
            font-size: 9.8px;
            font-weight: bold;
            padding: 2.5px 8px;
            border-radius: 3px;
            line-height: 1.2;
            margin-top: 1px;
        }

        /* Data Matrix Table */
        .rpt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            table-layout: fixed;
        }
        .rpt-table th, .rpt-table td {
            border: 1px solid #cbd5e1;
            padding: 7px 5px;
            text-align: center;
            font-size: 9.5px;
        }
        .rpt-table th {
            font-size: 8.8px;
            font-weight: bold;
            text-transform: uppercase;
            background: #f1f5f9;
            color: #334155;
            vertical-align: middle;
            padding: 7.5px 4px;
        }
        .rpt-table th .sub {
            display: block;
            font-size: 7.2px;
            font-weight: normal;
            color: #64748b;
            text-transform: none;
            line-height: 1.15;
            margin-top: 1.5px;
        }
        .rpt-table td.q {
            text-align: left;
            font-size: 9.8px;
            color: #1e293b;
            line-height: 1.35;
            padding: 7px 8px;
        }
        .rpt-table td.avg { font-weight: bold; font-size: 10.5px; color: #0f172a; }
        .rpt-table td.band { font-size: 9.5px; color: #334155; font-weight: 500; }
        .rpt-table td.num { font-size: 9.8px; }
        .rpt-table tr.total td {
            background: #f1f5f9;
            font-weight: bold;
            font-size: 10px;
            color: #0f172a;
            border-top: 2px solid #94a3b8;
            padding: 8px 6px;
        }
        .rpt-table tr.weakest-row { background: #fffdf5; }
        .rpt-table tr.weakest-row td.q { color: #9a3412; font-weight: 600; }
        .zero-val { color: #94a3b8; }
        .weak-tag {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            font-size: 7px;
            font-weight: bold;
            padding: 0.5px 3.5px;
            border-radius: 2px;
            margin-right: 4px;
            vertical-align: middle;
        }

        /* Legend Note */
        .legend-strip {
            font-size: 8.2px;
            color: #64748b;
            line-height: 1.4;
            margin-bottom: 12px;
            padding: 2px 2px;
        }

        /* 2-Column Balanced Bottom Layout */
        .layout-two-col { width: 100%; border-collapse: collapse; margin-top: 4px; table-layout: fixed; }
        .layout-two-col td { vertical-align: top; border: none; padding: 0; }
        .col-half-left { width: 50%; padding-right: 5px; }
        .col-half-right { width: 50%; padding-left: 5px; }

        /* Report Box Modules (Executive PDF Style) */
        .report-box {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #ffffff;
            margin-bottom: 7px;
        }
        .report-box-head {
            background: #f8fafc;
            border-bottom: 1px solid #cbd5e1;
            padding: 5.5px 8px;
            font-size: 9px;
            font-weight: bold;
            color: #0f2a6b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .report-box-head.alert-head {
            color: #9a3412;
            background: #fffdf5;
            border-bottom: 1px solid #fed7aa;
        }
        .report-box-body {
            padding: 6px 8px;
            font-size: 9px;
            line-height: 1.35;
        }

        /* Mini Highlight Table */
        .box-mini-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .box-mini-table td {
            border: none;
            padding: 4px 0;
            vertical-align: top;
        }

        .pill-badge {
            display: inline-block;
            font-size: 7.5px;
            font-weight: bold;
            padding: 1.5px 5px;
            border-radius: 3px;
            line-height: 1.15;
            text-transform: uppercase;
            text-align: center;
        }
        .pill-high { background: #ecfdf5; color: #166534; border: 1px solid #bbf7d0; }
        .pill-low { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .pill-watch { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }

        .trend-up { color: #15803d; font-weight: bold; }
        .trend-down { color: #b91c1c; font-weight: bold; }

        /* Profile Mini Table */
        .profile-mini { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .profile-mini th, .profile-mini td {
            border: 1px solid #e2e8f0;
            padding: 4.5px 4px;
            text-align: center;
            font-size: 9px;
        }
        .profile-mini th {
            background: #f1f5f9;
            font-size: 7.8px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
        }
        .profile-mini td { font-weight: bold; color: #0f2a6b; font-size: 10px; }

        /* Footer */
        .footer {
            border-top: 1px solid #e2e8f0;
            margin-top: 18px;
            padding-top: 5px;
            font-size: 8.2px;
            color: #94a3b8;
            text-align: left;
        }
        .empty-note {
            border: 1px dashed #cbd5e1;
            padding: 16px;
            font-size: 10px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
            border-radius: 4px;
            margin-top: 15px;
        }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <table class="header-table">
        <tr>
            <td style="width:50px; text-align:left;">
                @php $logo = public_path('images/ncmb-logo.svg'); @endphp
                @if(file_exists($logo))
                    <img src="{{ 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logo)) }}" class="logo">
                @endif
            </td>
            <td style="text-align:left;">
                <div class="agency">NATIONAL CONCILIATION AND MEDIATION BOARD</div>
                <div class="form-title">Client Satisfaction Measurement (CSM) — Monthly Summary Report</div>
            </td>
        </tr>
    </table>

    {{-- METADATA STRIP (Single Row) --}}
    <div class="meta-strip">
        <table class="meta-table">
            <tr>
                <td style="width: 32%; text-align: left;"><b>Reporting Period:</b> {{ $monthLabel }}</td>
                <td style="width: 34%; text-align: center;"><b>Date Generated:</b> {{ now()->format('F j, Y') }}</td>
                <td style="width: 34%; text-align: right;"><b>Coverage:</b> Whole Office (ICT Unit)</td>
            </tr>
        </table>
    </div>

    @if(!$hasData)
        <div class="empty-note">
            No survey responses were recorded for {{ $monthLabel }}.<br>
            This report is automatically archived as part of the annual ARTA/CSM record.
        </div>
        <div class="footer">National Conciliation and Mediation Board &middot; ICT Management System &middot; {{ $monthLabel }}</div>
    @else

        {{-- 1. MONTHLY SUMMARY (KPI STRIP) --}}
        <div class="sec-head">Monthly Performance Overview</div>
        <table class="kpi-table">
            <tr>
                <td class="kpi-card" style="width: 16%;">
                    <div class="kpi-val">{{ $overall !== null ? number_format($overall, 1) . ' / 5' : '—' }}</div>
                    <div class="kpi-label">Overall Score</div>
                </td>
                <td class="kpi-card" style="width: 18%;">
                    <div class="kpi-val">
                        @if($overall !== null)
                            <span class="kpi-badge">{{ $band['label'] }}</span>
                        @else
                            —
                        @endif
                    </div>
                    <div class="kpi-label">Rating Interpretation</div>
                </td>
                <td class="kpi-card" style="width: 15%;">
                    <div class="kpi-val">{{ $satisfiedPct !== null ? $satisfiedPct . '%' : '—' }}</div>
                    <div class="kpi-label">Satisfaction Rate</div>
                </td>
                <td class="kpi-card" style="width: 15%;">
                    <div class="kpi-val">{{ $respondents }}</div>
                    <div class="kpi-label">Total Responses</div>
                </td>
                <td class="kpi-card" style="width: 20%;">
                    <div class="kpi-val">{{ $completedCount }}</div>
                    <div class="kpi-label">Completed Requests</div>
                </td>
                <td class="kpi-card" style="width: 16%;">
                    <div class="kpi-val">{{ $responseRate !== null ? $responseRate . '%' : '—' }}</div>
                    <div class="kpi-label">Response Rate</div>
                </td>
            </tr>
        </table>

        {{-- 2. QUESTION BREAKDOWN TABLE --}}
        <div class="sec-head">Service Quality Dimension (SQD) Breakdown</div>
        <table class="rpt-table">
            <thead>
                <tr>
                    <th style="width: 37%; text-align: left; padding-left: 6px;">Question / Dimension</th>
                    <th style="width: 7%;">5<span class="sub">Strongly Agree</span></th>
                    <th style="width: 7%;">4<span class="sub">Agree</span></th>
                    <th style="width: 7%;">3<span class="sub">Neutral</span></th>
                    <th style="width: 7%;">2<span class="sub">Disagree</span></th>
                    <th style="width: 7%;">1<span class="sub">Strongly Dis.</span></th>
                    <th style="width: 12%;">Average</th>
                    <th style="width: 16%;">Rating</th>
                </tr>
            </thead>
            <tbody>
                @foreach($questions as $row)
                    @php
                        $isWeakest = ($weakest && $weakest['column'] === $row['column']);
                    @endphp
                    <tr @class(['weakest-row' => $isWeakest])>
                        <td class="q">
                            @if($isWeakest)
                                <span class="weak-tag">LOWEST</span>
                            @endif
                            {{ $row['question'] }}
                        </td>
                        @foreach([5,4,3,2,1] as $s)
                            <td class="num">
                                @if($row['counts'][$s] > 0)
                                    <b>{{ $row['counts'][$s] }}</b>
                                @else
                                    <span class="zero-val">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="avg">{{ $row['average'] !== null ? number_format($row['average'], 1) : '—' }}</td>
                        <td class="band">{{ $row['average'] !== null ? $row['band']['label'] : '—' }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td class="q" style="font-weight: bold; text-align: left; padding-left: 6px;">
                        OVERALL SATISFACTION AVERAGE
                    </td>
                    <td colspan="5" style="color: #64748b; font-size: 7.5px; font-weight: normal; letter-spacing: 0.2px;">
                        (Based on {{ $respondents }} scorable response{{ $respondents === 1 ? '' : 's' }})
                    </td>
                    <td class="avg">{{ $overall !== null ? number_format($overall, 1) : '—' }}</td>
                    <td class="band" style="font-weight: bold; color: #0f2a6b;">{{ $overall !== null ? $band['label'] : '—' }}</td>
                </tr>
            </tbody>
        </table>

        {{-- LEGEND / SCALE NOTE --}}
        <div class="legend-strip">
            <b>Rating Scale:</b> 5 = Strongly Agree &middot; 4 = Agree &middot; 3 = Neither Agree nor Disagree &middot; 2 = Disagree &middot; 1 = Strongly Disagree &middot; N/A excluded.<br>
            <b>Interpretation Range:</b> 4.21&ndash;5.00 Very Satisfied &middot; 3.41&ndash;4.20 Satisfied &middot; 2.61&ndash;3.40 Neutral / Not Sure &middot; 1.81&ndash;2.60 Dissatisfied &middot; 1.00&ndash;1.80 Very Dissatisfied.
        </div>

        {{-- 3. TWO-COLUMN BALANCED INSIGHTS (SIDE-BY-SIDE EQUAL BOXES) --}}
        <table class="layout-two-col">
            <tr>
                {{-- LEFT: TOP PERFORMING DIMENSIONS --}}
                <td class="col-half-left">
                    <div class="report-box">
                        <div class="report-box-head">
                            Top Performing Dimensions &mdash; Highest Rated
                        </div>
                        <div class="report-box-body">
                            @if(!empty($goodNews))
                                <table class="box-mini-table">
                                    @foreach($goodNews as $idx => $row)
                                        <tr @if(!$loop->last) style="border-bottom: 1px solid #f1f5f9;" @endif>
                                            <td style="width: 28px; padding: 3px 0 4px 0;">
                                                <span class="pill-badge pill-high">#{{ $idx + 1 }}</span>
                                            </td>
                                            <td style="padding: 3px 6px 4px 5px;">
                                                <div style="font-weight: bold; color: #0f2a6b; font-size: 9px;">
                                                    {{ $row['label'] }} &middot; {{ $row['dimension'] ?? '' }}
                                                </div>
                                                <div style="font-size: 8px; color: #64748b; line-height: 1.25; margin-top: 1px;">
                                                    {{ $row['question'] }}
                                                </div>
                                                <div style="font-size: 7.8px; color: #15803d; margin-top: 2px;">
                                                    <b>Favorable:</b> {{ $row['counts'][5] + $row['counts'][4] }} of {{ $row['scorable'] }} ({{ $row['scorable'] > 0 ? round((($row['counts'][5] + $row['counts'][4]) / $row['scorable']) * 100) : 0 }}% Agree / Strongly Agree)
                                                </div>
                                            </td>
                                            <td style="width: 65px; text-align: right; padding: 3px 0 4px 0; white-space: nowrap;">
                                                <div style="font-weight: bold; color: #0f172a; font-size: 9.8px;">
                                                    {{ number_format($row['average'], 1) }} <span style="font-size: 7.5px; color: #64748b; font-weight: normal;">/ 5.0</span>
                                                </div>
                                                <div style="font-size: 7.5px; color: #166534; font-weight: bold;">
                                                    {{ $row['band']['label'] }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @else
                                <div style="color: #64748b; padding: 8px 0; text-align: center; font-size: 8.8px;">
                                    No dimension data available.
                                </div>
                            @endif
                        </div>
                    </div>
                </td>

                {{-- RIGHT: AREA NEEDING IMPROVEMENT --}}
                <td class="col-half-right">
                    <div class="report-box">
                        <div class="report-box-head alert-head">
                            Area Needing Improvement &mdash; Lowest Rated
                        </div>
                        <div class="report-box-body">
                            @if($weakest)
                                <table class="box-mini-table">
                                    <tr @if($secondWeakest && $secondWeakest['column'] !== $weakest['column']) style="border-bottom: 1px solid #f1f5f9;" @endif>
                                        <td style="width: 28px; padding: 3px 0 4px 0;">
                                            <span class="pill-badge pill-low">LOW</span>
                                        </td>
                                        <td style="padding: 3px 6px 4px 5px;">
                                            <div style="font-weight: bold; color: #9a3412; font-size: 9px;">
                                                {{ $weakest['label'] }} &middot; {{ $weakest['dimension'] ?? '' }}
                                            </div>
                                            <div style="font-size: 8px; color: #64748b; line-height: 1.25; margin-top: 1px;">
                                                {{ $weakest['question'] }}
                                            </div>
                                            <div style="font-size: 7.8px; color: #9a3412; margin-top: 2px;">
                                                <b>Disagree:</b> {{ $weakest['disagreeCount'] }} of {{ $respondents }} ({{ $respondents > 0 ? round(($weakest['disagreeCount'] / $respondents) * 100) : 0 }}%)
                                                @if($weakest['prevAverage'] !== null)
                                                    &middot; <b>MoM:</b>
                                                    @if($weakest['average'] < $weakest['prevAverage'])
                                                        <span class="trend-down">Decreased ({{ number_format($weakest['prevAverage'], 1) }} to {{ number_format($weakest['average'], 1) }})</span>
                                                    @elseif($weakest['average'] > $weakest['prevAverage'])
                                                        <span class="trend-up">Improved ({{ number_format($weakest['prevAverage'], 1) }} to {{ number_format($weakest['average'], 1) }})</span>
                                                    @else
                                                        <span>Maintained ({{ number_format($weakest['average'], 1) }})</span>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                        <td style="width: 65px; text-align: right; padding: 3px 0 4px 0; white-space: nowrap;">
                                            <div style="font-weight: bold; color: #0f172a; font-size: 9.8px;">
                                                {{ number_format($weakest['average'], 1) }} <span style="font-size: 7.5px; color: #64748b; font-weight: normal;">/ 5.0</span>
                                            </div>
                                            <div style="font-size: 7.5px; color: #92400e; font-weight: bold;">
                                                {{ $weakest['band']['label'] }}
                                            </div>
                                        </td>
                                    </tr>
                                    @if($secondWeakest && $secondWeakest['column'] !== $weakest['column'])
                                        <tr>
                                            <td style="width: 28px; padding: 3px 0 4px 0;">
                                                <span class="pill-badge pill-watch">2ND</span>
                                            </td>
                                            <td style="padding: 3px 6px 4px 5px;">
                                                <div style="font-weight: bold; color: #334155; font-size: 9px;">
                                                    {{ $secondWeakest['label'] }} &middot; {{ $secondWeakest['dimension'] ?? '' }}
                                                </div>
                                                <div style="font-size: 8px; color: #64748b; line-height: 1.25; margin-top: 1px;">
                                                    {{ $secondWeakest['question'] }}
                                                </div>
                                                <div style="font-size: 7.8px; color: #64748b; margin-top: 2px;">
                                                    <b>Disagree:</b> {{ $secondWeakest['disagreeCount'] }} of {{ $respondents }} ({{ $respondents > 0 ? round(($secondWeakest['disagreeCount'] / $respondents) * 100) : 0 }}%)
                                                </div>
                                            </td>
                                            <td style="width: 65px; text-align: right; padding: 3px 0 4px 0; white-space: nowrap;">
                                                <div style="font-weight: bold; color: #0f172a; font-size: 9.8px;">
                                                    {{ number_format($secondWeakest['average'], 1) }} <span style="font-size: 7.5px; color: #64748b; font-weight: normal;">/ 5.0</span>
                                                </div>
                                                <div style="font-size: 7.5px; color: #475569; font-weight: bold;">
                                                    {{ $secondWeakest['band']['label'] }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                            @else
                                <div style="color: #64748b; padding: 8px 0; text-align: center; font-size: 8.8px;">
                                    No negative ratings recorded for this period.
                                </div>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- 4. RESPONDENT DEMOGRAPHIC PROFILE (FULL-WIDTH ANCHOR) --}}
        <div class="report-box" style="margin-top: 4px; margin-bottom: 0;">
            <div class="report-box-head" style="color: #334155; font-size: 8.5px; padding: 4.5px 8px;">
                Respondent Demographic Profile
            </div>
            <div class="report-box-body" style="padding: 4px 6px;">
                @php $others = $respondents - $male - $female; @endphp
                <table class="profile-mini">
                    <tr>
                        <th style="width: 25%;">Male</th>
                        <th style="width: 25%;">Female</th>
                        <th style="width: 25%;">Not Specified</th>
                        <th style="width: 25%;">Total Respondents</th>
                    </tr>
                    <tr>
                        <td>{{ $male }} <span style="font-size: 7.8px; font-weight: normal; color: #64748b;">({{ $respondents > 0 ? round(($male / $respondents) * 100) : 0 }}%)</span></td>
                        <td>{{ $female }} <span style="font-size: 7.8px; font-weight: normal; color: #64748b;">({{ $respondents > 0 ? round(($female / $respondents) * 100) : 0 }}%)</span></td>
                        <td>{{ $others > 0 ? $others : 0 }} <span style="font-size: 7.8px; font-weight: normal; color: #64748b;">({{ $respondents > 0 && $others > 0 ? round(($others / $respondents) * 100) : 0 }}%)</span></td>
                        <td style="background: #f8fafc;">{{ $respondents }} <span style="font-size: 7.8px; font-weight: normal; color: #64748b;">(100%)</span></td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- FOOTER --}}
        <div class="footer">
            National Conciliation and Mediation Board &middot; ICT Management System &middot; Client Satisfaction Measurement (CSM) Monthly Summary &middot; {{ $monthLabel }}
        </div>

    @endif

</body>
</html>