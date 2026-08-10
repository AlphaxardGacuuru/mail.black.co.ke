<?php

namespace App\Http\Services;

// AfricasTalking

use App\Http\Resources\EmailResource;
use App\Models\Email;
use Carbon\Carbon;
use Illuminate\Http\Response;

class EmailService extends Service
{
    /**
     * Display a listing of the resource.
     */
    public function index($request)
    {
        $emailQuery = new Email;

        $successful = $emailQuery->where("status", "success")->count();

        $failed = $emailQuery->whereNot("status", "success")->count();

        $emailQuery = $this->search($emailQuery, $request);

        $emails = $emailQuery
            ->orderBy("id", "DESC")
            ->paginate(20);

        return EmailResource::collection($emails)
            ->additional([
                "successfull" => $successful,
                "failed" => $failed,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store($request)
    {
        $sms = new Email;
        $sms->user_unit_id = $request->userUnitId;
        $sms->invoice_id = $request->invoiceId;
        $sms->email = $request->email;
        $sms->model = $request->model;
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

        $sms = new Email;
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
     * @param  \App\Email  $sMS
     * @return Response
     */
    public function destroy(Email $sMS)
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

        $email = $request->input("email");

        if ($request->filled("email")) {
            $query = $query
                ->whereHas("userUnit.user", function ($query) use ($email) {
                    $query->where("email", "LIKE", "%".$email."%");
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
