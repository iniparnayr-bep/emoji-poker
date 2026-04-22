<?php

namespace App\Events;

use App\Models\PokerSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class SessionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly PokerSession $session,
        public readonly string $event = 'sessionUpdated',
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('session.' . $this->session->token)];
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    public function broadcastWith(): array
    {
        $this->session->load('players');
        return $this->session->toClientArray();
    }
}
