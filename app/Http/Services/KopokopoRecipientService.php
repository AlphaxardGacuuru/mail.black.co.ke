<?php

namespace App\Http\Services;

use App\Http\Resources\KopokopoRecipientResource;
use App\Models\KopokopoRecipient;
use Kopokopo\SDK\K2;

class KopokopoRecipientService extends Service
{
    /*
     * Get All Kopokopo Recipients
     */
    public function index()
    {
        $kopokopoRecipients = KopokopoRecipient::all();

        return KopokopoRecipientResource::collection($kopokopoRecipients);
    }

    /*
     * Get Recipient by ID
     */
    public function show($id)
    {
        $kopokopoRecipient = KopokopoRecipient::where("user_id", $id)
            ->get();

        return KopokopoRecipientResource::collection($kopokopoRecipient);
    }

    /*
     * Create And Store Kopokopo Recipient
     */
    public function store($request)
    {
        $options = MPESATransactionService::options();

        $K2 = new K2($options);

        // Get one of the services
        $tokens = $K2->TokenService();

        // Use the service
        $result = $tokens->getToken();

        if ($result['status'] == 'success') {
            $data = $result['data'];
            // echo "My access token is: " . $data['accessToken'] . " It expires in: " . $data['expiresIn'] . "<br>";
        }

        // Add receipient
        $pay = $K2->PayService();
        // Get necessary details for creating a recipient
        $details = $this->recipientDetails($request, $data['accessToken']);

        $response = $pay->addPayRecipient($details);
        // dd($response);

        if ($response['status'] == 'success') {
            // Save destination reference
            $kopokopoRecipient = new KopokopoRecipient;
            $kopokopoRecipient->user_id = auth("sanctum")->user()->id;
            $kopokopoRecipient->destination_reference = "";
            $kopokopoRecipient->type = $request->type;
            $kopokopoRecipient->first_name = $request->firstName;
            $kopokopoRecipient->last_name = $request->lastName;
            $kopokopoRecipient->email = $request->email;
            $kopokopoRecipient->phone_number = $request->phoneNumber;
            $kopokopoRecipient->account_name = $request->accountName;
            $kopokopoRecipient->account_number = $request->accountNumber;
            $kopokopoRecipient->till_name = $request->tillName;
            $kopokopoRecipient->till_number = $request->tillNumber;
            $kopokopoRecipient->paybill_name = $request->paybillName;
            $kopokopoRecipient->paybill_number = $request->paybillNumber;
            $kopokopoRecipient->paybill_account_number = $request->paybillAccountNumber;
            $kopokopoRecipient->description = $request->description;
            $saved = $kopokopoRecipient->save();

            $message = "Recipient Wallet Created";

            $data = [
                "kopokopoRecipient" => $kopokopoRecipient,
                "kopokopo" => $response,
            ];

            return [$saved, $message, $data];
        } else {
            return [
                $response["status"],
                $response["data"]["errorMessage"],
                $response,
            ];
        }
    }

    /*
     * Get relevant details for pay recipient
     */
    public function recipientDetails($request, $accessToken)
    {
        switch ($request->type) {
            case "mobile_wallet":
                return $this->mobileWalletDetails($request, $accessToken);
                break;

            case "bank_account":
                return $this->backAccountDetails($request, $accessToken);
                break;

            case "till":
                return $this->tillDetails($request, $accessToken);
                break;

            default:
                return $this->payBillDetails($request, $accessToken);
                break;
        }
    }

    /*
     * Mobile Wallet Details
     */
    public function mobileWalletDetails($request, $accessToken)
    {
        // Get phone in better format
        $betterPhone = substr_replace($request->phoneNumber, '+254', 0, -9);

        return [
            'type' => 'mobile_wallet',
            'firstName' => $request->firstName,
            'lastName' => $request->lastName,
            'email' => $request->email,
            'phoneNumber' => $betterPhone,
            'network' => 'Safaricom',
            'accessToken' => $accessToken,
        ];
    }

    /*
     * Bank Account Details
     */
    public function backAccountDetails($request, $accessToken)
    {
        return [
            'type' => 'bank_account',
            'accountName' => $request->accountName,
            'accountNumber' => $request->accountName,
            'bankBranchRef' => $request->bankBranchRef,
            'settlementMethod' => 'RTS',
            'accessToken' => $accessToken,
        ];
    }

    /*
     * Till Details
     */
    public function tillDetails($request, $accessToken)
    {
        return [
            'type' => 'till',
            'tillName' => $request->tillName,
            'tillNumber' => $request->tillNumber,
            'accessToken' => $accessToken,
        ];
    }

    /*
     * Paybill Details
     */
    public function payBillDetails($request, $accessToken)
    {
        return [
            'type' => 'paybill',
            'paybillName' => $request->paybillName,
            'paybillNumber' => $request->paybillNumber,
            'paybillAccountNumber' => $request->paybillAccountNumber,
            'accessToken' => $accessToken,
        ];
    }
}
