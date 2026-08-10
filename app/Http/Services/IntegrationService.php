<?php

namespace App\Http\Services;

use App\Models\Integration;

class IntegrationService extends Service
{
    /*
     * Get All Integrations
     */
    public function index($request)
    {
        $query = new Integration;

        if ($request->filled("service")) {
            $query = $query->where("service", $request->service);
        }

        $integrations = $query
            ->orderBy("id", "DESC")
            ->paginate();

        return [true, $integrations->total()." Integrations Retrieved Successfully", $integrations];
    }
}
