<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_schedules', function (Blueprint $table) {
            // Drop foreign key first
            try {
                $table->dropForeign(['doctor_id']);
            } catch (\Exception $e) {
                // Foreign key may not exist, continue
            }
            
            // Drop the unique constraint to allow multiple slots per day
            $table->dropUnique(['doctor_id', 'day_of_week']);
            
            // Re-add foreign key
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_schedules', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['doctor_id']);
            
            // Restore the unique constraint
            $table->unique(['doctor_id', 'day_of_week'], 'doctor_schedules_doc_day_unique');
            
            // Re-add foreign key
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
        });
    }
};
