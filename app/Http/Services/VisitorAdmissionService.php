<?php

namespace App\Http\Services;

use App\Models\VisitorAdmission;

class VisitorAdmissionService extends Service
{
    public function index($request)
    {
        $visitorAdmissionQuery = new VisitorAdmission;

        $visitorAdmissionQuery = $this->search($visitorAdmissionQuery, $request);

        $visitorAdmissions = $visitorAdmissionQuery
            ->orderby("id", "DESC")
            ->paginate();

        return $visitorAdmissions;
    }

    public function show($id)
    {
        $visitorAdmission = VisitorAdmission::findOrFail($id);

        return $visitorAdmission;
    }

    public function store($request)
    {
        $visitorAdmission = new VisitorAdmission;
        $visitorAdmission->user_unit_id = $request->tenantId;
        $visitorAdmission->first_name = $request->firstName;
        $visitorAdmission->middle_name = $request->middleName;
        $visitorAdmission->last_name = $request->lastName;
        $visitorAdmission->national_id = $request->nationalID;
        $visitorAdmission->email = $request->email;
        $visitorAdmission->phone = $request->phone;
        $visitorAdmission->status = "pending";
        $visitorAdmission->created_by = $this->id;

        $saved = $visitorAdmission->save();

        return [$saved, "Visitor Admission Request Sent Successfully", $visitorAdmission];
    }

    public function update($request, $id)
    {
        $visitorAdmission = VisitorAdmission::find($id);
        $visitorAdmission->user_unit_id = $request->input("userUnitId", $visitorAdmission->user_unit_id);
        $visitorAdmission->first_name = $request->input("firstName", $visitorAdmission->first_name);
        $visitorAdmission->middle_name = $request->input("middleName", $visitorAdmission->middle_name);
        $visitorAdmission->last_name = $request->input("lastName", $visitorAdmission->last_name);
        $visitorAdmission->national_id = $request->input("nationalID", $visitorAdmission->national_id);
        $visitorAdmission->email = $request->input("email", $visitorAdmission->email);
        $visitorAdmission->phone = $request->input("phone", $visitorAdmission->phone);
        $visitorAdmission->status = "pending";
        $visitorAdmission->created_by = $this->id;

        $saved = $visitorAdmission->save();

        return [$saved, "Visitor Admission Request Sent Successfully", $visitorAdmission];
    }

    public function destroy($id)
    {
        $visitorAdmission = VisitorAdmission::findOrFail($id);

        $deleted = $visitorAdmission->delete();

        return [$deleted, "Visitor Admission Request Deleted Successfully", $visitorAdmission];
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        if ($request->filled("propertyId")) {
            $propertyId = explode(",", $request->propertyId);

            $query = $query->whereHas("unit.property", function ($query) use ($propertyId) {
                $query->whereIn("id", $propertyId);
            });
        }

        $unitId = $request->input("unitId");

        if ($request->filled("unitId")) {
            $query = $query->where("unit_id", $unitId);
        }

        $userUnitId = $request->input("userUnitId");

        if ($request->filled("userUnitId")) {
            $query = $query->where("id", $userUnitId);
        }

        $name = $request->input("name");

        if ($request->filled("name")) {
            $query = $query
                ->whereHas("user", function ($query) use ($name) {
                    $query->where("name", "LIKE", "%".$name."%");
                });
        }

        $phone = $request->input("phone");

        if ($request->filled("phone")) {
            $query = $query
                ->whereHas("user", function ($query) use ($phone) {
                    $query->where("phone", "LIKE", "%".$phone."%");
                });
        }

        $gender = $request->input("gender");

        if ($request->filled("gender")) {
            $query = $query
                ->whereHas("user", function ($query) use ($gender) {
                    $query->where("gender", "LIKE", "%".$gender."%");
                });
        }

        if ($request->filled("vacated")) {
            $query = $query->whereNotNull("vacated_at");
        }

        if ($request->filled("occupied")) {
            $query = $query->whereNull("vacated_at");
        }

        return $query;
    }
}
