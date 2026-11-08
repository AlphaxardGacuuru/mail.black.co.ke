<?php

use App\Http\Controllers\Admin\AdminAiUsageController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminWebhookController;
use App\Http\Controllers\Chat\ChatAttachmentController;
use App\Http\Controllers\Chat\ChatConversationController;
use App\Http\Controllers\Chat\ChatLocationShareController;
use App\Http\Controllers\Chat\ChatMessageController;
use App\Http\Controllers\FilePondController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\Jobs\CvRevampController;
use App\Http\Controllers\Jobs\JobApplicationController;
use App\Http\Controllers\Jobs\JobApplicationFormController;
use App\Http\Controllers\Jobs\JobSearchController;
use App\Http\Controllers\Mail\MailAttachmentController;
use App\Http\Controllers\Mail\MailLabelController;
use App\Http\Controllers\Mail\MailMessageController;
use App\Http\Controllers\Mail\MailThreadController;
use App\Http\Controllers\Mail\MailWebhookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('auth', [UserController::class, 'auth']);

    // Before the resource routes, which would read it as a thread id.
    Route::get('threads/unread-count', [MailThreadController::class, 'unreadCount']);

    // Only your own account; listing, creating and deleting people is for
    // the admin (below).
    Route::apiResource('users', UserController::class)
        ->only(['show', 'update']);

    Route::apiResources([
        "notifications" => NotificationController::class,
        "integrations" => IntegrationController::class,
        "support-tickets" => SupportTicketController::class,
        "settings" => SettingController::class,
        "threads" => MailThreadController::class,
        "labels" => MailLabelController::class,
    ]);

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    Route::get('stories', [StoryController::class, 'index']);
    Route::get('stories/{id}', [StoryController::class, 'show']);
    Route::post('stories/{id}/seen', [StoryController::class, 'seen']);

    Route::get('chat/users', [ChatConversationController::class, 'users']);
    Route::get('chat/conversations', [ChatConversationController::class, 'index']);
    Route::post('chat/conversations', [ChatConversationController::class, 'store']);
    Route::get('chat/conversations/{id}', [ChatConversationController::class, 'show']);
    Route::post('chat/conversations/{id}/read', [ChatConversationController::class, 'markRead']);
    Route::post('chat/conversations/{id}/archive', [ChatConversationController::class, 'archive']);
    Route::delete('chat/conversations/{id}', [ChatConversationController::class, 'destroy']);
    Route::post('chat/conversations/{id}/messages', [ChatMessageController::class, 'store']);
    Route::delete('chat/messages/{id}', [ChatMessageController::class, 'destroy']);
    Route::post('chat/messages/{id}/star', [ChatMessageController::class, 'star']);
    Route::post('chat/messages/{id}/forward', [ChatMessageController::class, 'forward']);

    Route::post('chat/conversations/{id}/location', [ChatLocationShareController::class, 'start']);
    Route::patch('chat/conversations/{id}/location', [ChatLocationShareController::class, 'update'])
        ->middleware('throttle:12,1');
    Route::delete('chat/conversations/{id}/location', [ChatLocationShareController::class, 'stop']);

    Route::get('chat/attachments/{id}/download', [ChatAttachmentController::class, 'download'])
        ->name('chat.attachments.download');

    Route::post('threads/{id}/labels', [MailThreadController::class, 'attachLabel']);
    Route::delete('threads/{id}/labels/{labelId}', [MailThreadController::class, 'detachLabel']);

    Route::post('messages', [MailMessageController::class, 'store']);
    Route::post('messages/{id}/reply', [MailMessageController::class, 'reply']);
    Route::post('messages/{id}/reply-all', [MailMessageController::class, 'replyAll']);
    Route::post('messages/{id}/forward', [MailMessageController::class, 'forward']);
    Route::post('messages/{id}/retry', [MailMessageController::class, 'retry']);
    Route::post('messages/{id}/suggest-reply', [MailMessageController::class, 'suggestReply'])
        ->middleware('throttle:20,1');

    Route::get('attachments/{id}/download', [MailAttachmentController::class, 'download'])
        ->name('attachments.download');

    // Which notifications arrive in the app, by push and by email
    Route::get('notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);

    // Job search
    Route::get('job-search', [JobSearchController::class, 'show']);
    Route::put('job-search', [JobSearchController::class, 'update']);
    Route::delete('job-search', [JobSearchController::class, 'destroy']);
    Route::post('job-search/cv', [JobSearchController::class, 'uploadCv'])->middleware('throttle:10,1');
    Route::put('job-search/cover-letter', [JobSearchController::class, 'updateCoverLetter']);
    Route::put('job-search/setup-step', [JobSearchController::class, 'updateSetupStep']);
    Route::post('job-search/cover-letter', [JobSearchController::class, 'uploadCoverLetter'])->middleware('throttle:10,1');
    Route::post('job-search/career-pages', [JobSearchController::class, 'storeCareerPage'])->middleware('throttle:10,1');
    Route::delete('job-search/career-pages/{id}', [JobSearchController::class, 'destroyCareerPage']);
    Route::get('job-search/mailbox', [JobSearchController::class, 'mailboxSuggestions'])->middleware('throttle:20,1');

    // Revamping the CV with the AI
    Route::get('job-search/cv-revamp', [CvRevampController::class, 'show']);
    Route::post('job-search/cv-revamp', [CvRevampController::class, 'store'])->middleware('throttle:5,1');
    Route::patch('job-search/cv-revamp', [CvRevampController::class, 'update']);
    Route::post('job-search/cv-revamp/use', [CvRevampController::class, 'use']);
    Route::delete('job-search/cv-revamp/use', [CvRevampController::class, 'stopUsing']);
    Route::put('job-search/cv-tailoring', [CvRevampController::class, 'updateTailoring']);
    Route::post('job-search/mailbox', [JobSearchController::class, 'storeMailbox'])->middleware('throttle:5,1');

    Route::get('job-applications', [JobApplicationController::class, 'index']);
    Route::get('job-applications/counts', [JobApplicationController::class, 'counts']);
    Route::post('job-applications/shared', [JobApplicationController::class, 'storeShared'])->middleware('throttle:10,1');
    Route::patch('job-applications/{id}', [JobApplicationController::class, 'update']);
    Route::post('job-applications/{id}/approve', [JobApplicationController::class, 'approve']);
    Route::post('job-applications/{id}/skip', [JobApplicationController::class, 'skip']);
    Route::post('job-applications/{id}/cv', [CvRevampController::class, 'tailor'])->middleware('throttle:10,1');

    // Applying on the employer's own form
    Route::post('job-applications/{id}/form', [JobApplicationFormController::class, 'prepare'])->middleware('throttle:10,1');
    Route::patch('job-applications/{id}/form', [JobApplicationFormController::class, 'update']);
    Route::post('job-applications/{id}/form/submit', [JobApplicationFormController::class, 'submit'])->middleware('throttle:10,1');

});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('webhooks', [AdminWebhookController::class, 'index'])->name('webhooks');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::apiResource('users', UserController::class)
        ->only(['store', 'destroy']);
    Route::patch('users/{id}/verify', [UserController::class, 'verify'])->name('users.verify');
});

