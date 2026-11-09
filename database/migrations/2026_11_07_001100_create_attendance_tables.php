<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One class meeting on one date.
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_offering_id')->constrained()->restrictOnDelete();
            $table->date('session_date');
            $table->foreignId('taken_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            // One attendance sheet per class and date.
            $table->unique(['class_offering_id', 'session_date']);
        });

        // One student's status in one session.
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status', 10);                  // present, absent, late, excused
            $table->string('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One record per student per session.
            $table->unique(['attendance_session_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
