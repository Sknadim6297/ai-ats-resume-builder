<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('job_title')->nullable();
            $table->string('company_name')->nullable();
            $table->string('job_location')->nullable();
            $table->string('job_url')->nullable();
            $table->longText('job_description');
            $table->json('analysis_json')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_analyses');
    }
};
