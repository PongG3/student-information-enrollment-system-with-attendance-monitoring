<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Who archived a student (the Archive page shows staff their own archives; admins see everyone's). */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('archived_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
        });

        // Students archived before this column existed: take the person from the audit log.
        $archived = DB::table('students')->whereNotNull('deleted_at')->pluck('id');
        foreach ($archived as $id) {
            $by = DB::table('audit_logs')->where('action', 'student.archived')->where('subject_type', 'Student')
                ->where('subject_id', $id)->orderByDesc('id')->value('user_id');
            if ($by) {
                DB::table('students')->where('id', $id)->update(['archived_by' => $by]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
        });
    }
};
