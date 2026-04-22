<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PokerSession extends Model
{
    protected $primaryKey = 'token';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'token', 'name', 'color', 'emojis_enabled', 'rosebud',
        'is_open', 'estimation_options', 'estimation_values', 'owner_user_id',
    ];

    protected $casts = [
        'emojis_enabled' => 'boolean',
        'rosebud'        => 'boolean',
        'is_open'        => 'boolean',
        'estimation_values' => 'array',
    ];

    // Estimation presets
    const DECKS = [
        'Fibonacci'   => ['🤷‍♂️', '☕', '1', '2', '3', '5', '8', '13', '21', '34', '55', '89', '144'],
        'PowersOfTwo' => ['🤷‍♂️', '☕', '1', '2', '4', '8', '16', '32', '64', '128', '256', '512', '1024'],
        'TShirtSizes' => ['🤷‍♂️', '☕', 'XS', 'S', 'M', 'L', 'XL', 'XXL'],
        'PersonDays'  => ['🤷‍♂️', '☕', '0.5', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14'],
    ];

    /** Generate a unique 5-char uppercase token */
    public static function generateToken(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $token = '';
            for ($i = 0; $i < 5; $i++) {
                $token .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (static::where('token', $token)->exists());
        return $token;
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class, 'session_token', 'token');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'session_token', 'token');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ChatImage::class, 'session_token', 'token');
    }

    /** Export shape sent to all clients */
    public function toClientArray(): array
    {
        return [
            'token'              => $this->token,
            'name'               => $this->name,
            'open'               => $this->is_open,
            'color'              => $this->color,
            'emojisEnabled'      => $this->emojis_enabled,
            'rosebud'            => $this->rosebud,
            'estimationOptions'  => $this->estimation_options,
            'estimationValues'   => $this->estimation_values ?? self::DECKS[$this->estimation_options] ?? self::DECKS['Fibonacci'],
            'isPro'              => $this->isPro(),
            'players'            => $this->players->map(fn($p) => $p->toClientArray((bool)$this->is_open))->values()->toArray(),
        ];
    }

    public function isPro(): bool
    {
        if ($this->rosebud) return true;
        if (!$this->owner_user_id) return false;
        $owner = User::find($this->owner_user_id);
        return $owner?->is_pro ?? false;
    }
}
