<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
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
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'billingCycle' => $this->billing_cycle,
            'maxProperties' => $this->max_properties,
            'maxUnits' => $this->max_units,
            'maxUsers' => $this->max_users,
            'features' => $this->features,
            'createdAt' => $this->created_at,
        ];
    }
}
