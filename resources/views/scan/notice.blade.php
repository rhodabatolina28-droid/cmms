<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | CMMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style nonce="{{ $cspNonce }}">
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; padding: 16px; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 420px; width: 100%; padding: 36px 28px; text-align: center; }
        .icon-wrap { width: 56px; height: 56px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 22px; margin: 0 auto 16px; }
        h2 { font-size: 18px; color: #1e293b; margin-bottom: 8px; }
        p { font-size: 14px; color: #64748b; line-height: 1.5; }
        .btn { display: inline-flex; align-items: center; gap: 6px; margin-top: 22px; padding: 12px 24px; background: #0038A8; color: white; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 700; min-height: 44px; }
        .btn:hover { background: #002d8c; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap"><i class="fa-solid {{ $icon ?? 'fa-circle-info' }}"></i></div>
        <h2>{{ $title }}</h2>
        <p>{{ $message }}</p>
        <a href="{{ url('/') }}" class="btn"><i class="fa-solid fa-house"></i> Go to Dashboard</a>
    </div>
</body>
</html>