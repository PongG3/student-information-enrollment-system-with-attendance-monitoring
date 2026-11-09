<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Classes belong to a block (program + year + section, e.g. BSCS 1-C), so two programs
 * can both have a section C. Class codes become schedule codes (e.g. 202610007).
 * The program and year are optional: a class can also be open to any block (irregular students).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_offerings', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('year_level')->nullable()->after('program_id');
        });

        // One class per subject per block per term (the old rule allowed only one per section letter).
        Schema::table('class_offerings', function (Blueprint $table) {
            $table->unique(['term_id', 'subject_id', 'program_id', 'year_level', 'section'], 'class_offerings_block_unique');
        });
        Schema::table('class_offerings', function (Blueprint $table) {
            $table->dropUnique(['term_id', 'subject_id', 'section']);
        });

        // Give existing classes schedule codes, in the order they were created.
        // Same rule as App\Support\ScheduleCode: start year of the academic year + term's place in it.
        $years = DB::table('academic_years')->pluck('start_date', 'id');
        $prefixes = [];
        $placeInYear = [];
        foreach (DB::table('terms')->orderBy('start_date')->orderBy('id')->get(['id', 'academic_year_id']) as $term) {
            $placeInYear[$term->academic_year_id] = ($placeInYear[$term->academic_year_id] ?? 0) + 1;
            $prefixes[$term->id] = substr((string) $years[$term->academic_year_id], 0, 4) . min($placeInYear[$term->academic_year_id], 9);
        }

        $numberInTerm = [];
        foreach (DB::table('class_offerings')->orderBy('term_id')->orderBy('id')->get(['id', 'term_id']) as $class) {
            $numberInTerm[$class->term_id] = ($numberInTerm[$class->term_id] ?? 0) + 1;
            DB::table('class_offerings')->where('id', $class->id)->update([
                'class_code' => $prefixes[$class->term_id] . str_pad((string) $numberInTerm[$class->term_id], 4, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('class_offerings', function (Blueprint $table) {
            $table->unique(['term_id', 'subject_id', 'section']);
        });
        Schema::table('class_offerings', function (Blueprint $table) {
            $table->dropUnique('class_offerings_block_unique');
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn('year_level');
        });
    }
};
