<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Staff profile details the staff member keeps up to date themselves. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('department', 120)->nullable()->after('role');      // e.g. "Computer Science"; students see it
            $table->string('office', 120)->nullable()->after('department');    // e.g. "Faculty Room 2"
            $table->string('contact_number', 20)->nullable()->after('office'); // staff only
            $table->string('photo_path')->nullable()->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['department', 'office', 'contact_number', 'photo_path']);
        });
    }
};
