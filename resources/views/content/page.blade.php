@extends('layouts.app')

@section('title', $page->seo_title ?: $page->title.' | APF Press')
@section('description', $page->seo_description ?: Str::limit(collect($page->content_blocks)->pluck('text')->join(' '), 160))

@section('content')
<header class="page-hero content-hero">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span>{{ $page->title }}</span></nav>
        <div class="page-hero-grid">
            <div><p class="eyebrow">{{ $page->slug === 'publish-with-us' ? 'For authors and changemakers' : 'Independent by design' }}</p><h1>{{ $page->title }}</h1></div>
            <div class="page-hero-aside"><span class="folio">APF / {{ $page->slug === 'publish-with-us' ? 'Submissions' : 'About' }}</span><p>{{ $page->slug === 'publish-with-us' ? 'Thoughtful proposals begin a conversation. A complete manuscript can come later.' : 'A small Canadian press making room for serious ideas, difficult questions, and overlooked voices.' }}</p></div>
        </div>
    </div>
</header>

<section class="section content-section">
    <div class="container content-layout">
        <aside class="content-rail">
            <p class="eyebrow">{{ $page->slug === 'publish-with-us' ? 'A considered process' : 'Our position' }}</p>
            <span class="section-index">A / 02</span>
            <blockquote>{{ $page->slug === 'publish-with-us' ? 'The best proposals tell us why the work matters—and who needs to read it.' : 'Academic publishing should expand the conversation, not narrow it.' }}</blockquote>
        </aside>
        <article class="content-blocks">
            @foreach($page->content_blocks ?? [] as $block)
                @switch($block['type'] ?? 'paragraph')
                    @case('lead')<p class="content-lead">{{ $block['text'] }}</p>@break
                    @case('heading')<h2>{{ $block['text'] }}</h2>@break
                    @case('notice')<div class="alert">{{ $block['text'] }}</div>@break
                    @default<div class="prose"><p>{{ $block['text'] }}</p></div>
                @endswitch
            @endforeach

            @if($page->slug === 'about')
            <div class="editorial-principles">
                <div><span>01</span><strong>Rigour without orthodoxy</strong></div>
                <div><span>02</span><strong>Independence with purpose</strong></div>
                <div><span>03</span><strong>Ideas connected to community</strong></div>
            </div>
            <a class="button" href="{{ route('catalog.index') }}">Explore our books <span aria-hidden="true">↗</span></a>
            @endif

            @if($page->slug === 'publish-with-us')
            <div class="submission-note"><span aria-hidden="true">i</span><p><strong>Before you begin</strong>Please do not send a complete manuscript unless requested. A clear abstract or proposal is enough for our first review.</p></div>
            <div class="form-card proposal-form-card">
                <div class="form-card-heading"><p class="eyebrow">Proposal form</p><h2>Start a conversation.</h2><p>Fields marked required help our editors understand the work and its intended audience.</p></div>
                <form action="{{ route('submissions.store') }}" method="post" enctype="multipart/form-data">@csrf
                    <div class="form-grid">
                        <div class="field"><label for="name">Your name <span>Required</span></label><input class="input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required></div>
                        <div class="field"><label for="email">Email <span>Required</span></label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
                        <div class="field field-full"><label for="working_title">Working title <span>Required</span></label><input class="input" id="working_title" name="working_title" value="{{ old('working_title') }}" required></div>
                        <div class="field field-full"><label for="genre">Subject or discipline</label><input class="input" id="genre" name="genre" value="{{ old('genre') }}"></div>
                        <div class="field field-full"><label for="abstract">Proposal or abstract <span>Required</span></label><textarea class="textarea" id="abstract" name="abstract" required>{{ old('abstract') }}</textarea></div>
                        <div class="field field-full"><label for="manuscript">Optional proposal file</label><input class="input file-input" id="manuscript" name="manuscript" type="file" accept=".pdf,.doc,.docx"><p class="help">PDF or Word, up to 20 MB. Files are stored privately.</p></div>
                    </div>
                    <button class="button form-submit" type="submit">Send your proposal <span aria-hidden="true">→</span></button>
                </form>
            </div>
            @endif
        </article>
    </div>
</section>
@endsection
