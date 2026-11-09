<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // The person's role at the moment of the action (it may change later).
            $table->string('user_role', 20)->nullable()->after('user_id');
            $table->index('user_role');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_role']);
            $table->dropColumn('user_role');
        });
    }
};
