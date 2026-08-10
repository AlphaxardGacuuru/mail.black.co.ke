<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if ($request->filled("idAndName")) {
            return [
                "id" => $this->id,
                "name" => $this->user->name,
                "userId" => $this->user_id,
                "propertyId" => $this->property_id,
            ];
        }

        return [
            "id" => $this->id,
            "userId" => $this->user->id,
            "name" => $this->user->name,
            "avatar" => $this->user->avatar,
            "email" => $this->user->email,
            "phone" => $this->user->phone,
            "gender" => $this->user->gender,
            "propertyId" => $this->property_id,
            "roles" => $this->roles,
            "roleNames" => $this->getRoleNames(),
            "permissions" => $this->getPermissionNames(),
            "createdAt" => $this->created_at,
        ];
    }
}
