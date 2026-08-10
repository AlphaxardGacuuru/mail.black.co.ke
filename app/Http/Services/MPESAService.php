<?php

namespace App\Http\Services;

use App\Models\MPESA;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kopokopo\SDK\K2;

class MPESAService extends Service
{
    /*
     *
     * Display a listing of the resource.
     *
     */
    public function index()
    {
        //
    }

    /**
     * Send STK Push to MPESA.
     */
    public function stkPush($request)
    {
        // Get phone in better format
        $betterPhone = substr_replace(auth('sanctum')->user()->phone, '+254', 0, -9);

        // Get first and last name
        $parts = explode(" ", auth('sanctum')->user()->name);

        $lastname = array_pop($parts);

        $firstname = implode(" ", $parts);

        // Do not hard code these values
        $options = [
            'clientId' => env('KOPOKOPO_CLIENT_ID_SANDBOX'),
            // 'clientId' => env('KOPOKOPO_CLIENT_ID'),
            'clientSecret' => env('KOPOKOPO_CLIENT_SECRET_SANDBOX'),
            // 'clientSecret' => env('KOPOKOPO_CLIENT_SECRET'),
            'apiKey' => env('KOPOKOPO_API_KEY_SANDBOX'),
            // 'apiKey' => env('KOPOKOPO_API_KEY'),
            'baseUrl' => env('KOPOKOPO_BASE_URL_SANDBOX'),
            // 'baseUrl' => env('KOPOKOPO_BASE_URL'),
        ];

        $K2 = new K2($options);

        // Get one of the services
        $tokens = $K2->TokenService();

        // Use the service
        $result = $tokens->getToken();

        if ($result['status'] == 'success') {
            $data = $result['data'];
            // echo "My access token is: " . $data['accessToken'] . " It expires in: " . $data['expiresIn'] . "<br>";
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
            'callbackUrl' => 'https://music.black.co.ke/api/kopokopo',
            'accessToken' => $data['accessToken'],
        ]);

        if ($response['status'] == 'success') {
            $data = $result['data'];

            // echo "The resource location is: " . json_encode($response['location']);
            // => 'https://sandbox.kopokopo.com/api/v1/incoming_payments/247b1bd8-f5a0-4b71-a898-f62f67b8ae1c'
            return response(["message" => "Request sent to your phone"], 200);
        } else {
            return response(["message" => $response], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store($request)
    {
        // Shorten attributes
        $attributes = $request->data['attributes'];
        // Shorten event
        $event = $attributes['event'];
        // Shorten resource
        $resource = $event['resource'];

        // Get username
        $betterPhone = substr_replace($resource['sender_phone_number'], '0', 0, -9);

        $user = User::where('phone', $betterPhone)->first();

        $mpesa = new MPESA;
        $mpesa->mpesa_id = $request->data['id'];
        $mpesa->type = $request->data['type'];
        $mpesa->initiation_time = $attributes['initiation_time'];
        $mpesa->status = $attributes['status'];
        $mpesa->event_type = $event['type'];
        $mpesa->resource_id = $resource['id'];
        $mpesa->reference = $resource['reference'];
        $mpesa->origination_time = $resource['origination_time'];
        $mpesa->sender_phone_number = $resource['sender_phone_number'];
        $mpesa->amount = round($resource['amount'], 0);
        $mpesa->currency = $resource['currency'];
        $mpesa->till_number = $resource['till_number'];
        $mpesa->system = $resource['system'];
        $mpesa->resource_status = $resource['status'];
        $mpesa->sender_first_name = $resource['sender_first_name'];
        $mpesa->sender_middle_name = $resource['sender_middle_name'];
        $mpesa->sender_last_name = $resource['sender_last_name'];
        $mpesa->user_id = $user->id;
        $saved = $mpesa->save();

        return [$saved, $mpesa, $user];
    }
}
