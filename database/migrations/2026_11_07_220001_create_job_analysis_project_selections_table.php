<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_analysis_project_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_analysis_id')->constrained('job_analyses')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->boolean('selected')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->string('selection_source', 32)->default('recommendation');
            $table->timestamps();

            $table->unique(['job_analysis_id', 'project_id'], 'job_analysis_project_unique');
            $table->index(['user_id', 'job_analysis_id']);
            $table->index(['job_analysis_id', 'selected', 'display_order'], 'japs_analysis_selected_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_analysis_project_selections');
    }
};
