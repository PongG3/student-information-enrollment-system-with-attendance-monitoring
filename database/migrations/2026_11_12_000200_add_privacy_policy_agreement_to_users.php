<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Which version of the staff data privacy policy each account agreed to, and when. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('privacy_policy_version', 40)->nullable()->after('must_change_password');
            $table->timestamp('privacy_policy_agreed_at')->nullable()->after('privacy_policy_version');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['privacy_policy_version', 'privacy_policy_agreed_at']);
        });
    }
};
