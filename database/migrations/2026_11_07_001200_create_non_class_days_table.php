<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Holidays and suspended-class days. Never counted as absences.
        Schema::create('non_class_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('description', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_class_days');
    }
};
