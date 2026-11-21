<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Allow this origin to use camera/microphone for 1:1 WebRTC calls.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Vite's dev server relies on inline scripts and a local websocket, so
        // the strict policy is only applied to HTML responses outside local.
        if (! app()->environment('local') && $this->isHtml($response)) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($response));
        }

        return $response;
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function contentSecurityPolicy(Response $response): string
    {
        $realtimeHost = parse_url((string) config('bhn.realtime.url'), PHP_URL_HOST);
        $websocket = $realtimeHost ? "wss://{$realtimeHost} https://{$realtimeHost}" : '';

        // Inertia renders the initial page payload as an inline JSON script.
        // Hash it so the strict script-src policy can allow exactly that block.
        $scriptSrc = "script-src 'self'";

        if (preg_match('#<script data-page="[^"]*" type="application/json">(.*?)</script>#s', (string) $response->getContent(), $matches)) {
            $scriptSrc .= " 'sha256-".base64_encode(hash('sha256', $matches[1], true))."'";
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            $scriptSrc,
            "connect-src 'self' ".$websocket,
        ]);
    }
}
