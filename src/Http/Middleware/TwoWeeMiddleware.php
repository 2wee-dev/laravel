<?php

namespace TwoWee\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoWeeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->acceptsJson()) {
            return response()->json(['error' => 'Not Acceptable'], 406);
        }

        if (! $request->isSecure() && ! app()->environment('local', 'testing')) {
            return response()->json(['error' => 'HTTPS required'], 403);
        }

        return $next($request);
    }
}
