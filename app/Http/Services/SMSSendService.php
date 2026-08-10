<?php

namespace App\Http\Services;

use AfricasTalking\SDK\AfricasTalking;
use App\Models\SMS;
use Illuminate\Support\Facades\Log;

class SMSSendService extends Service
{
    public $africasTalking;

    public $model;

    public function __construct($model)
    {
        // Set your app credentials
        $username = env('AFRICASTKNG_USERNAME');
        $apiKey = env('AFRICASTKNG_API_KEY');
        // $username = env('AFRICASTKNG_USERNAME_SANDBOX');
        // $apiKey = env('AFRICASTKNG_API_KEY_SANDBOX');

        // Initialize the SDK
        $this->africasTalking = new AfricasTalking($username, $apiKey);

        $this->model = $model;

        $phone = $model->userUnit->user->phone;

        // $phone = substr_replace($phone, '+254', 0, -9);
        $phone = substr_replace("0700364446", '+254', 0, -9);
        // $phone = substr_replace("0721721357", '+254', 0, -9);
        // $phone = substr_replace("0721357153", '+254', 0, -9);
        // $phone = substr_replace("0718560702", '+254', 0, -9);

        $this->recipient = $phone;
    }

    // Send SMS
    public function sendSMS($type)
    {
        // Use the service
        $sms = $this->africasTalking->sms();

        // Set your message
        $message = $this->template($type, $this->model);

        // Set your shortCode or senderId
        $from = "";

        $status = "";

        try {
            // Send message
            ["status" => $status, "data" => $data] = $sms->send([
                'to' => $this->recipient,
                'message' => $message,
                'from' => $from,
            ]);

            // Insert message into data object
            $data->message = $message;

            // Save SMS
            $saved = $this->saveSMS($data);

            Log::info('SMS Sent: '.json_encode($data));

        } catch (\Throwable $exception) {
            Log::error('Sending SMS Failed: '.$exception);

            throw $exception;
        }

        return $status;
    }

    /*
     * SMS Template
     */
    public function template($type, $model)
    {
        $amount = number_format($model->balance);

        $invoiceType = collect(explode('_', $model->type))
            ->map(fn ($word) => ucfirst($word)) // Capitalize each word
            ->join(' '); // Join the words with a space

        switch ($type) {
            case "invoice":
                return "Dear ".$model->userUnit->user->name.",\n
				Your ".$invoiceType." invoice of KES ".$amount." is ready.\n
				Kindly check your email for more details.";
                break;
            case "reminder":
                return "Dear ".$model->userUnit->user->name.",\n
				Your ".$invoiceType." invoice of KES ".$amount." is still pending.\n
				Kindly check your email for more details.";
                break;
            case "receipt":
                return "Dear ".$model->userUnit->user->name.",\n
				Your ".$invoiceType." payment of KES ".$amount." has been recieved and your receipt is ready.\n
				Kindly check your email for more details.";
                break;
            default:
                return "Dear customer, this is a reminder to pay your invoice. Kindly check your email for more details.";
                break;
        }
    }

    /*
     * Save SMS
     */
    public function saveSMS($data)
    {
        $message = $data->message;
        $data = $data->SMSMessageData;
        $responseMessage = $data->Message;
        [$recipient] = $data->Recipients;

        // Save to database
        $sms = new SMS;
        $sms->user_unit_id = $this->model->userUnit->id;
        $sms->invoice_id = $this->model->id;
        $sms->response_message = $responseMessage;
        $sms->message_id = $recipient->messageId;
        $sms->number = $recipient->number;
        $sms->text = $message;
        $sms->status = $recipient->status;
        $sms->status_code = $recipient->statusCode;
        $sms->cost = $recipient->cost;

        return $sms->save();
    }
}
