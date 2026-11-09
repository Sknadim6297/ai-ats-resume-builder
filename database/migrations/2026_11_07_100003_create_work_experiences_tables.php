<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('job_title');
            $table->string('employment_type', 100);
            $table->string('location')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->json('responsibilities')->nullable();
            $table->json('achievements')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
            $table->index(['user_id', 'is_current']);
        });

        Schema::create('work_experience_technologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_experience_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('skill_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['work_experience_id', 'name'], 'work_exp_tech_unique');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_experience_technologies');
        Schema::dropIfExists('work_experiences');
    }
};
