<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The teacher's note on an excuse letter (the remarks entered on the sheet), shown to the student.
        Schema::table('absence_excuses', function (Blueprint $table) {
            $table->string('decision_note', 255)->nullable()->after('status');
        });

        // Why the registrar rejected a student's photo, shown on the student's profile until they send another.
        Schema::table('students', function (Blueprint $table) {
            $table->string('photo_rejected_reason', 255)->nullable()->after('pending_photo_at');
            $table->timestamp('photo_rejected_at')->nullable()->after('photo_rejected_reason');
        });
    }

    public function down(): void
    {
        Schema::table('absence_excuses', fn (Blueprint $table) => $table->dropColumn('decision_note'));
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['photo_rejected_reason', 'photo_rejected_at']));
    }
};
