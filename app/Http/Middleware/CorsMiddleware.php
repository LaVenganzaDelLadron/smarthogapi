<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headers = $this->corsHeaders($request);

        if ($request->isMethod('OPTIONS')) {
            return response('', 204, $headers);
        }

        $response = $next($request);

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    /**
     * Get CORS response headers.
     */
    protected function corsHeaders(Request $request): array
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigin = 'https://smarthogv2.vercel.app';

        $headers = [
            'Access-Control-Allow-Origin' => $allowedOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ];

        if ($origin !== $allowedOrigin) {
            unset($headers['Access-Control-Allow-Origin']);
        }

        return $headers;
    }
}
