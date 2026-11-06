<?php

namespace App\Http\Services;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;

class ChatLocationShareService extends Service
{
    public function start(string $conversationId): array
    {
        $conversation = ChatConversation::forUser($this->id)->with('participants')->findOrFail($conversationId);

        if ($this->myPivot($conversation)?->is_sharing_location) {
            return [false, 'You Are Already Sharing Your Location In This Conversation', null];
        }

        $conversation->participants()->updateExistingPivot($this->id, [
            'is_sharing_location' => true,
            'location_latitude' => null,
            'location_longitude' => null,
            'location_accuracy_meters' => null,
            'location_updated_at' => null,
        ]);

        return [true, 'Location Sharing Started', $conversation];
    }

    public function updatePosition(string $conversationId, array $position): array
    {
        $conversation = ChatConversation::forUser($this->id)->with('participants')->findOrFail($conversationId);

        if (! $this->myPivot($conversation)?->is_sharing_location) {
            return [false, 'No Active Location Share Found', null];
        }

        $conversation->participants()->updateExistingPivot($this->id, [
            'location_latitude' => $position['latitude'],
            'location_longitude' => $position['longitude'],
            'location_accuracy_meters' => $position['accuracyMeters'] ?? null,
            'location_updated_at' => now(),
        ]);

        return [true, 'Location Updated', $conversation];
    }

    public function stop(string $conversationId): array
    {
        $conversation = ChatConversation::forUser($this->id)->with('participants')->findOrFail($conversationId);

        if (! $this->myPivot($conversation)?->is_sharing_location) {
            return [false, 'No Active Location Share Found', null];
        }

        $conversation->participants()->updateExistingPivot($this->id, [
            'is_sharing_location' => false,
        ]);

        return [true, 'Location Sharing Stopped', $conversation];
    }

    // updateExistingPivot() always targets the pivot row keyed to $this->id,
    // so there's no risk of one participant touching another's row — this is
    // only for reading the caller's own current state before writing it.
    protected function myPivot(ChatConversation $conversation): ?ChatConversationParticipant
    {
        return $conversation->participants->firstWhere('id', $this->id)?->pivot;
    }
}
