<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminWebhookController;
use App\Http\Controllers\FilePondController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\Mail\MailAttachmentController;
use App\Http\Controllers\Mail\MailLabelController;
use App\Http\Controllers\Mail\MailMessageController;
use App\Http\Controllers\Mail\MailThreadController;
use App\Http\Controllers\Mail\MailWebhookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SettingController;
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

    Route::get('attachments/{id}/download', [MailAttachmentController::class, 'download'])
        ->name('attachments.download');

});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('webhooks', [AdminWebhookController::class, 'index'])->name('webhooks');
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
