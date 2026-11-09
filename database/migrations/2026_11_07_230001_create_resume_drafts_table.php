<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_analysis_id')->constrained('job_analyses')->cascadeOnDelete();
            $table->string('template_version', 32)->default('ats-universal-v1');
            $table->string('resume_schema_version', 32)->default('1.0');
            $table->json('structured_content')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('generation_status', 32)->default('pending');
            $table->text('safe_error')->nullable();
            $table->timestamps();

            $table->unique('job_analysis_id');
            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'generation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_drafts');
    }
};
