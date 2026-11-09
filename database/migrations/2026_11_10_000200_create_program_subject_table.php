<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which programs' curriculum includes a subject (e.g. CS102 -> BSCS, BSIT).
 * A subject with no rows here is a general subject, open to every program.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['program_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_subject');
    }
};
