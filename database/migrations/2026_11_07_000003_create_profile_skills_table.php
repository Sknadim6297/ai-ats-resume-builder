<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profile ↔ skill membership with proficiency and verification flags.
     */
    public function up(): void
    {
        Schema::create('profile_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('proficiency')->default(3); // 1–5
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->unique(['profile_id', 'skill_id']);
            $table->index(['profile_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_skills');
    }
};
