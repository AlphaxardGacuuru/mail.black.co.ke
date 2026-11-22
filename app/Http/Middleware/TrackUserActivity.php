<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    /**
     * Sign-ins are rare since API tokens last, so activity is read from
     * API requests instead. Checked after the route runs, once auth:sanctum
     * has resolved the user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $request->user()?->markActive();

        return $response;
    }
}
