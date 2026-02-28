<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parrot Meta Suite</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
        }

        .hero {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: white;
            padding: 120px 0;
        }

        .hero h1 {
            font-weight: 700;
        }

        .feature-card {
            transition: 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .pricing-card {
            border-radius: 12px;
            transition: 0.3s ease;
        }

        .pricing-card:hover {
            transform: scale(1.03);
        }

        footer {
            background: #111;
            color: #aaa;
            padding: 40px 0;
        }

        footer a {
            color: #ccc;
            text-decoration: none;
        }

        footer a:hover {
            color: white;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="#">Parrot Meta Suite</a>
        <div class="ms-auto">
            <a href="{{ route('login') }}" class="btn btn-outline-primary me-2">Login</a>
            <a href="{{ route('register') }}" class="btn btn-primary">Register</a>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero text-center">
    <div class="container">
        <h1 class="display-4 mb-4">
            Manage Facebook Ads & WhatsApp Campaigns
            <br>From One Powerful Dashboard
        </h1>
        <p class="lead mb-5">
            Connect your Meta Business account, monitor ad performance,
            and automate WhatsApp conversations with ease.
        </p>
        <a href="{{ route('register') }}" class="btn btn-light btn-lg me-3">Start Free Trial</a>
        <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg">Login</a>
    </div>
</section>

<!-- FEATURES -->
<section class="py-5">
    <div class="container text-center">
        <h2 class="mb-5">Powerful Features</h2>
        <div class="row g-4">

            <div class="col-md-4">
                <div class="card feature-card p-4 h-100">
                    <h5 class="fw-bold">Real-Time Analytics</h5>
                    <p class="text-muted">
                        Track ad spend, leads and WhatsApp conversations instantly.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card feature-card p-4 h-100">
                    <h5 class="fw-bold">Secure Meta OAuth</h5>
                    <p class="text-muted">
                        Official Meta authentication for safe business connection.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card feature-card p-4 h-100">
                    <h5 class="fw-bold">Business Automation</h5>
                    <p class="text-muted">
                        Manage campaigns and respond to customers easily.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- PRICING -->
<section class="bg-light py-5">
    <div class="container text-center">
        <h2 class="mb-5">Simple Pricing</h2>

        <div class="row justify-content-center g-4">

            <div class="col-md-4">
                <div class="card pricing-card shadow-sm p-4">
                    <h4 class="fw-bold">Free Plan</h4>
                    <h2 class="my-3">$0</h2>
                    <ul class="list-unstyled text-muted mb-4">
                        <li>1 Business Connection</li>
                        <li>Basic Analytics</li>
                        <li>Email Support</li>
                    </ul>
                    <a href="{{ route('register') }}" class="btn btn-primary w-100">
                        Get Started
                    </a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card pricing-card shadow-lg border-primary p-4">
                    <h4 class="fw-bold text-primary">Pro Plan</h4>
                    <h2 class="my-3">$49 <small>/month</small></h2>
                    <ul class="list-unstyled text-muted mb-4">
                        <li>Unlimited Connections</li>
                        <li>Advanced Analytics</li>
                        <li>Priority Support</li>
                    </ul>
                    <a href="{{ route('register') }}" class="btn btn-primary w-100">
                        Upgrade Now
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="text-center">
    <div class="container">
        <p>© {{ date('Y') }} Parrot Meta Suite. All rights reserved.</p>
        <div class="mt-3">
            <a href="/privacy-policy">Privacy Policy</a> |
            <a href="/terms-of-service">Terms</a> |
            <a href="/data-deletion">Data Deletion</a>
        </div>
    </div>
</footer>

</body>
</html>