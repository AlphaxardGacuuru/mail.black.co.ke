<?php

namespace App\Http\Services;

use App\Http\Resources\MPESATransactionResource;
use App\Models\Integration;
use App\Models\MPESATransaction;
use App\Models\User;
use App\Models\UserSubscriptionPlan;
use Exception;
use Illuminate\Support\Facades\Log;
use Kopokopo\SDK\K2;
use Illuminate\Http\Request;

class MPESATransactionService extends Service
{
    /*
     * Kopokopo Environment Variables
     */
    public static function config()
    {
        $env = config('services.kopokopo.environment', 'live');

        if (!in_array($env, ['sandbox', 'live'], true)) {
            $env = 'live';
        }

        return config("services.kopokopo.$env", []);
    }

    /*
     * Show All Card Transactions
     */
    public function index($request)
    {
        $mpesaTransactionQuery = new MPESATransaction;

        $mpesaTransactionQuery = $this->search($mpesaTransactionQuery, $request);

        $sum = $mpesaTransactionQuery->sum("amount");

        $creditNotes = $mpesaTransactionQuery
            ->orderBy("id", "DESC")
            ->paginate(20);

        return MPESATransactionResource::collection($creditNotes)
            ->additional(["sum" => number_format($sum)]);
    }

    /*
     * Store MPESA Transaction
     */
    public function store($request)
    {
        Log::info("Kopokopo Transaction Received: " . json_encode($request->all()));

        $data = $request->data;

        // Shorten attributes
        $attributes = $data['attributes'];
        // Shorten event
        $event = $attributes['event'];
        // Shorten resource
        $resource = $event['resource'];

        $betterPhone = substr_replace($resource['sender_phone_number'], '0', 0, -9);

        $user = User::where('phone', $betterPhone)->first();

        if (!$user) {
            Log::info("User Not Found. Reference: " . $resource['reference']);

            return [true, "User Not Found", null, null];
        }

        $amount = is_numeric($resource['amount']) ? round($resource['amount'], 0) : 0;

        // 1. Check for duplicate using M-PESA reference (to prevent duplicates between STK Push and Webhooks)
        $existing = MPESATransaction::where('reference', $resource['reference'])->first();

        if ($existing) {
            Log::info("Duplicate STK Transaction Ignored. Reference: " . $resource['reference']);

            return [true, "Transaction Already Exists", $existing, $user];
        }

        $mpesaTransaction = new MPESATransaction;
        $mpesaTransaction->kopokopo_id = $data['id'];
        $mpesaTransaction->type = $data['type'];
        $mpesaTransaction->initiation_time = $attributes['initiation_time'];
        $mpesaTransaction->status = $attributes['status'];
        $mpesaTransaction->event_type = $event['type'];
        $mpesaTransaction->resource_id = $resource['id'];
        $mpesaTransaction->reference = $resource['reference'];
        $mpesaTransaction->origination_time = $resource['origination_time'];
        $mpesaTransaction->sender_phone_number = $resource['sender_phone_number'];
        $mpesaTransaction->amount = $amount;
        $mpesaTransaction->currency = $resource['currency'];
        $mpesaTransaction->till_number = $resource['till_number'];
        $mpesaTransaction->system = $resource['system'];
        $mpesaTransaction->resource_status = $resource['status'];
        $mpesaTransaction->sender_first_name = $resource['sender_first_name'];
        $mpesaTransaction->sender_middle_name = $resource['sender_middle_name'];
        $mpesaTransaction->sender_last_name = $resource['sender_last_name'];
        $mpesaTransaction->user_id = $user->id;
        $saved = $mpesaTransaction->save();

        $this->updateUserSubscriptionPlan($amount, $user->id);

        $message = "Transaction Saved Successfully";

        return [$saved, $message, $mpesaTransaction, $user];
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        $userId = $request->input("userId");

        if ($request->filled("userId")) {
            $query = $query->where("user_id", $userId);
        }

        return $query;
    }

