<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 5);
            $table->foreign('session_token')->references('token')->on('poker_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 50);
            $table->string('avatar', 8)->default('🐧');
            $table->boolean('is_owner')->default(false);
            $table->string('estimate', 20)->nullable();
            $table->string('player_token', 25)->unique(); // private join token
            $table->boolean('is_spectator')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
