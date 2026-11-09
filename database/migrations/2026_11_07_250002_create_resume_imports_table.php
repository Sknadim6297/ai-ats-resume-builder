<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->string('original_filename');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedInteger('file_size')->default(0);
            $table->string('disk', 32)->default('local');
            $table->string('storage_path')->nullable();
            $table->string('status', 32)->default('uploaded');
            // uploaded|extracting|extracted|parsing|parsed|review|completed|failed|cancelled
            $table->string('extraction_method', 40)->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->longText('extracted_text')->nullable();
            $table->json('parsed_payload')->nullable();
            $table->json('review_payload')->nullable();
            $table->string('merge_strategy', 32)->nullable();
            // new_profile|merge|replace_fields
            $table->text('safe_error')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_imports');
    }
};
