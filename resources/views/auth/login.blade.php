<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - CMMS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/ncmb-logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    @vite(['resources/css/login.css'])
    <style nonce="{{ $cspNonce }}">
        /* Page transition fade-out - fast & smooth */
        body.fade-out { opacity: 0; transition: opacity 0.12s ease; }
        body { opacity: 0; animation: pageFadeIn 0.2s ease forwards; }
        @keyframes pageFadeIn { from { opacity: 0; } to { opacity: 1; } }
        /* Password toggle inline */
        .password-wrapper {
            position: relative;
            width: 100%;
        }
        .password-wrapper input {
            padding-right: 48px;
        }
        .password-toggle {
            position: absolute;
            right: 2px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #9ca3af;
            font-size: 18px;
            transition: color 0.2s;
            border-radius: 8px;
        }
        .password-toggle:hover {
            color: #1e40af;
        }
        .password-toggle:active {
            background: #f3f4f6;
        }
        @media screen and (max-width: 767px) {
            .password-toggle {
                width: 48px;
                height: 48px;
                font-size: 20px;
                right: 2px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Back Link -->
        <a href="{{ url('/') }}" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Website
        </a>

        <div class="login-box">
            <div class="logo-container">
                <img src="{{ asset('images/ncmb-logo.png') }}" alt="NCMB Logo" class="login-logo">
            </div>
            
            <div class="login-header">
                <h1>Sign In</h1>
                <p class="subtitle">Enter your credentials to access the portal</p>
            </div>

            @if($errors->any())
                <div class="error-box error-box--visible" style="margin-bottom: 20px;">
                    <i class="fa-solid fa-circle-exclamation" style="font-size: 16px; flex-shrink: 0;"></i>
                    <div>
                        {{ $errors->first('email') ?: ($errors->first('password') ?: 'Invalid credentials. Please try again.') }}
                    </div>
                </div>
            @endif

            <div id="errorBox" class="error-box"></div>

            <form id="loginForm" method="POST" action="{{ route('login') }}">
                @csrf
                <input type="hidden" name="redirect" value="{{ $redirect ?? '' }}">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com" autofocus autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required placeholder="Enter your password" autocomplete="current-password">
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="btnLogin" style="margin-top: 6px;">
                    Sign In
                </button>
            </form>

            <div style="text-align: center; margin-top: 24px; padding-top: 18px; border-top: 1px solid #f1f5f9;">
                <span style="color: #94a3b8; font-size: 12px;">
                    Need help accessing your account? Contact your <strong style="color: #0038A8; font-weight: 700;">ICT Administrator</strong>.
                </span>
            </div>
        </div>

    @vite(['resources/js/login.js'])
    <script nonce="{{ $cspNonce }}">
        (function() {
            const toggleBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            if (toggleBtn && passwordInput) {
                toggleBtn.addEventListener('click', function() {
                    const isPassword = passwordInput.type === 'password';
                    passwordInput.type = isPassword ? 'text' : 'password';
                    this.querySelector('i').className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
                });
            }
        })();
    </script>
    <script nonce="{{ $cspNonce }}">
        // Fast smooth page transition on "Back to Website" click
        document.addEventListener('DOMContentLoaded', function() {
            var backLink = document.querySelector('.back-link');
            if (backLink) {
                backLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    var href = this.getAttribute('href');
                    document.body.classList.add('fade-out');
                    setTimeout(function() {
                        window.location.href = href;
                    }, 120);
                });
            }
        });
    </script>
</body>
</html>
