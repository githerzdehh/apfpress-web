@extends('layouts.app')

@section('title', 'Contact APF Press | Orders, Rights & Publishing')
@section('description', 'Contact APF Press in Toronto about book orders, course adoption, rights, manuscripts, or academic publishing inquiries.')

@section('content')
<header class="page-hero contact-hero">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span>Contact</span></nav>
        <div class="page-hero-grid"><div><p class="eyebrow">Get in touch</p><h1>Let’s continue the conversation.</h1></div><div class="page-hero-aside"><span class="folio">Toronto / Canada</span><p>Questions about a title, course adoption, rights, an order, or a publishing proposal? Write directly to our team.</p></div></div>
    </div>
</header>

<section class="section contact-section">
    <div class="container contact-grid">
        <div class="contact-details">
            <p class="eyebrow">A small press with a direct line</p>
            <h2>Talk to a person,<br>not a ticket number.</h2>
            <div class="contact-list">
                <div><span>Orders & manuscripts</span><a href="mailto:apf.press@rogers.com">apf.press@rogers.com</a><a href="tel:+14168171266">416-817-1266</a></div>
                <div><span>Editorial team</span><p>R. Doyle, PhD · Senior Editor<br>Andrew Urie, PhD · Managing Editor</p></div>
                <div><span>Mailing address</span><address>APF Press<br>4 Carnegie Court<br>Toronto, ON M2M 1W2<br>Canada</address></div>
            </div>
        </div>
        <div class="form-card contact-form-card">
            <div class="form-card-heading"><p class="eyebrow">Send an inquiry</p><h2>How can we help?</h2><p>We will route your note to the right member of our small team.</p></div>
            <form action="{{ route('contact.store') }}" method="post">@csrf
                <div class="field honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="form-grid">
                    <div class="field"><label for="name">Your name <span>Required</span></label><input class="input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required></div>
                    <div class="field"><label for="email">Email <span>Required</span></label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
                    <div class="field field-full"><label for="subject">Subject <span>Required</span></label><input class="input" id="subject" name="subject" value="{{ old('subject', request('subject')) }}" required></div>
                    <div class="field field-full"><label for="message">Your message <span>Required</span></label><textarea class="textarea" id="message" name="message" required>{{ old('message') }}</textarea></div>
                </div>
                <button class="button form-submit" type="submit">Send your message <span aria-hidden="true">→</span></button>
            </form>
        </div>
    </div>
</section>
@endsection
