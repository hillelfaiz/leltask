<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('semester')->nullable(); // Semester 1-8
            $table->string('schedule_day')->nullable(); // "Senin", "Selasa", dll
            $table->string('schedule_time_start')->nullable(); // "08:00"
            $table->string('schedule_time_end')->nullable(); // "10:00"
            $table->string('room')->nullable(); // "Ruang A-301"
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['semester', 'schedule_day', 'schedule_time_start', 'schedule_time_end', 'room']);
        });
    }
};
