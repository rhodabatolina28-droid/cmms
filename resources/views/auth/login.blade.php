<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - CMMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/login.css') }}" rel="stylesheet">
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
        @media (max-width: 767px) {
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
            <!-- Brand badge -->
            <div class="brand-badge">
                ICT System
            </div>

            <div class="logo-container">
                <img src="{{ asset('images/ncmb-logo.png') }}" alt="NCMB Logo" class="login-logo">
            </div>
            
            <div class="login-header">
                <h1>Sign In</h1>
                <p class="subtitle">Enter your credentials to access the portal</p>
            </div>

            <div id="errorBox" class="error-box"></div>

            <form id="loginForm" method="POST" action="{{ route('login') }}">
                @csrf
                <input type="hidden" name="redirect" value="{{ $redirect ?? '' }}">

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com">
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                    <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn-login">
                Login
            </button>
        </form>

        @if($errors->any())
            <div style="text-align:center; margin-top:20px;">
                <span style="color:#94a3b8; font-size:12px;">
                    Need help signing in? Please contact <strong style="color:#0038A8;">ICT Unit</strong>.
                </span>
            </div>
        @endif
    </div>

    <script nonce="{{ $cspNonce }}" src="{{ asset('js/login.js') }}"></script>
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
