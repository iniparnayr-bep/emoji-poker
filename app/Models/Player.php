<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Player extends Model
{
    protected $fillable = [
        'session_token', 'user_id', 'name', 'avatar',
        'is_owner', 'estimate', 'player_token', 'is_spectator', 'last_seen_at',
    ];

    protected $casts = [
        'is_owner'     => 'boolean',
        'is_spectator' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    protected static $avatarPool = [
        '🦊','🐯','🦁','🐼','🐻','🐨','🐾','🦖',
        '🐙','🦀','🐬','🐠','🐧','🦆','🦉','🐔',
        '🦄','🐉','🐲','🐳','🐍','🐢','🦎','🦝',
        '🐝','🐞','🐛','🦋','🚀','⭐','🌈','🔥',
    ];

    public static function generateToken(): string
    {
        return Str::random(25);
    }

    public static function pickAvatar(array $usedAvatars = []): string
    {
        $available = array_diff(self::$avatarPool, $usedAvatars);
        $pool = count($available) ? array_values($available) : self::$avatarPool;
        return $pool[array_rand($pool)];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PokerSession::class, 'session_token', 'token');
    }

    public function toClientArray(bool $revealed = false): array
    {
        $estimate = $this->estimate;
        if (!$revealed && $estimate !== null) {
            $estimate = -1; // hidden until revealed
        }
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'avatar'      => $this->avatar,
            'isOwner'     => $this->is_owner,
            'isSpectator' => $this->is_spectator,
            'estimate'    => $estimate,
        ];
    }

    public function toPrivateArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'avatar'      => $this->avatar,
            'isOwner'     => $this->is_owner,
            'isSpectator' => $this->is_spectator,
            'token'       => $this->player_token,
            'estimate'    => $this->estimate,
        ];
    }
}
