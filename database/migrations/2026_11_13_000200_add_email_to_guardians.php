<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A guardian's email (optional), for absence alerts. */
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->string('email')->nullable()->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
