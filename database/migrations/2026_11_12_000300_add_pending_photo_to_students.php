<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A photo the student uploaded, waiting for the registrar to approve it as the official one. */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('pending_photo_path')->nullable()->after('photo_path');
            $table->timestamp('pending_photo_at')->nullable()->after('pending_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['pending_photo_path', 'pending_photo_at']);
        });
    }
};
