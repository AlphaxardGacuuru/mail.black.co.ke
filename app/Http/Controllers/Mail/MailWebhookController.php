<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Http\Services\MailInboundService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MailWebhookController extends Controller
{
    public function __construct(protected MailInboundService $service) {}

    public function mailgunInbound(Request $request): Response
    {
        try {
            [$status, $message, $data] = $this->service->handleInboundMail($request);
        } catch (\Throwable $exception) {
            Log::error('Mailgun inbound webhook failed', ['error' => $exception->getMessage()]);

            $status = false;
            $message = 'Failed to process inbound mail';
            $data = null;
        }

        return response([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], 200);
    }
}
