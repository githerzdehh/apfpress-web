@extends('layouts.app')

@section('title', $catalogItem->seo_title ?: $catalogItem->title.' | APF Press')
@section('description', $catalogItem->seo_description ?: Str::limit($catalogItem->summary ?: 'Discover '.$catalogItem->title.', published by APF Press in Canada.', 160))
@section('canonical', route('catalog.show', $catalogItem->slug))
@section('og_type', 'book')
@if($catalogItem->cover?->url)@section('og_image', $catalogItem->cover->url)@endif

@push('structured-data')
@php
    $schemaOffering = $catalogItem->offerings->firstWhere('price_amount', '!==', null) ?: $catalogItem->offerings->first();
    $schema = ['@context' => 'https://schema.org', '@type' => 'Book', 'name' => $catalogItem->title, 'description' => $catalogItem->summary, 'url' => route('catalog.show', $catalogItem->slug), 'publisher' => ['@type' => 'Organization', 'name' => 'APF Press']];
    if ($catalogItem->cover?->url) $schema['image'] = $catalogItem->cover->url;
    if ($catalogItem->contributors->isNotEmpty()) $schema['author'] = $catalogItem->contributors->map(fn($author) => ['@type' => 'Person', 'name' => $author->name])->values();
    if ($schemaOffering?->bookEdition?->isbn_13) $schema['isbn'] = $schemaOffering->bookEdition->isbn_13;
    if ($schemaOffering?->price_amount !== null) $schema['offers'] = ['@type' => 'Offer', 'priceCurrency' => $schemaOffering->currency, 'price' => number_format($schemaOffering->price_amount / 100, 2, '.', ''), 'availability' => $schemaOffering->isAvailable() ? 'https://schema.org/InStock' : 'https://schema.org/PreOrder', 'url' => route('catalog.show', $catalogItem->slug)];
    $primaryEdition = $catalogItem->offerings->first()?->bookEdition;
@endphp
<script nonce="{{ Vite::cspNonce() }}" type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<section class="book-detail-section">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><a href="{{ route('catalog.index') }}">Books</a><span aria-hidden="true">/</span><span>{{ Str::limit($catalogItem->title, 42) }}</span></nav>
        <div class="book-detail-grid">
            <div class="detail-visual">
                <div class="detail-cover-frame">
                    @if($catalogItem->cover?->url)<img class="detail-book-cover" src="{{ $catalogItem->cover->url }}" alt="{{ $catalogItem->cover->alt_text ?: $catalogItem->title.' book cover' }}" width="640" height="889">@else<div class="book-placeholder">{{ $catalogItem->title }}</div>@endif
                </div>
                <p class="detail-visual-note">Published independently in Canada<br>Prices shown in Canadian dollars</p>
            </div>

            <article class="detail-copy">
                <div class="chips">@foreach($catalogItem->categories as $category)<a class="chip" href="{{ route('catalog.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>@endforeach</div>
                <p class="eyebrow">An APF Press title</p>
                <h1>{{ $catalogItem->title }}</h1>
                @if($catalogItem->subtitle)<p class="detail-subtitle">{{ $catalogItem->subtitle }}</p>@endif
                <p class="author-line">{{ $catalogItem->contributors->isNotEmpty() ? 'By '.$catalogItem->contributors->pluck('name')->join(', ') : 'Author information forthcoming' }}</p>
                @if($catalogItem->summary)<p class="detail-summary">{{ $catalogItem->summary }}</p>@endif

                <div class="purchase-panel">
                    <div class="purchase-heading"><p class="eyebrow">Choose an edition</p><span>{{ $catalogItem->offerings->count() }} {{ Str::plural('format', $catalogItem->offerings->count()) }}</span></div>
                    <div class="offering-list">
                        @foreach($catalogItem->offerings as $offering)
                        <div class="offering">
                            <div class="offering-copy">
                                <span class="offering-kind">{{ ucfirst(str_replace('_', ' ', $offering->kind)) }}</span>
                                <strong>{{ $offering->name }}</strong>
                                <small>
                                    @if($offering->bookEdition?->isbn_13)
                                        ISBN {{ $offering->bookEdition->isbn_13 }}
                                    @else
                                        Bibliographic details being confirmed
                                    @endif
                                </small>
                            </div>
                            <div class="offering-action">
                                <span class="offering-price">{{ $offering->formatted_price ?: 'Price on request' }}</span>
                                @if($offering->isAvailable())
                                    <span data-add-to-cart data-offering-id="{{ $offering->id }}" data-label="Add to cart"></span>
                                @elseif($offering->purchase_mode === 'inquiry')
                                    <a class="button button-small button-secondary" href="{{ route('contact', ['subject' => 'Availability: '.$catalogItem->title]) }}">Ask about this edition</a>
                                @else
                                    <span class="availability-note">Currently unavailable</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <p class="purchase-note">Secure checkout · Shipping calculated before payment · Course and institutional orders welcome</p>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="book-information-section">
    <div class="container book-information-grid">
        <div><p class="eyebrow">About this title</p><span class="section-index">B / 01</span></div>
        <article>
            @if($catalogItem->description)<div class="prose book-description">{!! nl2br(e($catalogItem->description)) !!}</div>@elseif($catalogItem->summary)<div class="prose book-description"><p>{{ $catalogItem->summary }}</p><p>Additional title information is being prepared by our editorial team. Contact APF Press for course adoption, library, or ordering questions.</p></div>@endif
            <dl class="specs">
                <div class="spec"><dt>Publisher</dt><dd>{{ $catalogItem->bookDetails?->publisher ?: 'APF Press' }}</dd></div>
                <div class="spec"><dt>Language</dt><dd>{{ strtoupper($primaryEdition?->language ?: 'EN') }}</dd></div>
                <div class="spec"><dt>Publication date</dt><dd>{{ $primaryEdition?->publication_date?->format('F Y') ?: 'To be confirmed' }}</dd></div>
                <div class="spec"><dt>Pages</dt><dd>{{ $primaryEdition?->page_count ?: 'To be confirmed' }}</dd></div>
                @if($primaryEdition?->isbn_13)<div class="spec"><dt>ISBN-13</dt><dd>{{ $primaryEdition->isbn_13 }}</dd></div>@endif
                @if($primaryEdition?->format)<div class="spec"><dt>Format</dt><dd>{{ $primaryEdition->format }}</dd></div>@endif
            </dl>
        </article>
    </div>
</section>

@if($related->isNotEmpty())
<section class="section related-section"><div class="container"><div class="section-heading"><div><p class="eyebrow">Continue exploring</p><h2>Related reading.</h2></div><a class="text-link" href="{{ route('catalog.index') }}">Browse the full catalogue →</a></div><div class="book-grid related-grid">@foreach($related as $item)<x-book-card :item="$item" />@endforeach</div></div></section>
@endif
@endsection