    /**
     * Send STK Push to Kopokopo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function stkPush($request)
    {
        // Get phone in better format
        $betterPhone = substr_replace(auth('sanctum')->user()->phone, '+254', 0, -9);
        // $betterPhone = substr_replace("0700364446", '+254', 0, -9);

        // Get first and last name
        $parts = explode(" ", auth('sanctum')->user()->name);

        $lastname = array_pop($parts);

        $firstname = implode(" ", $parts);

        $K2 = new K2($this->config());

        // Get token
        $tokenResponse = $K2->TokenService()->getToken();

        $token = $tokenResponse['data']['accessToken'];

        if ($tokenResponse['status'] == 'success') {
            // echo "My access token is: " . $token . " It expires in: " . $tokenResponse['data']['expiresIn'] . "<br>";
        }

        // STKPush
        $stk = $K2->StkService();

        $response = $stk->initiateIncomingPayment([
            'paymentChannel' => 'M-PESA STK Push',
            'tillNumber' => 'K433842',
            'firstName' => $firstname,
            'lastName' => $lastname,
            'phoneNumber' => $betterPhone,
            'amount' => $request->input('amount'),
            'currency' => 'KES',
            'email' => auth('sanctum')->user()->email,
            'callbackUrl' => env('APP_URL', 'https://property.black.co.ke') . '/api/mpesa-transactions',
            'accessToken' => $token,
        ]);

        if ($response['status'] == 'success') {
            // echo "The resource location is: " . json_encode($response['location']);
            // => 'https://sandbox.kopokopo.com/api/v1/incoming_payments/247b1bd8-f5a0-4b71-a898-f62f67b8ae1c'

            return [$response["status"], "Request Sent to Your Phone", $response];
        } else {
            return [$response["status"], "Request Failed", $response];
        }
    }

    /**
     * Check STK Push Status with Kopokopo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function stkPushStatus($request)
    {
        $location = $request->input('location');

        if (!$location) {
            return ["failed", "Location URL is required to check status", null];
        }

        $K2 = new K2($this->config());

        // Get token
        $tokenResponse = $K2->TokenService()->getToken();

        $token = $tokenResponse['data']['accessToken'];

        // Use STK Service to check status
        $stk = $K2->StkService();

        $options = [
            'location' => $location,
            'accessToken' => $token,
        ];

        $response = $stk->getStatus($options);

        // $response['data']['attributes']['status'] usually contains the actual payment state: 
        // "Pending", "Success", "Failed" etc.
        if ($response['status'] == 'success') {
            return [$response['status'], "Status Checked Successfully", $response['data']];
        }

        return ["failed", "Failed to check STK push status", $response];
    }

    /*
	* Update User Subscription Plan
	*/
    public function updateUserSubscriptionPlan($amount, $id)
    {
        $userSubscriptionPlan = UserSubscriptionPlan::where("user_id", $id)
            ->where("status", "pending")
            ->first();

        if (!$userSubscriptionPlan) {
            return; // Avoid errors if plan is already active or doesn't exist
        }

        $userSubscriptionPlan->amount_paid = $amount;
        $userSubscriptionPlan->start_date = now();

        $months = $userSubscriptionPlan->billing_cycle == "monthly" ? 1 : 12;
        $userSubscriptionPlan->end_date = now()->addMonths($months);
        $userSubscriptionPlan->status = "active";
        $userSubscriptionPlan->save();
    }

    public function subscribe()
    {
        $events = [
            'buygoods_transaction_received',
            'buygoods_transaction_reversed',
            'customer_created'
        ];

        $K2 = new K2($this->config());

        $tokenResponse = $K2->TokenService()->getToken();

        $token = $tokenResponse['data']['accessToken'];

        $response = $K2->Webhooks()->subscribe([
            'eventType' => 'buygoods_transaction_received',
            'url'       => 'https://property.black.co.ke/api/kopokopo/save-webhook',
            'scope'     => 'till',
            'scopeReference' => '613289', // Your Till Number
            'accessToken' => $token,
        ]);

        Log::info("Kopokopo Subscription Response: " . json_encode($response));

        if ($response['status'] === 'success') {
            $locationParts = explode("/", $response['location']);

            $externalId = $locationParts[count($locationParts) - 1];

            $integration = Integration::updateOrCreate(
                [
                    'service' => 'kopokopo',
                    'event_type' => 'buygoods_transaction_received',
                ],
                [
                    'external_id' => $externalId,
                    'url' => $response['location'],
                    'status' => 'active',
                    'meta' => $response
                ]
            );
        }

        $message = $response['status'] === 'success' ? "Subscription Successful" : "Subscription Failed";

        return [
            $response["status"],
            $message,
            [
                "location" => $response['location'],
                "data" => $integration
            ]
        ];
    }

