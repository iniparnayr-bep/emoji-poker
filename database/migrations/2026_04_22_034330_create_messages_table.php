<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 5);
            $table->foreign('session_token')->references('token')->on('poker_sessions')->cascadeOnDelete();
            $table->string('player_name', 50)->default('Server');
            $table->string('player_avatar', 8)->nullable();
            $table->text('message');
            $table->enum('type', ['std', 'ai', 'server'])->default('std');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
