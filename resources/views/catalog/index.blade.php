@extends('layouts.app')

@section('title', 'Academic Books & E-books | APF Press')
@section('description', 'Browse independent Canadian scholarship from APF Press across social justice, law, human rights, race, economics, healthcare, and critical inquiry.')

@section('content')
<header class="page-hero catalogue-hero">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span>Books</span></nav>
        <div class="page-hero-grid">
            <div><p class="eyebrow">The APF Press catalogue</p><h1>Books for curious and courageous readers.</h1></div>
            <div class="page-hero-aside"><span class="folio">Catalogue / {{ str_pad((string) $items->total(), 2, '0', STR_PAD_LEFT) }}</span><p>Critical scholarship, community-centred perspectives, and ideas with the power to shift a conversation.</p></div>
        </div>
    </div>
</header>

<section class="catalogue-section">
    <div class="container">
        <form class="filter-bar" action="{{ route('catalog.index') }}" method="get">
            <div class="filter-heading"><span class="eyebrow">Find a title</span><span>{{ $items->total() }} {{ Str::plural('book', $items->total()) }}</span></div>
            <div class="field search-field"><label for="q">Title, author, or subject</label><input class="input" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search the catalogue…"></div>
            <div class="field"><label for="format">Format</label><select class="select" id="format" name="format"><option value="">All formats</option><option value="print_book" @selected(($filters['format'] ?? '') === 'print_book')>Print books</option><option value="ebook" @selected(($filters['format'] ?? '') === 'ebook')>E-books</option></select></div>
            <div class="field"><label for="category">Subject</label><select class="select" id="category" name="category"><option value="">All subjects</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="field"><label for="sort">Order</label><select class="select" id="sort" name="sort"><option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest first</option><option value="title" @selected(($filters['sort'] ?? '') === 'title')>Title A–Z</option><option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>Price low–high</option><option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>Price high–low</option></select></div>
            <button class="button filter-submit" type="submit">Apply filters</button>
        </form>

        <div class="catalogue-meta">
            <p>Showing {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} of {{ $items->total() }}</p>
            @if(request()->hasAny(['q','format','category','sort']))<a class="text-link" href="{{ route('catalog.index') }}">Clear all filters ×</a>@endif
        </div>

        @if($items->isEmpty())
            <div class="empty-state"><span class="empty-mark">APF</span><div><p class="eyebrow">No matching titles</p><h2>Try a broader line of inquiry.</h2><p>Search another author or subject, or return to the complete catalogue.</p><a class="button" href="{{ route('catalog.index') }}">See every title</a></div></div>
        @else
            <div class="book-grid catalogue-grid">@foreach($items as $item)<x-book-card :item="$item" />@endforeach</div>
            <div class="pagination">{{ $items->links() }}</div>
        @endif
    </div>
</section>
@endsection
