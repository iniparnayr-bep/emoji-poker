<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatImage extends Model
{
    protected $fillable = ['session_token', 'filename', 'path', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
