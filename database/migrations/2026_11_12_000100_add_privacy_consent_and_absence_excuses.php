<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the student (or a guardian) agreed to the privacy notice (Data Privacy Act, RA 10173).
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('privacy_consent_at')->nullable()->after('status');
        });

        // A student's explanation for missing a class (an excuse letter), read by the teacher on the sheet.
        Schema::create('absence_excuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_offering_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason', 500);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('status', 20)->default('pending');   // pending, accepted, declined
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'class_offering_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_excuses');
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('privacy_consent_at');
        });
    }
};
