<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row = one student asking for one class.
        // Status flow: pending -> approved -> enrolled. Exits: rejected, cancelled.
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_offering_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->string('status_reason')->nullable();   // required for rejected / cancelled
            $table->timestamps();

            // A student can never hold two rows for the same class.
            $table->unique(['student_id', 'class_offering_id']);
            $table->index(['class_offering_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