    public function saveWebhook($request)
    {
        Log::info("Kopokopo Webhook Received: " . json_encode($request->all()));

        $K2 = new K2($this->config());

        $webhooks = $K2->Webhooks();

        $webhookPayload = file_get_contents('php://input');

        // This will both validate and process the payload for you
        $response = $webhooks->webhookHandler($webhookPayload, $_SERVER['HTTP_X_KOPOKOPO_SIGNATURE']);

        Log::info("Kopokopo Webhook Received 2: " . json_encode($response));

        if (isset($response['status']) && $response['status'] === 'success') {

            $data = $response['data'];
            $topic = $data['topic'] ?? '';

            // The Kopokopo SDK might drop 'hashed_sender_phone' from $response['data'].
            // Retrieve it directly from Laravel's raw request JSON payload instead.
            $hashedPhoneNumber = $request->input('event.resource.hashed_sender_phone');

            if ($hashedPhoneNumber) {
                // Check if hashed phone exists
                $user = User::where('hashed_phone', $hashedPhoneNumber)->first();

                if ($user) {
                    $data["senderPhoneNumber"] = $user->phone;
                } else {
                    // lazy() loads users in chunks behind the scenes, keeping memory low
                    $user = User::whereNull('hashed_phone')->lazy()->first(function ($user) use ($hashedPhoneNumber) {
                        $phoneNumber = substr_replace($user->phone, '254', 0, -9);
                        $hashed = hash('sha256', $phoneNumber);

                        return $hashed === $hashedPhoneNumber;
                    });

                    if ($user) {
                        // Update user with hashed phone for future reference
                        $user->hashed_phone = $hashedPhoneNumber;
                        $user->save();

                        $data["senderPhoneNumber"] = $user->phone;
                    } else {
                        // Handle case where user isn't found at all
                        Log::warning('Kopokopo user not found for hash: ' . $hashedPhoneNumber);
                    }
                }
            } else {
                Log::warning('Kopokopo webhook payload missing hashed_sender_phone');
            }

            if ($topic === 'buygoods_transaction_received') {
                // Transform the webhook payload to match the STK callback payload structure
                $transformedPayload = [
                    'data' => [
                        'id' => $data['id'] ?? null,
                        'type' => $topic,
                        'attributes' => [
                            'initiation_time' => $data['createdAt'] ?? null,
                            'status' => $data['status'] ?? 'Success',
                            'event' => [
                                'type' => $data['eventType'] ?? null,
                                'resource' => [
                                    'id' => $data['resourceId'] ?? null,
                                    'amount' => $data['amount'] ?? null,
                                    'status' => $data['status'] ?? null,
                                    'system' => $data['system'] ?? null,
                                    'currency' => $data['currency'] ?? null,
                                    'reference' => $data['reference'] ?? null,
                                    'till_number' => $data['tillNumber'] ?? null,
                                    'sender_phone_number' => $data['senderPhoneNumber'] ?? null,
                                    'origination_time' => $data['originationTime'] ?? null,
                                    'sender_last_name' => $data['senderLastName'] ?? null,
                                    'sender_first_name' => $data['senderFirstName'] ?? null,
                                    'sender_middle_name' => $data['senderMiddleName'] ?? null,
                                ]
                            ]
                        ]
                    ]
                ];

                $requestInstance = (new Request)->merge($transformedPayload);

                $storeResult = $this->store($requestInstance);

                // Send Confirmation SMS via Kopokopo
                try {
                    $tokenResponse = $K2->TokenService()->getToken();

                    if ($tokenResponse['status'] === 'success') {
                        $token = $tokenResponse['data']['accessToken'];

                        $amount = $data['amount'];
                        $message = "Payment of KES {$amount} received successfully. Thank you!";

                        $smsResponse = $K2
                            ->SmsNotificationService()
                            ->sendTransactionSmsNotification([
                                'webhookEventReference' => $data['id'],
                                'message' => $message,
                                'callbackUrl' => env('APP_URL', 'https://property.black.co.ke') . '/api/mpesa-transactions',
                                'accessToken' => $token,
                            ]);

                        Log::info("Kopokopo Transaction SMS Sent: " . json_encode($smsResponse));
                    }
                } catch (Exception $e) {
                    Log::error("Failed to send Kopokopo Transaction SMS: " . $e->getMessage());
                }

                return $storeResult;
            }
        }

        return [false, "Webhook Skipped (Unknown Topic)", null];
    }
}
