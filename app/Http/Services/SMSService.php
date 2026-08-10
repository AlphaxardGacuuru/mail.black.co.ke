<?php

namespace App\Http\Services;

// AfricasTalking

use App\Http\Resources\SMSResource;
use App\Models\SMS;
use Carbon\Carbon;
use Illuminate\Http\Response;

class SMSService extends Service
{
    /**
     * Display a listing of the resource.
     */
    public function index($request)
    {
        $smsMessageQuery = new SMS;

        $successful = $smsMessageQuery->where("status", "success")->count();

        $failed = $smsMessageQuery->whereNot("status", "success")->count();

        $smsMessageQuery = $this->search($smsMessageQuery, $request);

        $smsMessages = $smsMessageQuery
            ->orderBy("id", "DESC")
            ->paginate(20);

        return SMSResource::collection($smsMessages)
            ->additional([
                "successfull" => $successful,
                "failed" => $failed,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $sms = SMS::where('message_id', $request->id)->first();
        $sms->delivery_status = $request->input('status');
        $sms->network_code = $request->input('networkCode');
        $sms->failure_reason = $request->input('failureReason');
        $sms->retry_count = $request->input('retryCount');
        $sms->save();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Store the received json in $callback
        $callback = file_get_contents('input:://input');
        // Decode the received json and store into $callbackurl
        $callbackUrl = json_decode($callback, true);

        // $callbackResponse = array(
        //     'id' => $callback['id'],
        //     'status' => $callback['status'],
        //     'phoneNumber' => $callback['phoneNumber'],
        //     'networkCode' => $callback['networkCode'],
        //     'failureReason' => $callback['failureReason'],
        //     'retryCount' => $callback['retryCount'],
        // );

        $sms = new SMS;
        $sms->message_id = $callback['id'];
        $sms->status = $callback['status'];
        $sms->number = $callback['phoneNumber'];
        // $sms->network_code = $request->networkCode;
        // $sms->failure_reason = $request->failureReason;
        // $sms->retry_count = $request->retryCount;
        $sms->save();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\SMS  $sMS
     * @return Response
     */
    public function destroy(SMS $sMS)
    {
        //
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        $propertyIds = explode(",", $request->propertyId);

        $isSuper = in_array("All", $propertyIds);

        if (! $isSuper) {
            $query = $query->whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            });
        }

        $tenant = $request->input("tenant");

        if ($request->filled("tenant")) {
            $query = $query
                ->whereHas("userUnit.user", function ($query) use ($tenant) {
                    $query->where("name", "LIKE", "%".$tenant."%");
                });
        }

        $unit = $request->input("unit");

        if ($request->filled("unit")) {
            $query = $query
                ->whereHas("userUnit.unit", function ($query) use ($unit) {
                    $query->where("name", "LIKE", "%".$unit."%");
                });
        }

        $phone = $request->input("phone");

        if ($request->filled("phone")) {
            $query = $query
                ->whereHas("userUnit.user", function ($query) use ($phone) {
                    $query->where("phone", "LIKE", "%".$phone."%");
                });
        }

        $startMonth = $request->filled("startMonth") ? $request->input("startMonth") : Carbon::now()->month;
        $endMonth = $request->filled("endMonth") ? $request->input("endMonth") : Carbon::now()->month;
        $startYear = $request->filled("startYear") ? $request->input("startYear") : Carbon::now()->year;
        $endYear = $request->filled("endYear") ? $request->input("endYear") : Carbon::now()->year;

        $start = Carbon::createFromDate($startYear, $startMonth, 1)
            ->startOfMonth()
            ->toDateTimeString(); // Output: 2024-01-01 00:00:00 (or current year)

        $end = Carbon::createFromDate($endYear, $endMonth, 1)
            ->endOfMonth()
            ->toDateTimeString(); // Output: 2024-01-01 00:00:00 (or current year)

        if ($request->filled("startMonth") || $request->filled("startYear")) {
            $query = $query->whereDate("paid_on", ">=", $start);
        }

        if ($request->filled("endMonth") || $request->filled("endYear")) {
            $query = $query->whereDate("paid_on", "<=", $end);
        }

        return $query;
    }
}
