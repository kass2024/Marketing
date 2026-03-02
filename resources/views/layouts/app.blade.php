<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Admin Panel') }}</title>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f9;
        }

        .navbar {
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }

        .card {
            border-radius: 14px;
        }

        .btn {
            border-radius: 8px;
        }

        .table th {
            font-weight: 600;
        }

        .page-title {
            font-weight: 600;
        }
    </style>
</head>
<body>

    {{-- PROFESSIONAL TOP NAVBAR --}}
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm">
        <div class="container">

            {{-- Brand --}}
            <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">
                <i class="bi bi-speedometer2 me-2"></i>
                {{ config('app.name', 'Admin Panel') }}
            </a>

            {{-- Toggle for mobile --}}
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">

                {{-- Left Links --}}
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard') }}">
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.faq.index') }}">
                            FAQ Management
                        </a>
                    </li>
                </ul>

                {{-- Right User Dropdown --}}
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fw-medium"
                           href="#"
                           role="button"
                           data-bs-toggle="dropdown">

                            <i class="bi bi-person-circle me-1"></i>
                            {{ Auth::user()->name ?? 'Admin' }}
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    <i class="bi bi-person me-2"></i> Profile
                                </a>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item">
                                        <i class="bi bi-box-arrow-right me-2"></i> Log Out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>

            </div>
        </div>
    </nav>

    {{-- OPTIONAL PAGE HEADER --}}
    @if (isset($header))
        <div class="bg-white border-bottom py-4 mb-4">
            <div class="container">
                {{ $header }}
            </div>
        </div>
    @endif

    {{-- MAIN CONTENT --}}
    <main class="container py-4">
        {{ $slot }}
    </main>

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>