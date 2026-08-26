@extends('layouts.app')
@section('title', 'Order Received | APF Press')
@section('noindex', 'true')
@section('content')
<section class="success-shell"><div class="container success-grid"><div class="success-mark" aria-hidden="true">✓</div><div><p class="eyebrow">Order {{ $order->number }}</p><h1>Thank you.</h1>@if($order->payment_status === 'paid')<p class="content-lead">Your payment is confirmed.</p><p>We have emailed your receipt. Print orders will move into fulfilment, and available e-books are now in your account.</p>@else<p class="content-lead">Your payment is being confirmed.</p><p>This page updates through the payment provider. Your account will show the order as paid as soon as confirmation arrives.</p>@endif<a class="button" href="{{ route('account.index') }}">View your account <span aria-hidden="true">→</span></a></div></div></section>
@endsection
