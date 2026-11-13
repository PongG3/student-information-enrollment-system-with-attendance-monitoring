<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The breach register (Data Privacy Act, RA 10173): every incident where personal data may have been
     * lost, stolen or seen by the wrong people, what was done, and whether the NPC and the people affected
     * were told (serious breaches: within 72 hours).
     */
    public function up(): void
    {
        Schema::create('data_breaches', function (Blueprint $table) {
            $table->id();
            $table->dateTime('discovered_at');
            $table->string('summary', 255);              // what happened
            $table->text('data_affected')->nullable();   // which kinds of data
            $table->unsignedInteger('people_affected')->nullable();
            $table->text('actions_taken')->nullable();
            $table->date('npc_notified_on')->nullable(); // National Privacy Commission
            $table->date('subjects_notified_on')->nullable();
            $table->string('status', 20)->default('open'); // open, closed
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breaches');
    }
};
