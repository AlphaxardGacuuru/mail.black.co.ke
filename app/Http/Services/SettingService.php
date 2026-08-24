<?php

namespace App\Http\Services;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingService extends Service
{
	public function index(Request $request): array
	{
		$query = $this->search(Setting::query(), $request);

		$settings = $query
			->orderBy('id', 'DESC')
			->get();

		return [true, 'Settings Retrieved Successfully.', $settings];
	}

	public function store(Request $request): array
	{
		$setting = Setting::query()->updateOrCreate(
			['key' => $request->input('key')],
			['value' => $request->input('value')]
		);

		$saved = $setting->exists;

		$keyName = Str::of($setting->key)
			->replace('_', ' ')
			->explode(' ')
			->filter()
			->map(fn(string $word) => Str::ucfirst($word))
			->join(' ');

		return [$saved, $setting, $keyName . ' Saved Successfully.'];
	}

	protected function search($query, Request $request)
	{
		if ($request->filled('key')) {
			$query->where('key', $request->input('key'));
		}

		return $query;
	}
}
