<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CardTransactionController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeductionController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\FilePondController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KopokopoRecipientController;
use App\Http\Controllers\KopokopoTransferController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanRepaymentController;
use App\Http\Controllers\MPESATransactionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SMSController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskStageController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSubscriptionPlanController;
use App\Http\Controllers\VisitorAdmissionController;
use App\Http\Controllers\WaterReadingController;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
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

// Public registration endpoint
Route::post('/register', [UserController::class, 'register']);

// Public password reset endpoint
Route::post('/reset-password', [UserController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('auth', [UserController::class, 'auth']);

    Route::delete('properties', [PropertyController::class, 'destroyMany']);

    Route::apiResources([
        "properties" => PropertyController::class,
        "units" => UnitController::class,
        "tenants" => TenantController::class,
        "invoices" => InvoiceController::class,
        "water-readings" => WaterReadingController::class,
        "card-transactions" => CardTransactionController::class,
        "payments" => PaymentController::class,
        "credit-notes" => CreditNoteController::class,
        "deductions" => DeductionController::class,
        "kopokopo-recipients" => KopokopoRecipientController::class,
        "kopokopo-transfers" => KopokopoTransferController::class,
        "users" => UserController::class,
        "owners" => OwnerController::class,
        "staff" => StaffController::class,
        "roles" => RoleController::class,
        "permissions" => PermissionController::class,
        "visitor-admissions" => VisitorAdmissionController::class,
        "notifications" => NotificationController::class,
        "integrations" => IntegrationController::class,
        "user-subscription-plans" => UserSubscriptionPlanController::class,
        "referrals" => ReferralController::class,
        "announcements" => AnnouncementController::class,
        "contracts" => ContractController::class,
        "leads" => LeadController::class,
        "loans" => LoanController::class,
        "loan-repayments" => LoanRepaymentController::class,
        "stages" => StageController::class,
        "tasks" => TaskController::class,
        "task-comments" => TaskCommentController::class,
        "task-stages" => TaskStageController::class,
        "support-tickets" => SupportTicketController::class,
        "settings" => SettingController::class,
    ]);

    Route::apiResource("subscription-plans", SubscriptionPlanController::class)->except(['index']);

    /*
    * Dashboard
    */
    Route::get("dashboard/{id}", [DashboardController::class, "index"]);
    Route::get("dashboard/properties/{id}", [DashboardController::class, "properties"]);
    Route::get("dashboard/narration/{id}", [DashboardController::class, "narration"]);
    Route::get("crm/dashboard", [DashboardController::class, "crm"]);

    /*
    * Invoices
    */
    Route::get('/invoices/{id}/preview', [InvoiceController::class, 'previewPdf']);
    Route::post("invoices/{id}/send-email", [InvoiceController::class, "sendEmail"]);
    Route::post("invoices/{id}/send-sms", [InvoiceController::class, "sendSMS"]);

    /*
    * Payments
    */
    Route::get('/payments/{id}/preview', [PaymentController::class, 'previewPdf']);
    Route::post("payments/{id}/send-email", [PaymentController::class, "sendEmail"]);

    /*
    * Contracts
    */
    Route::get('/contracts/{id}/preview', [ContractController::class, 'previewPdf']);
    Route::post("contracts/{id}/send-email", [ContractController::class, "sendEmail"]);

    /*
    * Statements
    */
    Route::get('statements/unit', [StatementController::class, 'unit']);
    Route::get('statements/subscription', [StatementController::class, 'subscription']);
    Route::get('statements/loan/{id}', [StatementController::class, 'loan']);

    /*
    * Loans
    */
    Route::get('/loans/{userId}/limit', [LoanController::class, 'getLimit']);

    // Kopokopo STK Push
    Route::post("stk-push", [MPESATransactionController::class, 'stkPush']);
    Route::post("stk-push-status", [MPESATransactionController::class, 'stkPushStatus']);
    Route::post("kopokopo-initiate-transfer", [KopokopoTransferController::class, 'initiateTransfer']);

});

Route::group(["prefix" => "kopokopo"], function () {
    Route::post("subscribe", [MPESATransactionController::class, 'subscribe']);
    Route::post("save-webhook", [MPESATransactionController::class, 'saveWebhook']);
});

// Set outside auth middleware to all index page to fetch
Route::apiResource("subscription-plans", SubscriptionPlanController::class)->only(['index']);

Route::apiResources([
    "mpesa-transactions" => MPESATransactionController::class,
    "emails" => EmailController::class,
    "smses" => SMSController::class,
]);

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
    });
});

Route::get('/mailable/{id}', function ($id) {
    $invoice = Invoice::query()->find($id);

    return new InvoiceMail($invoice);
});
