<?php

namespace App\Http\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

class SettingService extends Service
{
    public function index($request)
    {
        $query = Setting::query();

        $query = $this->search($query, $request);

        $settings = $query
            ->orderby("id", "DESC")
            ->get();

        return [true, "Settings Retrieved Successfully.", $settings];
    }

    public function store($request)
    {
        $setting = Setting::updateOrCreate(
            ['key' => $request->input('key')],
            ['value' => $request->input('value')]
        );

        $saved = $setting->exists;

        $keyName = Str::of($setting->key)
            ->replace('_', ' ')
            ->explode(' ')
            ->filter()
            ->map(fn ($word) => Str::ucfirst($word))
            ->join(' ');

        $message = $keyName." Saved Successfully.";

        return [$saved, $setting, $message];
    }

    public function search($query, $request)
    {
        if ($request->filled("key")) {
            $query->where("key", $request->input("key"));
        }

        return $query;
    }
}
