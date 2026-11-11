<?php

namespace App\Http\Services;

use App\Models\User;

class Service
{
    public ?string $id;

    public function __construct()
    {
        // Current User ID
        $auth = auth('sanctum')->user();

        $this->id = $auth ? $auth->id : null;
    }

    /**
     * Act for $user instead of whoever sent the request, for callers that
     * have no Sanctum token, like the signed buttons on push notifications.
     */
    public function actingFor(User $user): static
    {
        $this->id = $user->id;

        return $this;
    }
}
