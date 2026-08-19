<!doctype html>
<html lang="en" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | {{ $systemSettings?->application_name ?? 'Agile Tech Solutions IIM' }}</title>
    <link rel="icon" type="image/png" href="{{ \App\Support\PublicUrl::asset('static/img/agile-tech-logo.png') }}">
    <script>
        (() => {
            const savedTheme = 'light';
            const defaultTheme = @json($systemSettings?->default_theme ?? 'light');
            const preferredTheme = (savedTheme || defaultTheme) === 'system'
                ? ('light')
                : (savedTheme || defaultTheme);
            document.documentElement.dataset.theme = preferredTheme;
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" rel="stylesheet">
    <link href="{{ \App\Support\PublicUrl::asset('static/css/app.css') }}?v={{ filemtime(public_path('static/css/app.css')) }}" rel="stylesheet">
</head>

<body class="{{ request()->is('login', 'forgot-password', 'reset-password') ? 'auth-body' : '' }}">
    @if(request()->is('login', 'forgot-password', 'reset-password'))
    @yield('content')
    @else
    <div class="app-shell">
        @include('partials.sidebar')
        <div class="app-main">
            @include('partials.header')
            <main class="content-wrap">
                @include('partials.breadcrumbs')
                @yield('content')
            </main>
            @include('partials.footer')
        </div>
    </div>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    @include('partials.confirmation-modal')
    @endif
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    @include('partials.notifications')
    <script src="{{ \App\Support\PublicUrl::asset('static/js/app.js') }}?v={{ filemtime(public_path('static/js/app.js')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const fullscreenBtn = document.getElementById('fullscreenToggle');
            if (!fullscreenBtn) {
                return;
            }
            const fullscreenIcon = fullscreenBtn.querySelector('i');

            fullscreenBtn.addEventListener('click', () => {

                if (!document.fullscreenElement) {

                    document.documentElement.requestFullscreen();

                    fullscreenIcon.classList.remove('fa-expand');
                    fullscreenIcon.classList.add('fa-compress');

                } else {

                    document.exitFullscreen();

                    fullscreenIcon.classList.remove('fa-compress');
                    fullscreenIcon.classList.add('fa-expand');
                }
            });

            document.addEventListener('fullscreenchange', () => {

                if (document.fullscreenElement) {
                    fullscreenIcon.classList.remove('fa-expand');
                    fullscreenIcon.classList.add('fa-compress');
                } else {
                    fullscreenIcon.classList.remove('fa-compress');
                    fullscreenIcon.classList.add('fa-expand');
                }

            });

        });
    </script>
    <!--Start of Tawk.to Script-->
    <script type="text/javascript">
        var Tawk_API = Tawk_API || {},
            Tawk_LoadStart = new Date();
        (function() {
            var s1 = document.createElement("script"),
                s0 = document.getElementsByTagName("script")[0];
            s1.async = true;
            s1.src = 'https://embed.tawk.to/6a38b8f0b68b001d44260bc6/1jrmp4rhg';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        })();
    </script>
    <!--End of Tawk.to Script-->
    @stack('scripts')
</body>

</html>


