@php
    $offering = $item->offerings->sortBy(fn ($offer) => $offer->kind === 'print_book' ? 0 : 1)->first();
    $format = $offering?->kind === 'ebook' ? 'E-book' : 'Print';
    $authors = $item->contributors->filter(fn($contributor) => $contributor->pivot->role === 'author');
    $editors = $item->contributors->filter(fn($contributor) => $contributor->pivot->role === 'editor');
    $credit = $authors->isNotEmpty() ? $authors->pluck('name')->join(', ') : ($editors->isNotEmpty() ? 'Edited by '.$editors->pluck('name')->join(', ') : 'Author details forthcoming');
@endphp
<article class="book-card">
    <a class="book-card-link" href="{{ route('catalog.show', $item->slug) }}" aria-label="View {{ $item->title }}">
        <div class="book-cover-wrap">
            @if($item->cover?->url)<img class="book-cover" src="{{ $item->cover->url }}" alt="{{ $item->cover->alt_text ?: $item->title.' book cover' }}" loading="lazy" width="480" height="667">@else<div class="book-placeholder">{{ $item->title }}</div>@endif
            <span class="format-chip">{{ $format }}</span>
            <span class="book-view" aria-hidden="true">View title ↗</span>
        </div>
        <div class="book-card-copy">
            <p class="book-meta">{{ $credit }}</p>
            <h3>{{ $item->title }}</h3>
            <div class="book-card-foot"><span>{{ $offering?->formatted_price ?: ($offering?->purchase_mode === 'inquiry' ? 'Inquire for availability' : 'Details forthcoming') }}</span><span aria-hidden="true">→</span></div>
        </div>
    </a>
</article>
