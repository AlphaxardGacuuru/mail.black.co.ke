<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSubscriptionPlanResource extends JsonResource
{
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
            "userId" => $this->user_id,
            "userName" => $this->user->name,
            "subscriptionPlanId" => $this->subscription_plan_id,
            "subscriptionPlanName" => $this->subscriptionPlan->name,
            "amountPaid" => $this->amount_paid,
            "amountPaid" => $this->amount_paid,
            "startDate" => $this->start_date->format("d M Y h:i A"),
            "startDateFormated" => $this->start_date->format("Y-m-d"),
            "endDate" => $this->end_date->format("d M Y h:i A"),
            "endDateFormated" => $this->end_date->format("Y-m-d"),
            "type" => $this->type,
            "status" => $this->status,
            "billingCycle" => $this->billing_cycle,
            "createdAt" => $this->created_at,
            "updatedAt" => $this->updated_at,
        ];
    }
}
