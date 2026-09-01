<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Restrict access to the single configured admin email.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->email !== config('admin.email')) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
