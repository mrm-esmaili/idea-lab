<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">

    <title>@yield('title') | پنل ایده‌لب</title>

    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/adminlte.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a
                        class="nav-link"
                        data-lte-toggle="sidebar"
                        href="#"
                        role="button"
                        aria-label="باز و بسته کردن منو"
                    >
                        <i class="bi bi-list"></i>
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a
                        href="#"
                        class="nav-link dropdown-toggle"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <i class="bi bi-person-circle me-1"></i>
                        <span class="d-none d-md-inline">
                            {{ auth()->user()->name }}
                        </span>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text text-body-secondary small">
                                نقش: {{ auth()->user()->role?->label() }}
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="bi bi-box-arrow-left me-1"></i>
                                    خروج
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    @include('admin.partials.sidebar')

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row align-items-center g-2">
                    <div class="col-sm">
                        <h1 class="h3 mb-0">@yield('page-title', View::yieldContent('title'))</h1>

                        @hasSection('breadcrumbs')
                            <nav aria-label="مسیر صفحه">
                                <ol class="breadcrumb mb-0 mt-1 small">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('admin.dashboard') }}">داشبورد</a>
                                    </li>
                                    @yield('breadcrumbs')
                                </ol>
                            </nav>
                        @endif
                    </div>

                    @hasSection('page-actions')
                        <div class="col-sm-auto">
                            @yield('page-actions')
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                <x-admin.alerts />

                @yield('content')
            </div>
        </div>
    </main>

    <footer class="app-footer small text-body-secondary">
        ایده‌لب — سامانه اعتبارسنجی ایده
    </footer>
</div>

<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/adminlte/js/adminlte.min.js') }}"></script>
@stack('scripts')
</body>
</html>
