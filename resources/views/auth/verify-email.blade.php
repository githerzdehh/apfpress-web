@extends('layouts.app')
@section('title', 'Verify Your Email | APF Press')
@section('noindex', 'true')
@section('content')
<section class="auth-shell"><div class="container auth-grid"><aside class="auth-aside"><p class="eyebrow eyebrow-gold">One more step</p><blockquote>Confirm your address.<br><em>Then continue reading.</em></blockquote><p>Email verification protects orders and digital editions tied to your account.</p></aside><div class="auth-card form-card"><div class="form-card-heading"><p class="eyebrow">Check your inbox</p><h1>Verify your email.</h1><p>We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. The message may take a minute to arrive.</p></div><div class="button-stack"><form method="post" action="{{ route('verification.send') }}">@csrf<button class="button" type="submit">Send another link</button></form><form method="post" action="{{ route('logout') }}">@csrf<button class="button button-secondary" type="submit">Sign out</button></form></div></div></div></section>
@endsection
