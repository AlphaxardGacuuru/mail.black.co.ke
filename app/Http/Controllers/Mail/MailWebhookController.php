<?php

namespace App\Http\Controllers\Mail;

use App\Events\MailMessageReceivedEvent;
use App\Http\Controllers\Controller;
use App\Http\Services\MailInboundService;
use App\Http\Services\MailgunWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;
use Illuminate\Support\Facades\Log;

class MailWebhookController extends Controller
{
    public function __construct(
        protected MailInboundService $service,
        protected MailgunWebhookService $eventService,
    ) {}

    public function mailgunInbound(Request $request): Response
    {
        try {
            [$status, $message, $data, $saved] = $this->service->handleInboundMail($request);
        } catch (Throwable $exception) {
            Log::error('Mailgun inbound webhook failed', ['error' => $exception->getMessage()]);

            $status = false;
            $message = 'Failed To Process Inbound Mail';
            $data = null;
            $saved = false;
        }

        MailMessageReceivedEvent::dispatchIf($saved, $data);

        return response([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    public function mailgunEvents(Request $request): Response
    {
        try {
            $result = $this->eventService->handleEvent($request);
        } catch (Throwable $exception) {
            Log::error('Mailgun event webhook failed', ['error' => $exception->getMessage()]);
            
            $result = [
                'status' => false,
                'message' => 'Failed to process event',
                'updated' => 0
            ];
        }

        return response($result, 200);
    }
}
