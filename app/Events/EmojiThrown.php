<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class EmojiThrown implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $sessionToken,
        public readonly string $targetPlayerId,
        public readonly string $emoji,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('session.' . $this->sessionToken)];
    }

    public function broadcastAs(): string { return 'emojiThrown'; }

    public function broadcastWith(): array
    {
        return ['id' => $this->targetPlayerId, 'emoji' => $this->emoji];
    }
}
