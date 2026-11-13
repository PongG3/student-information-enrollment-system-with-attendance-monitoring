<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A scan of the document the student uploaded, for the registrar to check before verifying. */
    public function up(): void
    {
        Schema::table('student_requirements', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('remarks');
            $table->string('file_name')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('student_requirements', fn (Blueprint $table) => $table->dropColumn(['file_path', 'file_name']));
    }
};
