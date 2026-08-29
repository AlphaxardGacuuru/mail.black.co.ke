<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $userRoles = [
            "userId" => $this->id,
            "roles" => $this->roles,
        ];

        $userRoleNames = [
            "userId" => $this->id,
            "roleNames" => $this->getRoleNames(),
        ];

        return [
            "id" => $this->id,
            "name" => $this->name,
            "email" => $this->email,
            "mailgunConfigured" => $this->hasMailgunCredentials(),
            "mailgunAccounts" => MailgunAccountResource::collection(
                $this->mailgunAccounts()->get()
            )->resolve(),
            "phone" => $this->phone,
            "gender" => $this->gender,
            "avatar" => $this->avatar,
            "accountType" => $this->account_type,
            "emailVerifiedAt" => $this->email_verified_at,
            "settings" => $this->settings,
            "roles" => [$userRoles],
            "roleNames" => [$userRoleNames],
            "permissions" => $this->getAllPermissions()->pluck('name')->unique()->values(),
            "twoFactorEnabled" => $this->hasTwoFactorEnabled(),
            "createdAt" => $this->created_at,
        ];
    }
}
