<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow the same project name across different professional profiles for one user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->unique(['profile_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['profile_id', 'name']);
            $table->unique(['user_id', 'name']);
        });
    }
};
