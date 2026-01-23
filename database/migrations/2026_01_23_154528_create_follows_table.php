<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->string('follower_actor_id'); // Actor ID of the follower (can be remote)
            $table->foreignId('followed_user_id')->constrained('users'); // Local user being followed
            $table->unique(['follower_actor_id', 'followed_user_id']); // Prevent duplicate follows
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
