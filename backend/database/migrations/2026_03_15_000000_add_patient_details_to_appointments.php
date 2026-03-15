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
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('patient_name')->nullable()->after('patient_id');
            $table->string('patient_phone')->nullable()->after('patient_name');
            $table->string('patient_email')->nullable()->after('patient_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['patient_name', 'patient_phone', 'patient_email']);
        });
    }
};