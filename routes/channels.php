<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('mpesa-transaction-created.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('dashboard-narration.{userId}.{streamId}', function ($user, $userId, $streamId) {
    return (int) $user->id === (int) $userId;
});
