<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'session_token', 'player_name', 'player_avatar', 'message', 'type',
    ];

    public function toClientArray(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->player_name,
            'avatar'       => $this->player_avatar,
            'message'      => $this->message,
            'type'         => $this->type,
            'timestamp'    => $this->created_at->timestamp * 1000,
        ];
    }
}
