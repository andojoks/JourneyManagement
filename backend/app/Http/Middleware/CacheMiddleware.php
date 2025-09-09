<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|null  $cacheKey
     * @param  int  $ttl
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $cacheKey = null, int $ttl = 300)
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Skip caching for authenticated users with specific headers
        if ($request->header('X-No-Cache') === 'true') {
            return $next($request);
        }

        // Generate cache key if not provided
        if (!$cacheKey) {
            $cacheKey = $this->generateCacheKey($request);
        }

        // Try to get cached response
        $cachedResponse = Cache::get($cacheKey);
        
        if ($cachedResponse) {
            Log::info('Cache hit', ['key' => $cacheKey, 'url' => $request->url()]);
            return response()->json($cachedResponse);
        }

        // Process the request
        $response = $next($request);

        // Cache successful responses
        if ($response->getStatusCode() === 200) {
            $responseData = $response->getData(true);
            
            // Only cache if response has data
            if (!empty($responseData)) {
                Cache::put($cacheKey, $responseData, $ttl);
                Log::info('Cache stored', ['key' => $cacheKey, 'ttl' => $ttl, 'url' => $request->url()]);
            }
        }

        return $response;
    }

    /**
     * Generate a cache key based on request parameters
     */
    private function generateCacheKey(Request $request): string
    {
        $key = 'http_cache:' . md5($request->url() . '?' . $request->getQueryString());
        
        // Include user ID if authenticated
        if ($request->user()) {
            $key .= ':user:' . $request->user()->id;
        }

        return $key;
    }
}