// AI spend is for the same people as Telescope (see the viewAiUsage gate).
Route::middleware(['auth:sanctum', 'can:viewAiUsage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('ai-usage', [AdminAiUsageController::class, 'index'])->name('ai-usage.index');
});

/*
 * Filepond Controller
 */
Route::prefix('filepond')->group(function () {
    Route::controller(FilePondController::class)->group(function () {
        // User
        Route::post('avatar/{id}', 'updateAvatar');

        // Support Tickets
        Route::post('support-tickets/attachments', 'storeSupportTicketAttachment');
        Route::delete('support-tickets/attachments/{id}', 'destroySupportTicketAttachment');

        Route::post('attachments', 'storeMailAttachment');
        Route::delete('attachments/{id}', 'destroyMailAttachment');

        Route::post('chat-attachments', 'storeChatAttachment');
        Route::delete('chat-attachments/{id}', 'destroyChatAttachment');
    });
});

// Mailgun account avatars require an authenticated owner check.
Route::middleware('auth:sanctum')->post(
    'filepond/mailgun-accounts/{account}/avatar',
    [FilePondController::class, 'updateMailgunAccountAvatar']
);

// Signed links, for <img> tags, which can't send the bearer token.
Route::get('job-applications/{id}/form/screenshots/{name}', [JobApplicationFormController::class, 'screenshot'])
    ->whereIn('name', ['form', 'filled', 'confirmation'])
    ->middleware('signed')
    ->name('job-application-forms.screenshot');

// Signed links to CVs, which open in a new tab.
Route::get('cv-revamps/{id}/download/{format}', [CvRevampController::class, 'download'])
    ->whereIn('format', ['pdf', 'docx'])
    ->middleware(['signed', 'throttle:20,1'])
    ->name('cv-revamps.download');
Route::get('job-applications/{id}/cv/download', [CvRevampController::class, 'downloadTailored'])
    ->middleware('signed')
    ->name('job-applications.cv');

Route::post('webhooks/mailgun', [MailWebhookController::class, 'mailgunInbound'])
    ->middleware('verify.mailgun.webhook');
    
Route::post('webhooks/mailgun/events', [MailWebhookController::class, 'mailgunEvents'])
    ->middleware('verify.mailgun.webhook');
