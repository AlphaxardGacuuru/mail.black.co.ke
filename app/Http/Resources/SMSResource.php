<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SMSResource extends JsonResource
{
    /*
     * Get Network
     */
    public function getNetwork($networkCode)
    {
        // Assign network code to Telco that handled message
        switch ($networkCode) {
            case 63902:
                return "Safaricom";
                break;
            case 63903:
                return "Airtel Kenya";
                break;

            case 63907:
                return "Orange Kenya";
                break;

            case 63999:
                return "Equitel Kenya";
                break;

            default:
                return "Unknown Network";
                break;
        }
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "userName" => $this->userUnit->user->name,
            "unitName" => $this->userUnit->unit->name,
            "responseMessage" => $this->response_message,
            "messageId" => $this->message_id,
            "number" => substr_replace($this->number, '0', 0, -9),
            "text" => $this->text,
            "status" => $this->status,
            "statusCode" => $this->status_code,
            "cost" => $this->cost,
            "deliveryStatus" => $this->delivery_status,
            "network" => $this->getNetwork($this->network_code),
            "networkCode" => $this->network_code,
            "failureReason" => $this->failure_reason,
            "retryCount" => $this->retry_count,
            "message" => $this->message,
            "updatedAt" => $this->updated_at,
            "createdAt" => $this->created_at,
        ];
    }
}
