<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Http\Resources\MailMessageResource;
use App\Http\Services\MailMessageService;
use App\Http\Services\MailComposeAssistService;
use App\Http\Services\MailReplySuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Jobs\SendMailMessageJob;

class MailMessageController extends Controller
{
    public function __construct(protected MailMessageService $service) {}

    protected function rules(bool $requiresTo): array
    {
        return [
            'to' => $requiresTo ? 'required|array|min:1' : 'nullable|array',
            'to.*' => 'string',
            'cc' => 'nullable|array',
            'cc.*' => 'string',
            'bcc' => 'nullable|array',
            'bcc.*' => 'string',
            'subject' => 'required|string|max:255',
            'bodyHtml' => 'nullable|string',
            'temporaryUploadIds' => 'nullable|array',
            'temporaryUploadIds.*' => 'integer|exists:temporary_uploads,id',
        ];
    }

    /**
     * Compose a new message.
     */
    public function store(Request $request): MailMessageResource
    {
        $this->validate($request, $this->rules(requiresTo: true));

        [$saved, $message, $mailMessage] = $this->service->store($request);

        SendMailMessageJob::dispatchIf($saved, $mailMessage->id);

        return MailMessageResource::make($mailMessage)
            ->additional([
                'saved' => $saved,
                'message' => $message
            ]);
    }

    public function reply(Request $request, string $id): MailMessageResource
    {
        $this->validate($request, $this->rules(requiresTo: false));

        [$saved, $message, $mailMessage] = $this->service->respond($request, $id, 'reply');

        return MailMessageResource::make($mailMessage)
            ->additional([
                'saved' => $saved,
                'message' => $message
            ]);
    }

    public function replyAll(Request $request, string $id): MailMessageResource
    {
        $this->validate($request, $this->rules(requiresTo: false));

        [$saved, $message, $mailMessage] = $this->service->respond($request, $id, 'reply-all');

        SendMailMessageJob::dispatchIf($saved, $mailMessage->id);

        return MailMessageResource::make($mailMessage)
            ->additional([
                'saved' => $saved,
                'message' => $message
            ]);
    }

    public function forward(Request $request, string $id): MailMessageResource
    {
        $this->validate($request, $this->rules(requiresTo: true));

        [$saved, $message, $mailMessage] = $this->service->respond($request, $id, 'forward');

        SendMailMessageJob::dispatchIf($saved, $mailMessage->id);

        return MailMessageResource::make($mailMessage)
            ->additional([
                'saved' => $saved,
                'message' => $message
            ]);
    }

    public function retry(string $id): MailMessageResource
    {
        $mailMessage = $this->service->retry($id);

        SendMailMessageJob::dispatch($mailMessage->id);

        return MailMessageResource::make($mailMessage)
            ->additional([
                'saved' => true,
                'message' => 'Message Queued for Sending',
            ]);
    }

    /**
     * Draft a reply to one of the user's messages with AI, for them to edit
     * on the compose form (nothing is sent).
     */
    public function suggestReply(
        Request $request,
        string $id,
        MailReplySuggestionService $suggestions
    ): JsonResponse {
        $validated = $request->validate([
            'mode' => 'nullable|in:reply,reply-all',
        ]);

        $result = $suggestions->suggest($request->user(), $id, $validated['mode'] ?? 'reply');

        return response()->json(
            collect($result)->except('status')->all(),
            $result['status'],
        );
    }

    public function draft(Request $request, MailComposeAssistService $assist): JsonResponse
    {
        $validated = $request->validate([
            'instruction' => 'required|string|max:1000',
            'subject' => 'nullable|string|max:255',
            'to' => 'nullable|array|max:50',
            'to.*' => 'string|max:255',
        ]);

        $result = $assist->draft($request->user(), $validated['instruction'], $validated['subject'] ?? null, $validated['to'] ?? []);

        return response()->json(
            collect($result)->except('status')->all(),
            $result['status'],
        );
    }

    public function revamp(Request $request, MailComposeAssistService $assist): JsonResponse
    {
        $validated = $request->validate([
            'bodyHtml' => 'required|string|max:100000',
            'style' => 'required|in:'.implode(',', array_keys(MailComposeAssistService::STYLES)),
            'subject' => 'nullable|string|max:255',
        ]);

        $result = $assist->revamp($request->user(), $validated['bodyHtml'], $validated['style'], $validated['subject'] ?? null);

        return response()->json(
            collect($result)->except('status')->all(),
            $result['status'],
        );
    }
}
