<?php

use App\Http\Controllers\Admin\AdminAiUsageController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminWebhookController;
use App\Http\Controllers\FilePondController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\Jobs\JobApplicationController;
use App\Http\Controllers\Jobs\JobSearchController;
use App\Http\Controllers\Mail\MailAttachmentController;
use App\Http\Controllers\Mail\MailLabelController;
use App\Http\Controllers\Mail\MailMessageController;
use App\Http\Controllers\Mail\MailThreadController;
use App\Http\Controllers\Mail\MailWebhookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SettingController;
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

    Route::apiResources([
        "users" => UserController::class,
        "notifications" => NotificationController::class,
        "integrations" => IntegrationController::class,
        "support-tickets" => SupportTicketController::class,
        "settings" => SettingController::class,
        "threads" => MailThreadController::class,
        "labels" => MailLabelController::class,
    ]);

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy']);

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
    Route::post('job-search/cover-letter', [JobSearchController::class, 'uploadCoverLetter'])->middleware('throttle:10,1');
    Route::post('job-search/career-pages', [JobSearchController::class, 'storeCareerPage'])->middleware('throttle:10,1');
    Route::delete('job-search/career-pages/{id}', [JobSearchController::class, 'destroyCareerPage']);

    Route::get('job-applications', [JobApplicationController::class, 'index']);
    Route::patch('job-applications/{id}', [JobApplicationController::class, 'update']);
    Route::post('job-applications/{id}/approve', [JobApplicationController::class, 'approve']);
    Route::post('job-applications/{id}/skip', [JobApplicationController::class, 'skip']);

});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('webhooks', [AdminWebhookController::class, 'index'])->name('webhooks');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
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
    });
});

// Mailgun account avatars require an authenticated owner check.
Route::middleware('auth:sanctum')->post(
    'filepond/mailgun-accounts/{account}/avatar',
    [FilePondController::class, 'updateMailgunAccountAvatar']
);

Route::post('webhooks/mailgun', [MailWebhookController::class, 'mailgunInbound'])
    ->middleware('verify.mailgun.webhook');
    
Route::post('webhooks/mailgun/events', [MailWebhookController::class, 'mailgunEvents'])
    ->middleware('verify.mailgun.webhook');
