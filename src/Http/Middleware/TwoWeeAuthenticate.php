<?php

namespace TwoWee\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TwoWeeAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('twowee.auth.enabled', true)) {
            return $next($request);
        }

        if (! Auth::guard('twowee')->check()) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        return $next($request);
    }
}
