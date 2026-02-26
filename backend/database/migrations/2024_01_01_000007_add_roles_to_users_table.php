<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('is_active');
            $table->boolean('is_doctor')->default(false)->after('is_admin');
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->onDelete('set null')->after('is_doctor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['doctor_id']);
            $table->dropColumn(['is_admin', 'is_doctor', 'doctor_id']);
        });
    }
};
