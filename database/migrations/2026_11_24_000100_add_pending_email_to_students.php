<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A new email a student asked for, waiting for them to open the confirmation link sent to it. */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
            $table->timestamp('pending_email_at')->nullable()->after('pending_email');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'pending_email_at']);
        });
    }
};
