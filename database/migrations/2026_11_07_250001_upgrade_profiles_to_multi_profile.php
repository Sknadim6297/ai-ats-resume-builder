<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage: single Master Profile → multiple independent professional profiles.
 *
 * Design:
 * - Each profile owns its own contact/summary and skill attachments (profile_skills).
 * - Projects / experience / education gain profile_id (independent per-profile copies).
 * - Global skills catalog remains shared; never mutated cross-profile unexpectedly.
 * - Job analyses store profile_id permanently (not session-selected profile).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('name', 120)->default('Default Profile')->after('user_id');
            $table->boolean('is_default')->default(false)->after('is_verified');
            $table->boolean('is_active')->default(true)->after('is_default');
            $table->timestamp('archived_at')->nullable()->after('is_active');
        });

        // Drop one-profile-per-user uniqueness.
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->index(['user_id', 'archived_at']);
            $table->index(['user_id', 'is_default']);
        });

        // Existing rows become the default active profile.
        foreach (DB::table('profiles')->orderBy('id')->get() as $row) {
            $name = filled($row->headline) ? mb_substr((string) $row->headline, 0, 120) : 'Default Profile';
            DB::table('profiles')->where('id', $row->id)->update([
                'name' => $name,
                'is_default' => true,
                'is_active' => true,
                'archived_at' => null,
            ]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->after('user_id')->constrained('profiles')->nullOnDelete();
            $table->index(['profile_id', 'is_active']);
        });

        Schema::table('work_experiences', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->after('user_id')->constrained('profiles')->nullOnDelete();
            $table->index(['profile_id', 'start_date']);
        });

        Schema::table('educations', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->after('user_id')->constrained('profiles')->nullOnDelete();
            $table->index(['profile_id', 'start_year']);
        });

        Schema::table('job_analyses', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->after('user_id')->constrained('profiles')->nullOnDelete();
            $table->index(['user_id', 'profile_id']);
        });

        // Backfill profile_id from each user's default profile.
        $defaults = DB::table('profiles')
            ->where('is_default', true)
            ->get(['id', 'user_id'])
            ->keyBy('user_id');

        foreach ($defaults as $userId => $profile) {
            DB::table('projects')->where('user_id', $userId)->whereNull('profile_id')->update(['profile_id' => $profile->id]);
            DB::table('work_experiences')->where('user_id', $userId)->whereNull('profile_id')->update(['profile_id' => $profile->id]);
            DB::table('educations')->where('user_id', $userId)->whereNull('profile_id')->update(['profile_id' => $profile->id]);
            DB::table('job_analyses')->where('user_id', $userId)->whereNull('profile_id')->update(['profile_id' => $profile->id]);
        }

        Schema::table('profile_skills', function (Blueprint $table) {
            $table->string('source', 32)->default('manual')->after('is_active');
            // manual | imported | jd_approved
        });
    }

    public function down(): void
    {
        Schema::table('profile_skills', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('job_analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
        });
        Schema::table('educations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
        });
        Schema::table('work_experiences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
        });

        // Keep only default profiles when rolling back uniqueness.
        $keepIds = DB::table('profiles')->where('is_default', true)->pluck('id');
        DB::table('profiles')->whereNotIn('id', $keepIds)->delete();

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'archived_at']);
            $table->dropIndex(['user_id', 'is_default']);
            $table->dropColumn(['name', 'is_default', 'is_active', 'archived_at']);
            $table->unique('user_id');
        });
    }
};
