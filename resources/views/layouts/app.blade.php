<!doctype html>
<html lang="en-CA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'APF Press | Independent Canadian Academic Publisher')</title>
    <meta name="description" content="@yield('description', 'APF Press publishes bold Canadian scholarship, critical perspectives, and under-represented voices in social justice and human rights.')">
    <meta name="theme-color" content="#082f49">
    @if(View::hasSection('noindex'))<meta name="robots" content="noindex,nofollow">@endif
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="APF Press">
    <meta property="og:title" content="@yield('title', 'APF Press | Independent Canadian Academic Publisher')">
    <meta property="og:description" content="@yield('description', 'Independent Canadian publishing for ideas and voices that matter.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
    <meta name="twitter:card" content="summary_large_image">
    @stack('structured-data')
    @vite(['resources/css/app.css', 'resources/js/public.ts'])
</head>
<body class="public-site">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="announcement">
        <div class="container announcement-inner">
            <span>Independent academic publishing · Toronto, Canada</span>
            <a href="{{ route('publish') }}">Manuscript proposals welcome <span aria-hidden="true">↗</span></a>
        </div>
    </div>
    <header class="site-header">
        <div class="container nav-wrap">
            <a class="brand" href="{{ route('home') }}" aria-label="APF Press home">
                <x-wordmark />
            </a>
            <nav id="main-navigation" class="main-nav" aria-label="Main navigation">
                <a href="{{ route('catalog.index') }}" @if(request()->routeIs('catalog.*')) aria-current="page" @endif>Books</a>
                <a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>About</a>
                <a href="{{ route('publish') }}" @if(request()->routeIs('publish')) aria-current="page" @endif>Publish with us</a>
                <a href="{{ route('board') }}" @if(request()->routeIs('board')) aria-current="page" @endif>Editorial board</a>
                <a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
            </nav>
            <div class="nav-actions">
                <a class="icon-button account-link" href="{{ auth()->check() ? (auth()->user()->isStaff() ? route('admin.index') : route('account.index')) : route('login') }}" aria-label="{{ auth()->check() ? 'Your account' : 'Sign in' }}">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.2 3.6-7 8-7s8 2.8 8 7"/></svg>
                </a>
                <span data-cart-root></span>
                <span data-mobile-nav></span>
            </div>
        </div>
    </header>

    <main id="main-content">
        @if(session('success'))<div class="container alert" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="container alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-statement">
                <p class="eyebrow eyebrow-light">Independent by conviction</p>
                <p>Books for readers who remain<br><em>curious, committed, and courageous.</em></p>
            </div>
            <div class="footer-grid">
                <div>
                    <a class="brand brand-footer" href="{{ route('home') }}" aria-label="APF Press home"><x-wordmark /></a>
                    <p class="footer-copy">Publishing critical scholarship and under-represented perspectives from Canada to the wider world.</p>
                </div>
                <div><p class="footer-title">Explore</p><nav class="footer-links"><a href="{{ route('catalog.index') }}">All books</a><a href="{{ route('catalog.index', ['format' => 'print_book']) }}">Print books</a><a href="{{ route('catalog.index', ['format' => 'ebook']) }}">E-books</a></nav></div>
                <div><p class="footer-title">APF Press</p><nav class="footer-links"><a href="{{ route('about') }}">About us</a><a href="{{ route('board') }}">Editorial board</a><a href="{{ route('publish') }}">Publish with us</a></nav></div>
                <div><p class="footer-title">Contact</p><nav class="footer-links"><a href="mailto:apf.press@rogers.com">apf.press@rogers.com</a><a href="tel:+14168171266">416-817-1266</a><a href="{{ route('contact') }}">Send an inquiry</a></nav></div>
            </div>
            <div class="footer-bottom"><span>© {{ date('Y') }} APF Press. All rights reserved.</span><span>Toronto, Ontario, Canada · Prices in CAD</span></div>
        </div>
    </footer>
</body>
</html>
