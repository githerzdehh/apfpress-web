<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(40);
        Vite::useCspNonce($nonce);
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'img-src' => ["'self'", 'data:', 'https://apfpress.com', 'https://*.apfpress.com'],
            'font-src' => ["'self'", 'data:'],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", 'https://js.stripe.com', 'https://www.paypal.com'],
            'frame-src' => ['https://js.stripe.com', 'https://www.paypal.com'],
            'connect-src' => ["'self'", 'https://api.stripe.com', 'https://www.paypal.com'],
        ];

        if (config('app.env') === 'local') {
            $viteOrigin = rtrim((string) config('apf.vite_dev_origin'), '/');

            if ($viteOrigin !== '') {
                $websocketOrigin = preg_replace('/^http/', 'ws', $viteOrigin);
                $directives['img-src'][] = $viteOrigin;
                $directives['font-src'][] = $viteOrigin;
                $directives['style-src'][] = $viteOrigin;
                $directives['script-src'][] = $viteOrigin;
                $directives['connect-src'][] = $viteOrigin;
                $directives['connect-src'][] = $websocketOrigin;
            }
        }

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', array_unique($sources)))
            ->implode('; ');
    }
}
