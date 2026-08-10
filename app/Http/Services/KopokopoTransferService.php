<?php

namespace App\Http\Services;

use App\Http\Resources\KopokopoTransferResource;
use App\Models\KopokopoTransfer;
use Carbon\Carbon;
use Kopokopo\SDK\K2;

class KopokopoTransferService extends Service
{
    /*
     * Show All Transfers
     */
    public function index()
    {
        $kopokopoTransfers = KopokopoTransfer::paginate(20);

        return KopokopoTransferResource::collection($kopokopoTransfers);
    }

    /*
     * Show All Transfers
     */
    public function store($request)
    {
        // Get Data
        $data = $request->input("data");
        $attributes = $data["attributes"];

        $kopokopoTransfer = new KopokopoTransfer;
        $kopokopoTransfer->user_id = $attributes["metadata"]["userId"];
        $kopokopoTransfer->kopokopo_id = $data["id"];
        $kopokopoTransfer->kopokopo_created_at = $attributes["created_at"];
        $kopokopoTransfer->amount = $attributes["amount"]["value"];
        $kopokopoTransfer->currency = $attributes["amount"]["currency"];
        $kopokopoTransfer->transfer_batches = $attributes["transfer_batches"];
        $kopokopoTransfer->metadata = $attributes["metadata"];
        $saved = $kopokopoTransfer->save();

        return [$saved, "Payment Saved", $kopokopoTransfer];
    }

    /*
     * Initiate Payment
     */
    public function initiateTransfer($request)
    {
        $amount = $request->input('amount');

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

        $pay = $K2->PayService();

        // Pay
        $response = $pay->sendPay([
            'destinationType' => $request->type,
            'destinationReference' => $request->destinationReference,
            'amount' => $amount + 100,
            'currency' => 'KES',
            'callbackUrl' => env("APP_URL").'/api/kopokopo-transfers',
            'description' => 'Transfer',
            // 'category' => 'salaries',
            'tags' => ["tag 1", "tag 2"],
            'metadata' => [
                'userId' => $this->id,
                'notes' => 'Transfer at '.Carbon::now(),
            ],
            'accessToken' => $data['accessToken'],
        ]);

        if ($response["status"] == "success") {
            return [$response["status"], "Transfer Initiated", $response];
        } else {
            return ["Error", "Kopokopo Failed", $response];
        }
    }
}
