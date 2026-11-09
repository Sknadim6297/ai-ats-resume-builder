<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_draft_id')->constrained('resume_drafts')->cascadeOnDelete();
            $table->foreignId('job_analysis_id')->constrained('job_analyses')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('change_type', 32);
            $table->string('template_version', 32)->default('ats-universal-v1');
            $table->string('resume_schema_version', 32)->default('1.0');
            $table->json('structured_content');
            $table->json('source_snapshot')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['resume_draft_id', 'version_number'], 'resume_versions_draft_number_unique');
            $table->index(['user_id', 'updated_at']);
            $table->index(['resume_draft_id', 'is_current']);
            $table->index(['job_analysis_id', 'user_id']);
        });

        Schema::table('resume_drafts', function (Blueprint $table) {
            $table->unsignedInteger('current_version_number')->nullable()->after('safe_error');
            $table->foreignId('current_version_id')
                ->nullable()
                ->after('current_version_number')
                ->constrained('resume_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resume_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
            $table->dropColumn('current_version_number');
        });

        Schema::dropIfExists('resume_versions');
    }
};
