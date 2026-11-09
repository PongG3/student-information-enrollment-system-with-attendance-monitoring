<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subjects are either
 *   major: a program's specialized subject, only for the programs it is linked to (program_subject);
 *   minor: general education and PE (Ethics, Purposive Communication, PE...), open to every program.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('category', 10)->default('minor')->after('units');
            $table->index('category');
        });

        // Subjects already linked to programs are majors; the rest stay minor.
        DB::table('subjects')->whereIn('id', DB::table('program_subject')->select('subject_id'))->update(['category' => 'major']);
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
