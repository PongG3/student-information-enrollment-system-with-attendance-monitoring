<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One subject taught in one term by one teacher (a "class").
        Schema::create('class_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('section', 20)->default('A');
            $table->string('class_code', 20);
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->string('status', 20)->default('open'); // open, closed, cancelled
            $table->timestamps();

            $table->unique(['term_id', 'class_code']);
            $table->unique(['term_id', 'subject_id', 'section']);
            $table->index('teacher_id');
        });

        // Weekly meeting times. Used to detect schedule conflicts.
        Schema::create('class_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_offering_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Monday ... 7 = Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 50)->nullable();
            $table->timestamps();

            $table->index(['class_offering_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_meetings');
        Schema::dropIfExists('class_offerings');
    }
};
