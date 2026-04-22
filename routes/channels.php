<?php

use Illuminate\Support\Facades\Broadcast;

// Public channel for a poker session (all clients can join to receive events)
// We use a public channel because players authenticate via their playerToken, not Laravel auth
Broadcast::channel('session.{token}', function () {
    return true; // open to all
});
