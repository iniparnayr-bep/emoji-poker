<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poker_sessions', function (Blueprint $table) {
            $table->string('token', 5)->primary();   // uppercase 5-char token
            $table->string('name', 60);
            $table->string('color', 20)->default('default');
            $table->boolean('emojis_enabled')->default(true);
            $table->boolean('rosebud')->default(false);
            $table->boolean('is_open')->default(false);  // revealed
            $table->string('estimation_options', 20)->default('Fibonacci');
            $table->json('estimation_values')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poker_sessions');
    }
};
