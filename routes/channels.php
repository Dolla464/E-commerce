<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// user private channel for their orders
Broadcast::channel('user.{userId}.orders', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId; // must be true to authorize
});

// admin private channel for order management
Broadcast::channel('admin.orders', function (User $user) {
    return $user->isAdmin(); // only admin users can listen
});