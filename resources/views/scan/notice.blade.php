<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | CMMS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/ncmb-logo.svg') }}">
    <style nonce="{{ $cspNonce }}">
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; padding: 16px; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 420px; width: 100%; padding: 36px 28px; text-align: center; }
        /* Icons removed: text-only na ang notice page (Bug 7). */
        h2 { font-size: 18px; color: #1e293b; margin-bottom: 8px; }
        p { font-size: 14px; color: #64748b; line-height: 1.5; }
        .btn { display: inline-flex; align-items: center; gap: 6px; margin-top: 22px; padding: 12px 24px; background: #0038A8; color: white; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 700; min-height: 44px; }
        .btn:hover { background: #002d8c; }

        /* Mobile: full-width at 48px na touch target para madaling pindutin */
        @media screen and (max-width: 768px) {
            body { padding: 10px; }
            .card { padding: 28px 20px; }
            .btn { display: flex; width: 100%; justify-content: center; min-height: 48px; font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>{{ $title }}</h2>
        <p>{{ $message }}</p>
        <a href="{{ url('/') }}" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>