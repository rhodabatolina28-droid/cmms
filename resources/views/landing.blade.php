<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Repair & Maintenance Services - CMMS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/ncmb-logo.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/landing.css'])
    <style>
        /* Page transition fade-out - fast & smooth */
        body.fade-out { opacity: 0; transition: opacity 0.12s ease; }
        body { opacity: 0; animation: pageFadeIn 0.2s ease forwards; }
        @keyframes pageFadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
    <header class="gov-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-text">
                        <strong>ICT Unit</strong>
                    </div>
                </div>

                <div class="header-meta">
                    <div class="user-controls">
                        <a id="loginLink" href="{{ route('login') }}" class="btn btn-login-link">Log in</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <div class="hero-media">
                <img id="heroImage" src="{{ asset('images/landing-bg.png') }}" alt="NCMB service" />
            </div>
            <h1>NCMB ICT Repair and Preventive Maintenance</h1>
            <p>
                This official portal is designed to streamline the submission and monitoring
                of ICT repair and preventive maintenance requests across the NCMB Central Office.
            </p>
        </div>
    </section>

    <section class="how-it-works">
        <div class="container">
            <h2>How It Works</h2>
            <div class="steps">
                <div class="step">
                    <i class="fas fa-sign-in-alt step-icon"></i>
                    <h4>Log In</h4>
                    <p>Access your account with your credentials</p>
                </div>
                <div class="step">
                    <i class="fas fa-paper-plane step-icon"></i>
                    <h4>Submit Request</h4>
                    <p>Fill out the service request form</p>
                </div>
                <div class="step">
                    <i class="fas fa-search step-icon"></i>
                    <h4>Track Status</h4>
                    <p>Monitor your request in real-time</p>
                </div>
                <div class="step">
                    <i class="fas fa-check-circle step-icon"></i>
                    <h4>Resolution</h4>
                    <p>Receive notification when complete</p>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo"></div>
                <div class="copyright">&copy; {{ date('Y') }} NCMB ICT Unit. All rights reserved.</div>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
        </div>
    </footer>
    <script>
        // Fast smooth page transition on login click
        document.getElementById('loginLink').addEventListener('click', function(e) {
            e.preventDefault();
            var href = this.getAttribute('href');
            document.body.classList.add('fade-out');
            setTimeout(function() {
                window.location.href = href;
            }, 120);
        });
    </script>
</body>
</html>
