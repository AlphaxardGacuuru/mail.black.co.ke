<?php

namespace App\Http\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class LeadService extends Service
{
    /*
     * Get All Leads
     */
    public function index($request)
    {
        $query = Lead::query();

        $query = $this->search($query, $request);

        $leads = $query
            ->orderBy('created_at', 'DESC')
            ->paginate();

        return $leads;
    }

    /*
     * Get One Lead
     */
    public function show($id)
    {
        return Lead::findOrFail($id);
    }

    /*
     * Store Lead
     */
    public function store($request)
    {
        return DB::transaction(function () use ($request) {
            $lat = $request->latitude;
            $lng = $request->longitude;
            $threshold = 0.0002; // Approx 20 meters

            // 1. Check if a lead exists within the 20m radius
            $lead = Lead::whereBetween('latitude', [$lat - $threshold, $lat + $threshold])
                ->whereBetween('longitude', [$lng - $threshold, $lng + $threshold])
                ->where("name", $request->name)
                ->first();

            // 2. If no lead exists, create one
            if (! $lead) {
                $lead = new Lead;
                $lead->name = $request->name;
                $lead->email = $request->email;
                $lead->phone = $request->phone;
                $lead->type = $request->type;
                $lead->latitude = $lat;
                $lead->longitude = $lng;
                $lead->address = $request->address;
                $lead->google_maps_link = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
                $lead->created_by = $this->id;
                $lead->save();
            }

            $lead->visits()->create([
                'user_id' => $this->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'outcome' => $request->outcome,
            ]);

            $saved = true;
            $message = 'Lead Recorded Successfully';

            return [$saved, $message, $lead];
        });
    }

    /*
     * Update Lead
     */
    public function update($request, $id)
    {
        $lead = Lead::findOrFail($id);

        $lead->update($request->all());

        return [true, 'Lead Updated Successfully', $lead];
    }

    /*
     * Delete Lead
     */
    public function destroy($id)
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return [true, 'Lead Deleted Successfully', null];
    }

    public function search($query, $request)
    {
        if ($request->filled('name')) {
            $query->where('name', 'like', "%{$request->name}%");
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', "%{$request->email}%");
        }

        if ($request->filled('phone')) {
            $query->where('phone', $request->phone);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('outcome')) {
            $query->where('outcome', $request->outcome);
        }

        return $query;
    }
}
