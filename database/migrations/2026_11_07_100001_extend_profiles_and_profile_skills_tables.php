<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('years_of_experience')->nullable()->after('professional_summary');
            $table->json('additional_links')->nullable()->after('years_of_experience');
        });

        Schema::table('profile_skills', function (Blueprint $table) {
            $table->unsignedSmallInteger('last_used_year')->nullable()->after('years_experience');
            $table->boolean('is_active')->default(true)->after('is_verified');
            $table->index(['profile_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('profile_skills', function (Blueprint $table) {
            $table->dropIndex(['profile_id', 'is_active']);
            $table->dropColumn(['last_used_year', 'is_active']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['years_of_experience', 'additional_links']);
        });
    }
};
