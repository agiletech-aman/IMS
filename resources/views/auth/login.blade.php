@extends('layouts.app')

@section('title', 'Login')

@section('content')
<main class="login-page">
    <section class="login-card" aria-labelledby="login-heading">
        <div class="login-brand">
            <img
                src="{{ \App\Support\PublicUrl::asset('static/img/agile-tech-logo.png') }}"
                alt="Agile Tech Solutions"
                class="login-logo"
                onerror="this.hidden=true; this.nextElementSibling.hidden=false;"
            >
            <span class="login-logo-fallback" hidden aria-hidden="true">
                <i class="fa-solid fa-layer-group"></i>
            </span>
            <span class="login-company">{{ $systemSettings?->company_name ?? 'Agile Tech Solutions' }}</span>
        </div>

        <div class="login-intro">
            <p class="login-kicker">Asset Management System</p>
            <h1 id="login-heading">Welcome back</h1>
            <p>Enter your details to access your workspace.</p>
        </div>

        @if($errors->any())
            <div class="login-alert" role="alert">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="login-form" autocomplete="off">
            @csrf

            <div class="login-field">
                <label for="email">Email address</label>
                <div class="login-input">
                    <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        placeholder="Enter your email"
                        autocomplete="off"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="login-field">
                <label for="password">Password</label>
                <div class="login-input">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Enter your password"
                        autocomplete="new-password"
                        required
                    >
                    <button
                        class="password-toggle"
                        type="button"
                        id="passwordToggle"
                        aria-label="Show password"
                        aria-pressed="false"
                    >
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="login-options">
                <label class="remember-option" for="remember">
                    <input id="remember" name="remember" type="checkbox" value="1">
                    <span>Remember me</span>
                </label>
            </div>

            <button type="submit" class="login-submit">
                <span>Log in</span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </button>
        </form>
    </section>

    <section class="login-showcase" aria-label="Agile Tech Solutions asset management">
        <div class="showcase-copy">
            <span class="showcase-badge"><i class="fa-solid fa-shield-halved"></i> Secure workspace</span>
            <h2>Manage every asset.<br><span>All in one place.</span></h2>
            <p>Track, organize, and protect your technology inventory with a smarter asset management workspace.</p>
        </div>

        <div class="workspace-illustration" aria-hidden="true">
            <div class="illustration-glow glow-one"></div>
            <div class="illustration-glow glow-two"></div>

            <div class="floating-card asset-count">
                <span class="floating-icon cyan"><i class="fa-solid fa-cubes-stacked"></i></span>
                <span><small>Total assets</small><strong>2,486</strong></span>
                <em>+12%</em>
            </div>

            <div class="floating-card secure-card">
                <span class="floating-icon green"><i class="fa-solid fa-circle-check"></i></span>
                <span><strong>Systems secure</strong><small>All checks passed</small></span>
            </div>

            <div class="illustration-window">
                <div class="window-bar">
                    <span></span><span></span><span></span>
                    <div class="window-search"></div>
                    <i class="fa-regular fa-bell"></i>
                    <b>AT</b>
                </div>
                <div class="window-body">
                    <aside>
                        <div class="mini-brand"><i class="fa-solid fa-layer-group"></i></div>
                        <span class="active"></span><span></span><span></span><span></span><span></span>
                    </aside>
                    <div class="window-content">
                        <div class="content-heading"><span></span><button></button></div>
                        <div class="stat-row"><span></span><span></span><span></span></div>
                        <div class="chart-card">
                            <div class="chart-bars">
                                <i style="height:34%"></i><i style="height:54%"></i><i style="height:44%"></i>
                                <i style="height:76%"></i><i style="height:61%"></i><i style="height:88%"></i>
                                <i style="height:69%"></i><i style="height:94%"></i>
                            </div>
                        </div>
                        <div class="table-lines"><span></span><span></span><span></span></div>
                    </div>
                </div>
            </div>

            <div class="illustration-person">
                <div class="person-head">
                    <span class="person-hair"></span>
                    <i></i>
                </div>
                <div class="person-body">
                    <span class="shirt-mark">A</span>
                </div>
                <div class="person-arm left"></div>
                <div class="person-arm right"></div>
            </div>

            <div class="illustration-desk">
                <div class="laptop"><span></span><i class="fa-solid fa-layer-group"></i></div>
                <div class="desk-top"></div>
                <div class="desk-leg left"></div>
                <div class="desk-leg right"></div>
                <div class="plant"><i></i><i></i><i></i><span></span></div>
                <div class="mug"></div>
            </div>
        </div>

        <p class="showcase-footer">Powered by {{ $systemSettings?->company_name ?? 'Agile Tech Solutions' }}</p>
    </section>
</main>
@endsection

@push('scripts')
<script>
    document.getElementById('passwordToggle')?.addEventListener('click', function () {
        const password = document.getElementById('password');
        const showPassword = password.type === 'password';

        password.type = showPassword ? 'text' : 'password';
        this.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
        this.setAttribute('aria-pressed', String(showPassword));
        this.querySelector('i').className = showPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    });
</script>
@endpush
