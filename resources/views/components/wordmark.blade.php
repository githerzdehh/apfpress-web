@props(['compact' => false])

<span {{ $attributes->class(['wordmark', 'wordmark--compact' => $compact]) }}>
    <img class="official-logo" src="{{ asset('images/apf-press-logo.png') }}" alt="" width="357" height="65" decoding="async">
</span>
