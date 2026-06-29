<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - NCMB</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style nonce="{{ $cspNonce }}">
        body {
            font-family: Arial, sans-serif;
            background-color: #ffffff;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            color: #333;
        }

        .logout-box {
            text-align: center;
            padding: 20px;
        }

        .logo-img {
            width: 80px;
            height: auto;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #111827;
        }

        p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 25px;
        }

        .btn-login {
            display: inline-block;
            color: #0038A8;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            border: 1px solid #0038A8;
            padding: 10px 25px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-login:hover {
            background-color: #0038A8;
            color: white;
        }

        .footer-text {
            position: absolute;
            bottom: 30px;
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .ls-spinner-wrap { margin-top: 20px; }
        .ls-spinner { color: #0038A8; font-size: 24px; }
    </style>
</head>
<body>
    <div class="logout-box">
        <img src="{{ asset('images/ncmb-logo.png') }}" alt="NCMB Logo" class="logo-img">
        <h1>Successfully Logged Out</h1>
        <p>You have safely signed out. Redirecting you to the home page...</p>
        
        <div class="ls-spinner-wrap">
            <i class="fa-solid fa-circle-notch fa-spin ls-spinner"></i>
        </div>
    </div>

    <div class="footer-text">
        NCMB ICT UNIT &bull; CMMS PORTAL
    </div>

    <script nonce="{{ $cspNonce }}">
        // Redirect to landing page after 3 seconds
        setTimeout(function() {
            window.location.href = "{{ url('/') }}";
        }, 3000);
    </script>
</body>
</html>
