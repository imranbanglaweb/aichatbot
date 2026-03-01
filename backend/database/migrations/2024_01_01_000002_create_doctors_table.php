<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('external_id')->nullable();
            $table->index('external_id');
            $table->foreignId('specialization_id')->constrained()->onDelete('cascade');
            $table->string('license_number')->unique();
            $table->string('qualification')->nullable();
            $table->integer('experience_years')->default(0);
            $table->text('bio')->nullable();
            $table->decimal('consultation_fee', 10, 2)->default(0);
            $table->string('hospital_clinic')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->json('languages')->nullable(); // e.g., ["en", "bn", "hi"]
            $table->json('available_days')->nullable(); // e.g., ["monday", "tuesday"]
            $table->time('start_time')->default('09:00:00');
            $table->time('end_time')->default('17:00:00');
            $table->integer('slot_duration')->default(30); // in minutes
            $table->boolean('is_available')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
