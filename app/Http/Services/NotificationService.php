<?php

namespace App\Http\Services;

use App\Http\Resources\NotificationResource;
use App\Notifications;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationService extends Service
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        // Check if user is logged in
        $notifications = auth('sanctum')->user()->notifications;

        return NotificationResource::collection($notifications);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  Notifications  $notifications
     * @return Response
     */
    public function update($request, $id)
    {
        return auth('sanctum')->user()->notifications->markAsRead();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Notifications  $notifications
     * @return Response
     */
    public function destroy($id)
    {
        if ($id === "0") {
            auth('sanctum')->user()->notifications()->delete();
        } else {
            auth("sanctum")->user()->notifications()->findOrFail($id)->delete();
        }
    }
}
