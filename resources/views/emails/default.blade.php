<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'NCMB CMMS Notification' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            -webkit-font-smoothing: antialiased;
        }
        table { border-collapse: collapse; }

        .outer-table { width: 100%; background-color: #ffffff; }
        .main-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
        }

        .top-bar {
            height: 3px;
            background-color: #1e3a8a;
        }

        .header {
            padding: 28px 32px 0;
            text-align: left;
        }
        .header-name {
            font-size: 18px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 1px;
        }
        .header-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
            font-weight: 400;
        }
        .header-divider {
            height: 1px;
            background: -webkit-linear-gradient(left, #e2e8f0 0%, #cbd5e1 50%, #e2e8f0 100%);
            background: linear-gradient(to right, #e2e8f0 0%, #cbd5e1 50%, #e2e8f0 100%);
            margin: 14px 0 0;
        }

        .body-content {
            padding: 24px 32px 20px;
        }
        .greeting {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 10px;
        }
        .message {
            font-size: 14px;
            color: #334155;
            line-height: 1.6;
            margin-bottom: 22px;
        }

        .details-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table tr:not(:last-child) td {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
            padding-top: 10px;
        }
        .details-table tr:last-child td {
            padding-top: 10px;
        }
        .details-table td {
            font-size: 13px;
        }
        .details-label {
            color: #64748b;
            font-weight: 500;
            width: 100px;
            vertical-align: top;
            padding-right: 12px;
        }
        .details-value {
            color: #0f172a;
            font-weight: 500;
        }
        .details-value strong {
            color: #1e3a8a;
            font-weight: 700;
        }

        .btn-wrap {
            text-align: center;
            margin: 24px 0 4px;
        }
        .btn {
            display: inline-block;
            background: #1e3a8a;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .btn:hover {
            background: #1e40af;
        }

        .footer {
            padding: 20px 32px 28px;
            border-top: 1px solid #e2e8f0;
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
            text-align: center;
        }
        .footer-agency {
            font-weight: 700;
            color: #1e3a8a;
            font-size: 12px;
        }
        .footer-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 12px auto;
            max-width: 200px;
        }

        @media screen and (max-width: 600px) {
            .header { padding: 22px 20px 0 !important; }
            .body-content { padding: 18px 20px 16px !important; }
            .footer { padding: 16px 20px 22px !important; }
            .details-box { padding: 12px 16px !important; }
            .details-label { width: 80px !important; }
        }
    </style>
</head>
<body>
    <table class="outer-table" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding: 0;">
                <table class="main-container" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td class="top-bar"></td>
                    </tr>
                    <tr>
                        <td class="header">
                            <div class="header-name">NCMB CMMS</div>
                            <div class="header-sub">Computerized Maintenance Management System</div>
                            <div class="header-divider"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="body-content">
                            <div class="greeting">Good day, {{ $recipientName }}!</div>
                            <div class="message">{{ $notificationMessage }}</div>

                            <div class="details-box">
                                <table class="details-table" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td class="details-label">Ticket No.</td>
                                        <td class="details-value"><strong>{{ $requestNumber }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="details-label">Type</td>
                                        <td class="details-value">{{ $type }}</td>
                                    </tr>
                                    @if($status)
                                    <tr>
                                        <td class="details-label">Status</td>
                                        <td class="details-value">{{ $status }}</td>
                                    </tr>
                                    @endif
                                    @if($date)
                                    <tr>
                                        <td class="details-label">Date</td>
                                        <td class="details-value">{{ $date }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>

                            @if($ticketUrl)
                            <div class="btn-wrap">
                                <a href="{{ $ticketUrl }}" target="_blank" class="btn">View Details</a>
                            </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="footer">
                            <div class="footer-agency">National Conciliation and Mediation Board</div>
                            {{ $branch ?? '' }}{{ $branch && $region ? ' · ' : '' }}{{ $region ?? '' }}<br>
                            Department of Labor and Employment, Republic of the Philippines
                            <div class="footer-divider"></div>
                            This is an automated notification. Please do not reply.<br>
                            <strong>CONFIDENTIALITY NOTICE:</strong> This email and any files transmitted with it are confidential and intended solely for the designated recipient.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>