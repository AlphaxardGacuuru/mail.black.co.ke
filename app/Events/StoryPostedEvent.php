<?php

namespace App\Events;

use App\Models\Story;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoryPostedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public Story $story) {}
}
