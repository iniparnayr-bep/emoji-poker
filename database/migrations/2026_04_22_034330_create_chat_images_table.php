<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_images', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 5);
            $table->string('filename');   // {timestamp}_{sessionToken}_{random}.{ext}
            $table->string('path');       // relative storage path
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_images');
    }
};
