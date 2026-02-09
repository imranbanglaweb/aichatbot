<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('guest_id')->nullable(); // For anonymous users
            $table->string('language', 10)->default('en');
            $table->enum('status', ['active', 'completed', 'abandoned'])->default('active');
            $table->json('extracted_data')->nullable(); // Store extracted entities
            $table->string('current_intent')->nullable();
            $table->integer('message_count')->default(0);
            $table->text('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            
            $table->index(['session_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
