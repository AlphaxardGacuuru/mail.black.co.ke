<?php

namespace App\Http\Controllers;

use App\Models\MailgunAccount;
use App\Models\Submission;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class FilePondController extends Controller
{
    /**
     * Reusable temporary upload handler for FilePond process endpoint.
     */
    private function uploadToTemporaryStore(Request $request, string $inputName, string $directory = 'temporary-uploads'): TemporaryUpload
    {
        $file = $request->file($inputName);

        $path = $file->store($directory, 'public');

        $temporaryUpload = new TemporaryUpload;
        $temporaryUpload->disk = 'public';
        $temporaryUpload->path = $path;
        $temporaryUpload->original_name = $file->getClientOriginalName();
        $temporaryUpload->mime_type = $file->getClientMimeType();
        $temporaryUpload->size = $file->getSize();
        $temporaryUpload->save();

        return $temporaryUpload;
    }

    /*
     * Handle Profile Pic Upload */
    public function updateAvatar(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            'filepond-avatar' => 'required|image',
        ]);

        $avatar = $request->file('filepond-avatar')->store('avatars', 'public');

        $user = User::findOrFail($id);

        // Delete profile pic if it's not the default one
        if ($user->avatar != '/storage/avatars/male_avatar.png') {

            // Get old avatar and delete it
            $oldAvatar = substr($user->avatar, 9);

            Storage::disk("public")->delete($oldAvatar);
        }

        $user->avatar = $avatar;
        $user->save();

        return response("Account updated", 200);
    }

    /*
     * Handle Mailgun Account Profile Picture Upload */
    public function updateMailgunAccountAvatar(Request $request, MailgunAccount $account): Response
    {
        abort_unless($account->user_id === $request->user()->id, 404);

        $this->validate($request, [
            'filepond-mailgun-account-avatar' => 'required|image|max:5120',
        ]);

        $avatar = $request
            ->file('filepond-mailgun-account-avatar')
            ->store('mailgun-avatars', 'public');

        $oldAvatar = $account->getRawOriginal('avatar');

        if ($oldAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }

        $account->avatar = $avatar;
        $account->save();

        return response(['avatar' => $account->avatar], 200);
    }

    /*
     * Handle Material Upload */
    public function storeMaterial(Request $request): string
    {
        $this->validate($request, [
            'filepond-material' => 'required|file',
        ]);

        // Store material
        $material = $request->file('filepond-material')->store('public/materials');

        $material = substr($material, 7);

        return $material;
    }

    /*
     * Handle Material Delete */
    public function destoryMaterial(int|string $id): Response
    {
        Storage::delete('public/materials/' . $id);

        return response("Material deleted", 200);
    }

    /*
     * Discussion Forum
     */

    /*
     * Handle Attachment Upload */
    public function storeAttachment(Request $request): string
    {
        $this->validate($request, [
            'filepond-attachment' => 'required|file',
        ]);

        // Store Attachment
        $attachment = $request->file('filepond-attachment')->store('public/attachments');

        $attachment = substr($attachment, 7);

        return $attachment;
    }

    /*
     * Handle Attachment Delete */
    public function destoryAttachment(int|string $id): Response
    {
        Storage::delete('public/attachments/' . $id);

        return response("Attachment deleted", 200);
    }

    /*
     * Store Submissions */
    public function storeSubmission(Request $request, int|string $sessionId, int|string $unitId, int|string $week, int|string $userId, string $type): Response
    {
        $this->validate($request, [
            "filepond-file" => "required|file",
        ]);

        $attachment = $request
            ->file('filepond-file')
            ->store('public/submissions');

        $attachment = substr($attachment, 7);

        $submissionQuery = Submission::where("academic_session_id", $sessionId)
            ->where("unit_id", $unitId)
            ->where("week", $week)
            ->where("user_id", $userId)
            ->where("type", $type);

        $submissionDoesntExist = $submissionQuery->doesntExist();

        if ($submissionDoesntExist) {
            // Add New Submission
            $submission = new Submission;
            $submission->academic_session_id = $sessionId;
            $submission->unit_id = $unitId;
            $submission->week = $week;
            $submission->user_id = $userId;
            $submission->type = $type;
            $submission->attachment = $attachment;
            $submission->save();

            $message = $type . " saved";
        } else {
            $submission = $submissionQuery->first();

            // Get old attachment and delete it
            $oldAttachment = substr($submission->attachment, 8);

            Storage::disk("public")->delete($oldAttachment);

            // Update Submission
            $submission->attachment = $attachment;
            $submission->save();

            $message = $type . " updated";
        }

        return response($message, 200);
    }

    /*
     * Support Ticket Attachments
     */

    public function storeSupportTicketAttachment(Request $request): Response
    {
        $this->validate($request, [
            'filepond-support-ticket-attachments' => 'required|file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx',
        ]);

        $temporaryUpload = $this->uploadToTemporaryStore(
            $request,
            'filepond-support-ticket-attachments',
            'temporary-uploads/support-tickets'
        );

        // FilePond expects a plain server id string.
        return response((string) $temporaryUpload->id, 200);
    }

    public function destroySupportTicketAttachment(int|string $id): Response
    {
        $temporaryUpload = TemporaryUpload::find($id);

        if (! $temporaryUpload) {
            return response('Attachment already removed', 200);
        }

        Storage::disk($temporaryUpload->disk)->delete($temporaryUpload->path);
        $temporaryUpload->delete();

        return response('Attachment deleted', 200);
    }

    /*
     * Mail Compose Attachments
     */

    public function storeMailAttachment(Request $request): Response
    {
        $this->validate($request, [
            'filepond-mail-attachments' => 'required|file|max:25600',
        ]);

        $temporaryUpload = $this->uploadToTemporaryStore(
            $request,
            'filepond-mail-attachments',
            'temporary-uploads/mail'
        );

        return response((string) $temporaryUpload->id, 200);
    }

    public function destroyMailAttachment(int|string $id): Response
    {
        $temporaryUpload = TemporaryUpload::find($id);

        if (! $temporaryUpload) {
            return response('Attachment already removed', 200);
        }

        Storage::disk($temporaryUpload->disk)->delete($temporaryUpload->path);
        $temporaryUpload->delete();

        return response('Attachment deleted', 200);
    }
}
