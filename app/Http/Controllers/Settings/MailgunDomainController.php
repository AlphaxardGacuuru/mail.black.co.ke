<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MailgunDomainRequest;
use App\Http\Resources\MailgunDomainResource;
use App\Models\MailgunDomain;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MailgunDomainController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->domains($request->user()));
    }

    public function store(MailgunDomainRequest $request): JsonResponse
    {
        $request->user()->mailgunDomains()->create($request->validated());

        return response()
            ->json([
                'message' => 'Mailgun Domain Added.',
                'domains' => $this->domains($request->user()),
            ]);
    }

    public function update(MailgunDomainRequest $request, MailgunDomain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 404);

        $data = $request->validated();

        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        $domain->update($data);

        return response()
            ->json([
                'message' => 'Mailgun domain updated.',
                'domains' => $this->domains($request->user()),
            ]);
    }

    public function destroy(Request $request, MailgunDomain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 404);
        $domain->delete();

        return response()
            ->json([
                'message' => 'Mailgun domain removed.',
                'domains' => $this->domains($request->user()),
            ]);
    }

    private function domains($user): array
    {
        return MailgunDomainResource::collection(
            $user->mailgunDomains()->get()
        )->resolve();
    }
}
