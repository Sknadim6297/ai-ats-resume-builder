<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_analysis_skill_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_analysis_id')->constrained('job_analyses')->cascadeOnDelete();
            $table->string('canonical_skill_name', 120);
            $table->string('normalized_skill_key', 120);
            $table->string('requirement_type', 20);
            $table->string('decision', 32)->default('pending');
            $table->string('confirmation_status', 32)->default('not_confirmed');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['job_analysis_id', 'normalized_skill_key', 'requirement_type'],
                'job_analysis_skill_decisions_unique'
            );
            $table->index(['user_id', 'job_analysis_id']);
            $table->index(['job_analysis_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_analysis_skill_decisions');
    }
};
