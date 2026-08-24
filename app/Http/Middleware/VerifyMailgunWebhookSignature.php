<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyMailgunWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->input('timestamp');
        $token = $request->input('token');
        $signature = $request->input('signature');

        $secret = config('services.mailgun.webhook_signing_secret') ?: config('services.mailgun.secret');

        if (! $timestamp || ! $token || ! $signature || ! $secret) {
            abort(403, 'Missing Mailgun webhook signature.');
        }

        if (abs(time() - (int) $timestamp) > 900) {
            abort(403, 'Stale Mailgun webhook timestamp.');
        }

        $expected = hash_hmac('sha256', $timestamp.$token, $secret);

        if (! hash_equals($expected, (string) $signature)) {
            abort(403, 'Invalid Mailgun webhook signature.');
        }

        $seenKey = "mailgun-webhook-token:{$token}";

        if (Cache::has($seenKey)) {
            abort(403, 'Mailgun webhook token already used.');
        }

        Cache::put($seenKey, true, now()->addMinutes(20));

        return $next($request);
    }
}
