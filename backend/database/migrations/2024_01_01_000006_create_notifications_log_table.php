<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('type', ['sms', 'whatsapp', 'email', 'push']);
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'cancelled']);
            $table->string('recipient'); // phone number or email address
            $table->string('subject')->nullable();
            $table->text('message');
            $table->json('metadata')->nullable(); // Twilio message SID, etc.
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index(['appointment_id', 'type']);
            $table->index(['user_id', 'type']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
