<?php

namespace App\Http\Services;

use App\Models\Announcement;
use App\Models\AnnouncementUserUnit;
use Exception;
use Illuminate\Support\Facades\DB;

class AnnouncementService extends Service
{
    public function index($request)
    {
        $query = new Announcement;

        $query = $this->search($query, $request);

        $query = $query
            ->orderBy("id", "DESC")
            ->paginate(20);

        return $query;
    }

    public function store($request)
    {
        $announcement = new Announcement;
        $announcement->message = $request->input("message");
        $announcement->channels = $request->input("channels");
        $announcement->created_by = $this->id;

        try {
            $saved = $announcement->save();

            DB::beginTransaction();

            foreach ($request->userUnitIds as $userUnitId) {
                $announcementUserUnit = new AnnouncementUserUnit;
                $announcementUserUnit->announcement_id = $announcement->id;
                $announcementUserUnit->user_unit_id = $userUnitId;
                $announcementUserUnit->save();
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return [false, "Failed to Create Announcement: ".$e->getMessage(), null];
        }

        return [$saved, "Announcement Created Successfully", $announcement];
    }

    /*
     * Search
     */
    public function search($query, $request)
    {
        $name = $request->input("name");

        if ($request->filled("name")) {
            $query = $query->where("name", "LIKE", "%".$name."%");
        }

        return $query;
    }
}
