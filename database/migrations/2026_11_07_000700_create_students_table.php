<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('student_number', 20)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 10)->nullable();
            $table->string('gender', 10);
            $table->date('birth_date');
            $table->text('address')->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('year_level')->default(1);
            $table->string('student_type', 20)->default('new');   // new, returning, transferee
            $table->string('previous_school')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('active');      // active, inactive, graduated, withdrawn
            $table->softDeletes();                                 // archived, never hard-deleted
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('program_id');
            $table->index('status');
        });

        // Yearly counter so two registrars never receive the same student number.
        Schema::create('student_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_number_sequences');
        Schema::dropIfExists('students');
    }
};
