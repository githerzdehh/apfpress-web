@extends('layouts.app')

@section('title', 'Editorial Board | APF Press')
@section('description', 'Meet the scholars and professionals who guide APF Press editorial standards and its commitment to rigorous, critical academic publishing.')

@section('content')
<header class="page-hero board-hero">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span>Editorial board</span></nav>
        <div class="page-hero-grid"><div><p class="eyebrow">Guided by expertise and integrity</p><h1>Our editorial board.</h1></div><div class="page-hero-aside"><span class="folio">Advisory / {{ str_pad((string) $members->count(), 2, '0', STR_PAD_LEFT) }}</span><p>Respected scholars and professionals protect rigorous standards while making space for critical and unconventional work.</p></div></div>
    </div>
</header>

<section class="section board-section">
    <div class="container">
        <div class="board-intro"><p class="eyebrow">Voices behind the work</p><p class="manifesto">Editorial independence requires <em>knowledge, care, and principled disagreement.</em></p></div>
        <div class="board-grid">
            @forelse($members as $member)
            <article class="board-card"><span class="board-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h2>{{ $member->name }}</h2><p>{{ $member->affiliation }}</p></div></article>
            @empty<div class="empty-state"><p>No active board members are currently listed.</p></div>@endforelse
        </div>
    </div>
</section>
@endsection
