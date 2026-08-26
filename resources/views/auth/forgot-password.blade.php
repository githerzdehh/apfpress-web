@extends('layouts.app')
@section('title', 'Reset Password | APF Press')
@section('noindex', 'true')
@section('content')
<section class="auth-shell"><div class="container auth-grid"><aside class="auth-aside"><p class="eyebrow eyebrow-gold">Account access</p><blockquote>A clear path<br><em>back to your library.</em></blockquote><p>We will send a time-limited reset link to the address on your account.</p></aside><div class="auth-card form-card"><div class="form-card-heading"><p class="eyebrow">Password assistance</p><h1>Reset your password.</h1><p>Enter your account email and check your inbox for the next step.</p></div><form method="post" action="{{ route('password.email') }}">@csrf<div class="field"><label for="email">Email address</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div><button class="button form-submit" type="submit">Email reset link <span aria-hidden="true">→</span></button></form><p class="form-alternate"><a class="text-link" href="{{ route('login') }}">Return to sign in</a></p></div></div></section>
@endsection
