<?php

namespace App\Http\Controllers;

use App\Agents\DashboardNarrationAgent;
use App\Http\Services\DashboardService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $service)
    {
        //
    }

    /*
     * Get Dashboard Data
     */
    public function index(string $propertyIds): Response
    {
        $propertyIds = explode(",", $propertyIds);

        $data = $this->service->index($propertyIds);

        return response(["data" => $data], 200);
    }

    /*
     * Get Property Data by ID
     */
    public function properties(string $propertyIds): Response
    {
        $propertyIds = explode(",", $propertyIds);

        $data = $this->service->properties($propertyIds);

        return response(["data" => $data], 200);
    }

    /*
     * Get CRM Data
     */
    public function crm(): Response
    {
        $data = $this->service->crm();

        return response(["data" => $data], 200);
    }

    /*
     * Get AI narration for the dashboard
     */
    public function narration(string $propertyIds, Request $request): Response
    {
        $propertyIds = explode(",", $propertyIds);

        $metrics = $this->service->index($propertyIds);

        $prompt = $this->service->narration($metrics);

        $streamId = (string) $request->query('streamId', (string) Str::uuid7());
        
        $channelName = "dashboard-narration.{$request->user()->id}.{$streamId}";

        (new DashboardNarrationAgent)->broadcastNow($prompt, new PrivateChannel($channelName));

        return response([
            'data' => [
                'streamId' => $streamId,
                'channel' => $channelName,
            ],
        ], 200);
    }
}

