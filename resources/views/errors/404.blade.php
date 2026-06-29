<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found | NCMB CMMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style nonce="{{ $cspNonce }}">
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0038A8 0%, #0050d8 40%, #003090 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 60px 50px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .logo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 36px;
        }
        .logo-wrap img { width: 44px; height: auto; }
        .logo-wrap span {
            font-size: 18px;
            font-weight: 900;
            color: #0038A8;
            letter-spacing: 1px;
        }
        .icon-wrap {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .icon-wrap i {
            font-size: 42px;
            color: #0038A8;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        .code {
            font-size: 80px;
            font-weight: 900;
            color: #0038A8;
            line-height: 1;
            margin-bottom: 8px;
            letter-spacing: -4px;
        }
        .title {
            font-size: 22px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 12px;
        }
        .desc {
            font-size: 14px;
            color: #64748b;
            line-height: 1.7;
            margin-bottom: 36px;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: #0038A8;
            color: white;
        }
        .btn-primary:hover { background: #002d8c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,56,168,0.35); }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .btn-secondary:hover { background: #e2e8f0; }
        .divider {
            height: 1px;
            background: #f1f5f9;
            margin: 36px 0;
        }
        .footer-text {
            font-size: 12px;
            color: #94a3b8;
        }
        .footer-text strong { color: #0038A8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-wrap">
            <img src="{{ asset('images/ncmb-logo.svg') }}" alt="NCMB Logo" class="logo-img">
            <span>CMMS PORTAL</span>
        </div>

        <div class="icon-wrap">
            <i class="fa-solid fa-map-location-dot"></i>
        </div>

        <div class="code">404</div>
        <div class="title">Page Not Found</div>
        <p class="desc">
            The page you are looking for doesn't exist or may have been moved.
            Please check the URL or navigate back to the portal.
        </p>

        <div class="btn-group">
            @auth
                <a href="{{ route(Auth::user()->dashboardRouteName()) }}" class="btn btn-primary">
                    <i class="fa-solid fa-gauge"></i> Go to Dashboard
                </a>
                <a href="{{ url()->previous() }}" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Go Back
                </a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </a>
                <a href="{{ url('/') }}" class="btn btn-secondary">
                    <i class="fa-solid fa-house"></i> Home
                </a>
            @endauth
        </div>

        <div class="divider"></div>
        <p class="footer-text">
            NCMB Computerized Maintenance Management System &mdash;
            <strong>ICT Unit</strong>
        </p>
    </div>
    <script nonce="{{ $cspNonce }}">
        document.querySelectorAll('.logo-img').forEach(function(img) {
            img.addEventListener('error', function() { this.style.display = 'none'; });
        });
    </script>
</body>
</html>
