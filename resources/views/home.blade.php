@extends('layouts.app')

@section('title', 'APF Press | Bold Canadian Academic Publishing')
@section('description', 'APF Press publishes rigorous books on social justice, human rights, racialized communities, and critical inquiry by scholars and voices too often overlooked.')

@push('structured-data')
@php
    $organizationSchema = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'APF Press', 'url' => url('/'), 'logo' => asset('images/apf-press-logo.png'), 'description' => 'Independent Canadian academic publisher committed to critical and under-represented scholarship.', 'email' => 'apf.press@rogers.com', 'telephone' => '+1-416-817-1266', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Toronto', 'addressRegion' => 'ON', 'addressCountry' => 'CA']];
@endphp
<script nonce="{{ Vite::cspNonce() }}" type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy-block">
            <p class="eyebrow">An independent academic press in Canada</p>
            <h1>Ideas that question.<br><em>Books that endure.</em></h1>
            <p class="hero-copy">APF Press publishes rigorous work that confronts inequity, advances critical perspectives, and makes room for voices too often overlooked.</p>
            <div class="hero-actions">
                <a class="button" href="{{ route('catalog.index') }}">Explore the catalogue <span aria-hidden="true">↗</span></a>
                <a class="button button-quiet" href="{{ route('publish') }}">Publish with APF Press</a>
            </div>
            <div class="hero-proof" aria-label="APF Press publishing focus">
                <span>Academic rigour</span><span>Critical inquiry</span><span>Under-represented voices</span>
            </div>
        </div>

        @if($featured->isNotEmpty())
        <div class="hero-library" aria-label="Selected APF Press titles">
            @foreach($featured->take(3) as $item)
            <a class="hero-book hero-book-{{ $loop->iteration }}" href="{{ route('catalog.show', $item->slug) }}" aria-label="View {{ $item->title }}">
                @if($item->cover?->url)
                    <img src="{{ $item->cover->url }}" alt="" width="480" height="667" @if(!$loop->first) loading="lazy" @endif>
                @else
                    <span class="book-placeholder">{{ $item->title }}</span>
                @endif
            </a>
            @endforeach
            <p class="hero-library-caption"><span>Current catalogue</span>{{ $statistics['titles'] }} published titles</p>
        </div>
        @else
        <aside class="hero-note"><p>Academic publishing should expand the conversation—not narrow it.</p><span class="eyebrow">The APF commitment</span></aside>
        @endif
    </div>
</section>

<div class="principle-strip">
    <div class="container principle-row"><span>01 · Scholarly independence</span><span>02 · Community relevance</span><span>03 · Thoughtful disagreement</span></div>
</div>

<section class="section manifesto-section">
    <div class="container statement">
        <div><p class="eyebrow">Why APF Press</p><span class="section-index">A / 01</span></div>
        <div>
            <p class="manifesto">We publish people who ask difficult questions—and invite readers to be <em>curious, committed, and courageous</em> in challenging inequity.</p>
            <p class="statement-note">Our authors are academics, activists, artists, and professional practitioners. What connects them is work that matters beyond a single discipline.</p>
            <a class="text-link" href="{{ route('about') }}">Read our publishing philosophy <span aria-hidden="true">→</span></a>
        </div>
    </div>
</section>

@if($featured->isNotEmpty())
<section class="section section-catalogue">
    <div class="container">
        <div class="section-heading">
            <div><p class="eyebrow">From our catalogue</p><h2>Scholarship for a changing world.</h2></div>
            <div class="section-heading-aside"><p>Independent thinking across social justice, law, economics, health, and human rights.</p><a class="text-link" href="{{ route('catalog.index') }}">View all {{ $statistics['titles'] }} titles <span aria-hidden="true">→</span></a></div>
        </div>
        <div class="book-grid book-grid-featured">@foreach($featured as $item)<x-book-card :item="$item" />@endforeach</div>
    </div>
</section>
@endif

<section class="section section-dark">
    <div class="container">
        <div class="dark-intro"><p class="eyebrow eyebrow-gold">What guides us</p><h2>Publishing is a form<br>of participation.</h2><p>We treat editorial work as a public responsibility: to widen debate, protect complexity, and make serious ideas accessible.</p></div>
        <div class="values-grid">
            <article class="value-card"><span class="value-number">01</span><div><h3>Critical inquiry</h3><p>Scholarship that challenges orthodoxies and creates room for thoughtful disagreement.</p></div></article>
            <article class="value-card"><span class="value-number">02</span><div><h3>Under-represented voices</h3><p>Authors and perspectives too often overlooked by mainstream publishing channels.</p></div></article>
            <article class="value-card"><span class="value-number">03</span><div><h3>Community relevance</h3><p>Work rooted in lived realities, human rights, racialized communities, and social justice.</p></div></article>
        </div>
    </div>
</section>

<section class="section publishing-path">
    <div class="container">
        <div class="section-heading">
            <div><p class="eyebrow">For authors</p><h2>A thoughtful route from idea to reader.</h2></div>
            <div class="section-heading-aside"><p>We welcome proposals that bring intellectual rigour to urgent questions and overlooked perspectives.</p></div>
        </div>
        <ol class="path-grid">
            <li><span>01</span><h3>Begin with the idea</h3><p>Send a concise proposal, intended readership, and a short author biography.</p></li>
            <li><span>02</span><h3>Enter careful review</h3><p>We consider scholarly contribution, clarity, community relevance, and editorial fit.</p></li>
            <li><span>03</span><h3>Build the book together</h3><p>Selected projects move through collaborative editing, production, and publication.</p></li>
        </ol>
        <div class="cta-band">
            <div><p class="eyebrow eyebrow-light">Start a conversation</p><h2>Your work can move the conversation.</h2><p>Tell our editorial team about the book you believe needs to exist.</p></div>
            <a class="button button-light" href="{{ route('publish') }}">Share your proposal <span aria-hidden="true">↗</span></a>
        </div>
    </div>
</section>
@endsection
