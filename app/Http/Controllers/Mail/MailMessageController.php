<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Http\Resources\MailMessageResource;
use App\Http\Services\MailMessageService;
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
}
