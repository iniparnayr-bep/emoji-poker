<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $sessionToken,
        public readonly array  $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('session.' . $this->sessionToken)];
    }

    public function broadcastAs(): string { return 'chatMessage'; }

    public function broadcastWith(): array { return $this->message; }
}
