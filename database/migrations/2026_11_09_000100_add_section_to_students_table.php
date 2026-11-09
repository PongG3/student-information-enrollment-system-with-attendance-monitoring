<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Block section the student stays in for all subjects, e.g. "A" in "BSIT 1-A".
            $table->string('section', 20)->nullable()->after('year_level');
            $table->index(['program_id', 'year_level', 'section']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['program_id', 'year_level', 'section']);
            $table->dropColumn('section');
        });
    }
};
