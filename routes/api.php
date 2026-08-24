<?php

use App\Http\Controllers\FilePondController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\Mail\MailAttachmentController;
use App\Http\Controllers\Mail\MailLabelController;
use App\Http\Controllers\Mail\MailMessageController;
use App\Http\Controllers\Mail\MailThreadController;
use App\Http\Controllers\Mail\MailWebhookController;
use App\Http\Controllers\NotificationController;
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
    ]);

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

        // Mail
        Route::post('mail/attachments', 'storeMailAttachment');
        Route::delete('mail/attachments/{id}', 'destroyMailAttachment');
    });
});

/*
 * Mail
 */
Route::middleware('auth:sanctum')->prefix('mail')->group(function () {
    Route::get('threads', [MailThreadController::class, 'index']);
    Route::get('threads/{id}', [MailThreadController::class, 'show']);
    Route::patch('threads/{id}/star', [MailThreadController::class, 'star']);
    Route::patch('threads/{id}/unstar', [MailThreadController::class, 'unstar']);
    Route::patch('threads/{id}/archive', [MailThreadController::class, 'archive']);
    Route::patch('threads/{id}/unarchive', [MailThreadController::class, 'unarchive']);
    Route::patch('threads/{id}/trash', [MailThreadController::class, 'trash']);
    Route::patch('threads/{id}/restore', [MailThreadController::class, 'restore']);
    Route::patch('threads/{id}/read', [MailThreadController::class, 'markRead']);
    Route::patch('threads/{id}/unread', [MailThreadController::class, 'markUnread']);
    Route::post('threads/{id}/labels', [MailThreadController::class, 'attachLabel']);
    Route::delete('threads/{id}/labels/{labelId}', [MailThreadController::class, 'detachLabel']);
    Route::delete('threads/{id}', [MailThreadController::class, 'destroy']);

    Route::post('messages', [MailMessageController::class, 'store']);
    Route::post('messages/{id}/reply', [MailMessageController::class, 'reply']);
    Route::post('messages/{id}/reply-all', [MailMessageController::class, 'replyAll']);
    Route::post('messages/{id}/forward', [MailMessageController::class, 'forward']);

    Route::get('attachments/{id}/download', [MailAttachmentController::class, 'download'])
        ->name('mail.attachments.download');

    Route::get('labels', [MailLabelController::class, 'index']);
    Route::post('labels', [MailLabelController::class, 'store']);
    Route::patch('labels/{id}', [MailLabelController::class, 'update']);
    Route::delete('labels/{id}', [MailLabelController::class, 'destroy']);
});

Route::post('mail/webhooks/mailgun', [MailWebhookController::class, 'mailgunInbound'])
    ->middleware('verify.mailgun.webhook');
