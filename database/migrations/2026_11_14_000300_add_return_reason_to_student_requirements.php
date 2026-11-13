<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A document the registrar sent back to the student: why, when and by whom (shown to the student). */
    public function up(): void
    {
        Schema::table('student_requirements', function (Blueprint $table) {
            $table->string('returned_reason', 500)->nullable()->after('remarks');
            $table->timestamp('returned_at')->nullable()->after('returned_reason');
            $table->foreignId('returned_by')->nullable()->after('returned_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returned_by');
            $table->dropColumn(['returned_reason', 'returned_at']);
        });
    }
};
