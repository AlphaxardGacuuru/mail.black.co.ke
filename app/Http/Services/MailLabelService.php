<?php

namespace App\Http\Services;

use App\Models\MailLabel;
use Illuminate\Http\Request;

class MailLabelService extends Service
{
    public function index()
    {
        $labels = MailLabel::where('user_id', $this->id)->orderBy('name')->get();

        return [true, $labels->count() . ' Labels Retrieved', $labels];
    }

    public function store(Request $request)
    {
        $label = new MailLabel;
        $label->user_id = $this->id;
        $label->name = $request->input('name');
        $label->color = $request->input('color');
        $saved = $label->save();

        return [$saved, 'Label Created', $label];
    }

    public function update(Request $request, string $id)
    {
        $label = MailLabel::where('user_id', $this->id)->findOrFail($id);

        $label->fill([
            'name' => $request->input('name', $label->name),
            'color' => $request->input('color', $label->color),
        ])->save();

        return [true, 'Label Updated', $label];
    }

    public function destroy(string $id)
    {
        $label = MailLabel::where('user_id', $this->id)->findOrFail($id);
        $deleted = $label->delete();

        return [$deleted, 'Label Deleted', null];
    }
}
